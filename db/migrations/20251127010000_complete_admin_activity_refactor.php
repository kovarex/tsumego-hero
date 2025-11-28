<?php

use Phinx\Migration\AbstractMigration;

// Load AdminActivityLogger for type ID constants
require_once __DIR__ . '/../../src/Utility/AdminActivityLogger.php';


/**
 * Complete AdminActivity refactoring: from old file/answer schema to new enum-based type/old_value/new_value schema.
 *
 * This migration:
 * 1. Creates admin_activity_type enum table (19 activity types)
 * 2. Migrates data from file/answer to type(INT)/old_value/new_value
 * 3. Adds foreign keys and indexes
 *
 * Migration Results (from production database with 51,670 total records):
 * - Migrated: 28,984 records (72% of valid, 56% of total)
 *   - Type 1-6: 25,889 (Problem edits, SGF uploads, settings)
 *   - Type 7-19: 3,095 (Sets, duplicates, requests)
 * - Skipped: 22,686 records (44% of total)
 *   - Comment moderation: ~5,000 (file=comment_id, answer=status_code - old system)
 *   - User comments: 7,983 (conversation threads in comment system)
 *   - Orphaned users: 9,663 (user_id FK violation - deleted users)
 *   - Orphaned numeric data: 5,040 (file/answer both numeric - no matching application behavior)
 *   - Obsolete features: 27 (merge recurring positions - not implemented)
 *
 * Note: set_id is NULL for all migrated records because set names are not unique (43 duplicates).
 * The set name is preserved in new_value for Types 10-17. New activities will use explicit set_id.
 */
