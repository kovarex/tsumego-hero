<?php

use Phinx\Migration\AbstractMigration;

/**
 * Embed position data directly in comment message instead of separate field.
 *
 * This migration:
 * 1. Changes tsumego_comment.message from VARCHAR(2048) to TEXT for more space
 * 2. Migrates existing position data into message as [pos:...] tags
 * 3. Drops the position column (now redundant)
 *
 * New format: [pos:x/y/pX/pY/cX/cY/moveNumber/childrenCount/orientation|path]
 * Example: [pos:3/3/-1/-1/4/3/1/1/top-right|3/3+4/3]
 * Displayed as: [C12->B20] (clickable button, coordinates computed from path)
 */
class EmbedPositionsInComment extends AbstractMigration
{
	public function up()
	{
		// Step 1: Change message to TEXT (from VARCHAR(2048))
		$this->execute("ALTER TABLE tsumego_comment MODIFY message TEXT NOT NULL");

		// Step 2: Migrate existing positions into message as [pos:...] tags
		// Append [pos:...] to messages that have position data
		$this->execute("
			UPDATE tsumego_comment 
			SET message = CONCAT(message, ' [pos:', position, ']')
			WHERE position IS NOT NULL AND position != ''
		");

		// Step 3: Drop position column (now embedded in message)
		$this->table('tsumego_comment')
			->removeColumn('position')
			->update();
	}

	public function down()
	{
		// Restore position column
		$this->table('tsumego_comment')
			->addColumn('position', 'string', ['limit' => 300, 'null' => true, 'after' => 'user_id'])
			->update();

		// Extract [pos:...] from message back to position column
		// Note: This is a best-effort reversal, multiple positions will only get the last one
		$this->execute("
			UPDATE tsumego_comment 
			SET position = SUBSTRING_INDEX(SUBSTRING_INDEX(message, '[pos:', -1), ']', 1),
				message = TRIM(TRAILING ']' FROM TRIM(TRAILING SUBSTRING_INDEX(SUBSTRING_INDEX(message, '[pos:', -1), ']', 1) FROM TRIM(TRAILING '[pos:' FROM message)))
			WHERE message LIKE '%[pos:%'
		");

		// Change message back to VARCHAR(2048)
		$this->execute("ALTER TABLE tsumego_comment MODIFY message VARCHAR(2048) NOT NULL");
	}
}
