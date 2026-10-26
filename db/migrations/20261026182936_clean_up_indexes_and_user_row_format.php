<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Indexes that only repeat the start of a longer index cost on every write and help no query: the longer
 * index serves the same lookups, and the foreign keys use it too. They go:
 *
 * - tsumego_status.user_id_index, set_connection.set_id, tag_connection.tsumego_id and
 *   achievement_status.user_id start their table's unique key,
 * - sgf.tsumego_id starts tsumego_id_sgf_status_id,
 * - time_mode_attempt.user_id_and_tsumego_id is on tsumego_id alone, the same as its index tsumego_id,
 * - user.rating: the highscores number every player with ROW_NUMBER() and sort them all anyway.
 *
 * user and set were the last tables in ROW_FORMAT=COMPACT, which caps an index key at 767 bytes. In
 * DYNAMIC, like the rest, user.external_id (a Google id, varchar(200) = 800 bytes) can get the unique
 * key the Google sign-in looks it up by, instead of reading the whole table. And an e-mail address can
 * have up to 254 characters, not 50.
 *
 * The unique key fails if two accounts share a Google id, before anything else of user changes.
 */
final class CleanUpIndexesAndUserRowFormat extends AbstractMigration
{
	private const REDUNDANT_INDEXES = [
		'tsumego_status' => 'user_id_index',
		'set_connection' => 'set_id',
		'tag_connection' => 'tsumego_id',
		'achievement_status' => 'user_id',
		'sgf' => 'tsumego_id',
		'time_mode_attempt' => 'user_id_and_tsumego_id',
	];

	public function up(): void
	{
		foreach (self::REDUNDANT_INDEXES as $table => $index)
			$this->execute("ALTER TABLE `$table` DROP INDEX IF EXISTS `$index`");

		// In place, so the table stays writable. A shared Google id stops it with "Duplicate entry",
		// while in a copied table the same failure shows as error 1834 about the foreign keys.
		$this->execute('ALTER TABLE user
			ROW_FORMAT=DYNAMIC,
			ADD UNIQUE KEY IF NOT EXISTS external_id (external_id),
			DROP INDEX IF EXISTS rating,
			ALGORITHM=INPLACE, LOCK=NONE');
		// A VARCHAR past 255 bytes needs a longer length prefix, so this one copies the table, a few seconds
		// in which the players' writes to it wait.
		$this->execute("ALTER TABLE user
			MODIFY email VARCHAR(254) NOT NULL COMMENT 'email',
			MODIFY unconfirmed_email VARCHAR(254) DEFAULT NULL");
		$this->execute('ALTER TABLE `set` ROW_FORMAT=DYNAMIC, ALGORITHM=INPLACE, LOCK=NONE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `set` ROW_FORMAT=COMPACT');
		// fails while an address longer than 50 characters is stored
		$this->execute("ALTER TABLE user
			ADD INDEX IF NOT EXISTS rating (rating),
			DROP INDEX IF EXISTS external_id,
			MODIFY email VARCHAR(50) NOT NULL COMMENT 'email',
			MODIFY unconfirmed_email VARCHAR(50) DEFAULT NULL,
			ROW_FORMAT=COMPACT");

		$this->execute('ALTER TABLE time_mode_attempt ADD INDEX IF NOT EXISTS user_id_and_tsumego_id (tsumego_id)');
		$this->execute('ALTER TABLE sgf ADD INDEX IF NOT EXISTS tsumego_id (tsumego_id)');
		$this->execute('ALTER TABLE achievement_status ADD INDEX IF NOT EXISTS user_id (user_id)');
		$this->execute('ALTER TABLE tag_connection ADD INDEX IF NOT EXISTS tsumego_id (tsumego_id)');
		$this->execute('ALTER TABLE set_connection ADD INDEX IF NOT EXISTS set_id (set_id)');
		$this->execute('ALTER TABLE tsumego_status ADD INDEX IF NOT EXISTS user_id_index (user_id)');
	}
}