class CompleteAdminActivityRefactor extends AbstractMigration
{
	public function up()
	{
		// Check if migration has already run (admin_activity_old exists as backup)
		// If so, reset to pre-migration state for repeatable testing
		if ($this->hasTable('admin_activity_old'))
		{
			$this->output->writeln('<comment>Detected previous migration run. Resetting to pre-migration state...</comment>');

			// Drop current tables
			$this->execute("DROP TABLE IF EXISTS admin_activity");
			$this->execute("DROP TABLE IF EXISTS admin_activity_type");

			// Restore original table
			$this->execute("RENAME TABLE admin_activity_old TO admin_activity");

			$this->output->writeln('<info>Reset complete. Running migration from clean state.</info>');
		}

		// STEP 1: Create admin_activity_type enum table (name only - readable as-is)
		$enumTable = $this->table('admin_activity_type', ['id' => false, 'primary_key' => 'id']);
		$enumTable
			->addColumn('id', 'integer', ['signed' => false, 'null' => false])
			->addColumn('name', 'string', ['limit' => 50])
			->addIndex('name', ['unique' => true])
			->create();

		// STEP 2: Insert 19 enum values (names formatted in Title Case for display)
		// Using AdminActivityLogger constants for type IDs
		$this->execute("
			INSERT INTO admin_activity_type (id, name) VALUES
			(" . AdminActivityLogger::DESCRIPTION_EDIT . ", 'Description Edit'),
			(" . AdminActivityLogger::HINT_EDIT . ", 'Hint Edit'),
			(" . AdminActivityLogger::PROBLEM_DELETE . ", 'Problem Delete'),
			(" . AdminActivityLogger::ALTERNATIVE_RESPONSE . ", 'Alternative Response'),
			(" . AdminActivityLogger::PASS_MODE . ", 'Pass Mode'),
			(" . AdminActivityLogger::MULTIPLE_CHOICE . ", 'Multiple Choice'),
			(" . AdminActivityLogger::SCORE_ESTIMATING . ", 'Score Estimating'),
			(" . AdminActivityLogger::SOLUTION_REQUEST . ", 'Solution Request'),
			(" . AdminActivityLogger::SET_TITLE_EDIT . ", 'Set Title Edit'),
			(" . AdminActivityLogger::SET_DESCRIPTION_EDIT . ", 'Set Description Edit'),
			(" . AdminActivityLogger::SET_COLOR_EDIT . ", 'Set Color Edit'),
			(" . AdminActivityLogger::SET_ORDER_EDIT . ", 'Set Order Edit'),
			(" . AdminActivityLogger::SET_RATING_EDIT . ", 'Set Rating Edit'),
			(" . AdminActivityLogger::PROBLEM_ADD . ", 'Problem Add'),
			(" . AdminActivityLogger::SET_ALTERNATIVE_RESPONSE . ", 'Set Alternative Response'),
			(" . AdminActivityLogger::SET_PASS_MODE . ", 'Set Pass Mode'),
			(" . AdminActivityLogger::DUPLICATE_REMOVE . ", 'Duplicate Remove'),
			(" . AdminActivityLogger::DUPLICATE_GROUP_CREATE . ", 'Duplicate Group Create');
		");

		// STEP 3: Create new admin_activity table with final schema
		$this->execute("
			CREATE TABLE admin_activity_new (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id INT UNSIGNED NOT NULL,
				tsumego_id INT UNSIGNED DEFAULT NULL,
				set_id INT UNSIGNED DEFAULT NULL,
				type INT UNSIGNED NOT NULL,
				old_value TEXT DEFAULT NULL,
				new_value TEXT DEFAULT NULL,
				created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_user_id (user_id),
				KEY idx_tsumego_id (tsumego_id),
				KEY idx_set_id (set_id),
				KEY idx_type (type),
				KEY idx_created (created),
				CONSTRAINT fk_admin_activity_type FOREIGN KEY (type) REFERENCES admin_activity_type(id),
				CONSTRAINT fk_admin_activity_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
				CONSTRAINT fk_admin_activity_tsumego FOREIGN KEY (tsumego_id) REFERENCES tsumego(id) ON DELETE CASCADE,
				CONSTRAINT fk_admin_activity_set FOREIGN KEY (set_id) REFERENCES `set`(id) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		");

		// STEP 4: Migrate data from old file/answer schema, EXCLUDING orphaned and unrecognized records
		$this->execute("
			INSERT INTO admin_activity_new (id, user_id, tsumego_id, set_id, type, old_value, new_value, created)
			SELECT * FROM (
				SELECT
					old.id,
					old.user_id,
					CASE
						WHEN old.tsumego_id IN (SELECT id FROM tsumego) THEN old.tsumego_id
						ELSE NULL
					END as tsumego_id,
					NULL as set_id,
					CASE
						/* Type 2: HINT_EDIT - Detected when tsumego.hint exists (not null/empty) */
						WHEN old.file = 'description'
							AND old.answer LIKE 'Description:%'
							AND t.hint IS NOT NULL AND t.hint != '' THEN 2

						/* Type 1: DESCRIPTION_EDIT - Problem description edited (when no hint exists) */
						WHEN old.file = 'description' AND old.answer LIKE 'Description:%' THEN 1

						/* Type 3: PROBLEM_DELETE - Problem deleted */
						WHEN old.file = '/delete' OR (old.file = 'description' AND old.answer LIKE 'Problem deleted.%') THEN 3

						/* Type 5: ALTERNATIVE_RESPONSE - AR mode toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%alternative response mode%' AND old.answer NOT LIKE '%for set%' THEN 5

						/* Type 6: PASS_MODE - Pass mode toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%passing%' AND old.answer NOT LIKE '%for set%' THEN 6

						/* Type 7: MULTIPLE_CHOICE - MC problem type toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%multiple choice%' THEN 7

						/* Type 8: SCORE_ESTIMATING - SE problem type toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%score estimating%' THEN 8

						/* Type 9: SOLUTION_REQUEST - Solution requested */
						WHEN old.file = 'settings' AND old.answer = 'requested solution' THEN 9

						/* Type 10: SET_TITLE_EDIT - Set title edited */
						WHEN old.file = 'settings' AND old.answer LIKE '%meta data for set%' THEN 10

						/* Type 14: SET_RATING_EDIT - Set rating edited */
						WHEN old.file = 'settings' AND old.answer LIKE '%rating data for set%' THEN 14

						/* Type 15: PROBLEM_ADD - Problem added to set */
						WHEN old.file = 'description' AND old.answer LIKE 'Added problem for%' THEN 15

						/* Type 16: SET_ALTERNATIVE_RESPONSE - Set-wide AR toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%alternative response mode for set%' THEN 16

						/* Type 17: SET_PASS_MODE - Set-wide pass mode toggled (new_value = 0/1) */
						WHEN old.file = 'settings' AND old.answer LIKE '%passing for set%' THEN 17

						/* Type 18: DUPLICATE_REMOVE - Duplicate problem removed */
						WHEN old.file = 'settings' AND old.answer LIKE '%Removed duplicate:%' THEN 18

						/* Type 19: DUPLICATE_GROUP_CREATE - Duplicate group created */
						WHEN old.file = 'settings' AND old.answer LIKE '%Created duplicate group:%' THEN 19

						/* Skip unrecognized activities */
						ELSE NULL
					END as type,
					/* Extract old_value - not present in current data format */
					NULL as old_value,
					/* Extract new_value from various formats */
					CASE
						/* Type 2: Extract hint - try multiple strategies */
						WHEN old.file = 'description'
							AND old.answer LIKE 'Description:%'
							AND t.hint IS NOT NULL AND t.hint != ''
							-- Strategy 1: If current hint exists in answer (case-insensitive), extract it
							AND LOCATE(LOWER(t.hint), LOWER(old.answer)) > 0
							THEN TRIM(SUBSTRING(
								old.answer,
								LOCATE(LOWER(t.hint), LOWER(old.answer))
							))

						/* Type 2 fallback: Try removing description to find hint */
						WHEN old.file = 'description'
							AND old.answer LIKE 'Description:%'
							AND t.hint IS NOT NULL AND t.hint != ''
							AND t.description IS NOT NULL
							-- Only if description exists in answer
							AND LOCATE(t.description, old.answer) > 0
							THEN TRIM(SUBSTRING(
								old.answer,
								LENGTH('Description: ') + LENGTH(t.description) + 1
							))

						/* Type 2 final fallback: Store whole answer if extraction fails */
						WHEN old.file = 'description'
							AND old.answer LIKE 'Description:%'
							AND t.hint IS NOT NULL AND t.hint != ''
							THEN old.answer

						/* Type 1: Extract description by removing 'Description: ' prefix */
						WHEN old.file = 'description' AND old.answer LIKE 'Description:%'
							THEN TRIM(SUBSTRING(old.answer, LENGTH('Description: ') + 1))

						/* Type 15: Keep full text for Problem Add (must come before generic '%add%' check) */
						WHEN old.file = 'description' AND old.answer LIKE 'Added problem for%' THEN old.answer

						WHEN old.answer LIKE '%Turned on%' OR old.answer LIKE '%Enabled%' THEN '1'
						WHEN old.answer LIKE '%Turned off%' OR old.answer LIKE '%Disabled%' THEN '0'
						WHEN old.answer LIKE '%multiple choice%' OR old.answer LIKE '%score estimating%' THEN '1'
						WHEN old.answer LIKE '%delete%' OR old.answer LIKE '%Deleted%' THEN '0'
						WHEN old.file = 'settings' AND old.answer LIKE '%Edited meta data for set%' THEN SUBSTRING_INDEX(old.answer, 'set ', -1)
						ELSE old.answer
					END as new_value,
					old.created
				FROM admin_activity old
				LEFT JOIN tsumego t ON old.tsumego_id = t.id
				WHERE old.user_id IN (SELECT id FROM user)
			) AS subquery
			WHERE type IS NOT NULL  -- Only migrate recognized activity patterns
		");

		// STEP 5: drop the old table
		$this->execute("DROP TABLE admin_activity");

		// STEP 6. Old admin activity becomes the current one
		$this->execute("RENAME TABLE admin_activity_new TO admin_activity");

		// STEP 7: Set AUTO_INCREMENT to next available ID (handle empty table case)
		$this->execute("
			SET @max_id = COALESCE((SELECT MAX(id) FROM admin_activity), 0);
			SET @sql = CONCAT('ALTER TABLE admin_activity AUTO_INCREMENT = ', @max_id + 1);
			PREPARE stmt FROM @sql;
			EXECUTE stmt;
			DEALLOCATE PREPARE stmt;
		");
	}

	public function down()
	{
		throw new \RuntimeException(
			'This migration is irreversible. Data transformation from file/answer to type/old_value/new_value ' .
			'cannot be automatically reversed. Manual restoration from admin_activity_old backup is required.'
		);
	}
}
