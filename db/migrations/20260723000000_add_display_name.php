<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add display_name column to user table and clean up legacy data.
 *
 * This migration is IRREVERSIBLE - it cleans up legacy data that cannot be restored.
 *
 * Changes (in order):
 * 1. Add author_user_id FK column to tsumego table FIRST
 * 2. Populate author_user_id by matching tsumego.author to user.name (both still have g__ prefix)
 * 3. Clear old picture filenames (they pointed to non-deployed local files)
 * 4. Add display_name column
 * 5. Strip g__ prefix from email column (duplicates allowed)
 * 6. Strip g__ prefix from external_id column
 * 7. Strip g__ prefix from tsumego.author column
 * 8. Update tsumego.author to match user.display_name for tracked authors
 * 9. Make name column nullable and set to NULL for Google users
 *
 * Key insight: author_user_id matching must happen BEFORE any g__ stripping
 * so "g__John Smith" in tsumego.author matches "g__John Smith" in user.name.
 *
 * Google users (external_id IS NOT NULL) will have name=NULL because:
 * - They can't use password login (password_hash = 'google_oauth')
 * - Display should use display_name instead
 * - phpBB SSO uses display_name for usernames
 */
final class AddDisplayName extends AbstractMigration
{
	public function up(): void
	{
		// Disable FK checks for faster ALTER operations
		$this->execute("SET FOREIGN_KEY_CHECKS = 0");
		$this->execute("SET UNIQUE_CHECKS = 0");

		try
		{
			// Step 1: Add author_user_id column to tsumego table FIRST
			// This must happen BEFORE any g__ prefix stripping so we can match:
			// tsumego.author = "g__John Smith" matches user.name = "g__John Smith"
			$this->table('tsumego')
				->addColumn('author_user_id', 'integer', [
					'null' => true,
					'signed' => false,
					'after' => 'author',
				])
				->addForeignKey('author_user_id', 'user', 'id', [
					'delete' => 'SET_NULL',
					'update' => 'CASCADE',
				])
				->addIndex('author_user_id')
				->update();

			// Step 2: Populate author_user_id by matching tsumego.author to user.name
			// Both still have original values (including g__ prefixes for Google users)
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON u.name = t.author
				SET t.author_user_id = u.id
				WHERE t.author_user_id IS NULL
			");

			// Step 2b: Fallback for Google users whose tsumego.author was already stripped
			// In some cases, tsumego.author = "Joschka Zimdars" but user.name = "g__Joschka Zimdars"
			// This happens when the PHP code stripped g__ from author when saving the tsumego
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON u.name = CONCAT('g__', t.author)
				SET t.author_user_id = u.id
				WHERE t.author_user_id IS NULL
				AND t.author IS NOT NULL
				AND t.author != ''
			");

			// Step 3: Clear old picture filenames
			// The old `picture` values are filenames like `g__106434188846824891788.png`
			// that referenced locally-stored files in `/img/google/` which were never deployed.
			// New code will populate this with Google CDN URLs on next login.
			$this->execute("UPDATE user SET picture = NULL WHERE picture IS NOT NULL");

			// Step 4: Add display_name column (nullable initially)
			$this->table('user')
				->addColumn('display_name', 'string', [
					'limit' => 50,
					'null' => true,
					'after' => 'name',
					'comment' => 'Display name shown in UI (separate from login name)',
				])
				->update();

			// Step 5: Populate display_name for all existing users
			// For Google users (external_id not null): strip g__ prefix if present
			// For regular users: copy from name
			$this->execute("
				UPDATE user
				SET display_name = CASE
					WHEN external_id IS NOT NULL AND name LIKE 'g\\_\\_%' ESCAPE '\\\\' THEN SUBSTRING(name, 4)
					ELSE name
				END
			");

			// Step 5b: Handle duplicates by appending (N) suffix
			$this->execute("
				CREATE TEMPORARY TABLE temp_duplicates AS
				SELECT display_name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt
				FROM user
				WHERE display_name IS NOT NULL
				GROUP BY display_name
				HAVING COUNT(*) > 1;
			");

			$duplicates = $this->fetchAll("SELECT display_name, ids FROM temp_duplicates");

			foreach ($duplicates as $row)
			{
				$displayName = $row['display_name'];
				$ids = explode(',', $row['ids']);

				// Keep the first one (oldest), rename the others
				array_shift($ids);

				$suffix = 2;
				foreach ($ids as $id)
				{
					// Use (N) suffix format: "John Doe (2)", "John Doe (3)", etc.
					$newName = $displayName . ' (' . $suffix . ')';
					while ($this->fetchRow("SELECT id FROM user WHERE display_name = " . $this->getAdapter()->getConnection()->quote($newName)))
					{
						$suffix++;
						$newName = $displayName . ' (' . $suffix . ')';
					}

					$this->execute("UPDATE user SET display_name = " . $this->getAdapter()->getConnection()->quote($newName) . " WHERE id = $id");
					$suffix++;
				}
			}

			$this->execute("DROP TEMPORARY TABLE IF EXISTS temp_duplicates");

			// Step 5c: Make display_name NOT NULL and add UNIQUE constraint
			$this->execute("ALTER TABLE user MODIFY display_name VARCHAR(50) NOT NULL");

			$this->table('user')
				->addIndex(['display_name'], ['unique' => true, 'name' => 'idx_display_name_unique'])
				->update();

			// Step 6a: Strip g__ prefix from email column
			// Email does NOT have UNIQUE constraint, so duplicates are allowed
			// This affects Google users who were registered with 'g__email@example.com'
			$this->execute("
				UPDATE user
				SET email = SUBSTRING(email, 4)
				WHERE email LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 6b: Strip g__ prefix from external_id column
			// The column name already tells us it's for external providers
			$this->execute("
				UPDATE user
				SET external_id = SUBSTRING(external_id, 4)
				WHERE external_id LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 7: Strip g__ prefix from tsumego.author column (for display consistency)
			$this->execute("
				UPDATE tsumego
				SET author = SUBSTRING(author, 4)
				WHERE author LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 8: Update tsumego.author to match user's display_name for tracked authors
			// This ensures author string stays in sync with the user's chosen display name
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON t.author_user_id = u.id
				SET t.author = u.display_name
			");

			// Step 9: Make name nullable and set to NULL for Google users
			// Google users can't use password login, so they don't need a name for login
			$this->execute("ALTER TABLE user MODIFY name VARCHAR(50) NULL");
			$this->execute("UPDATE user SET name = NULL WHERE external_id IS NOT NULL");
		}
		finally
		{
			// Re-enable FK checks
			$this->execute("SET FOREIGN_KEY_CHECKS = 1");
			$this->execute("SET UNIQUE_CHECKS = 1");
		}
	}

	/**
	 * This migration is IRREVERSIBLE.
	 *
	 * Reasons:
	 * - picture column was cleared (old filenames are gone)
	 * - g__ prefixes stripped from email/external_id cannot be accurately restored
	 * - Restoring would break functionality anyway
	 *
	 * To "undo": restore from backup before this migration.
	 */
	public function down(): void
	{
		throw new \RuntimeException(
			'This migration is irreversible. Restore from database backup if needed.'
		);
	}
}
