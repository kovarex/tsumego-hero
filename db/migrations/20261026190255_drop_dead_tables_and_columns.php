<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Drops the tables and columns nothing reads:
 *
 * - cake_sessions: the sessions are PHP files (config/core.php, 'defaults' => 'php'),
 * - publish_date: the schedule took its place,
 * - signature: the board editor sent "signatures" of the correct moves in a cookie once a week per
 *   problem and the play page stored them, for a pattern search that was never written,
 * - site: old news, shown only by /sites/view, which nothing linked to,
 * - tsumego_comment_before_sgf_coordinates: the comments as they were before CommentCoordinatesInSgfSpace
 *   moved them to SGF coordinates, kept only for its down(),
 * - tsumego.part and part_increment (never used), tsumego.solved and failed (only reset on
 *   publishing), tsumego.activity_value (only counted up),
 * - set.multiplier (never read) and set.author (written for sandbox sets only),
 * - achievement_condition.created, which nothing reads, and which ON UPDATE made a time of the last change.
 *
 * down() brings the tables and columns back empty.
 */
final class DropDeadTablesAndColumns extends AbstractMigration
{
	private const TABLES = ['cake_sessions', 'publish_date', 'signature', 'site', 'tsumego_comment_before_sgf_coordinates'];

	public function up(): void
	{
		foreach (self::TABLES as $table)
			$this->execute('DROP TABLE IF EXISTS `' . $table . '`');
		$this->execute('ALTER TABLE tsumego
			DROP COLUMN IF EXISTS part,
			DROP COLUMN IF EXISTS part_increment,
			DROP COLUMN IF EXISTS solved,
			DROP COLUMN IF EXISTS failed,
			DROP COLUMN IF EXISTS activity_value');
		$this->execute('ALTER TABLE `set` DROP COLUMN IF EXISTS multiplier, DROP COLUMN IF EXISTS author');
		$this->execute('ALTER TABLE achievement_condition DROP COLUMN IF EXISTS created');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE achievement_condition
			ADD COLUMN IF NOT EXISTS created datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()');
		$this->execute('ALTER TABLE `set`
			ADD COLUMN IF NOT EXISTS author varchar(400) DEFAULT NULL AFTER title2,
			ADD COLUMN IF NOT EXISTS multiplier float NOT NULL DEFAULT 1 AFTER public');
		$this->execute("ALTER TABLE tsumego
			ADD COLUMN IF NOT EXISTS part varchar(50) DEFAULT NULL COMMENT 'Experimental field. Currently one use for Study Group in the sandbox.' AFTER id,
			ADD COLUMN IF NOT EXISTS part_increment int(11) DEFAULT NULL COMMENT 'Experimental field. No use currently.' AFTER part,
			ADD COLUMN IF NOT EXISTS solved int(11) DEFAULT NULL COMMENT 'Number of solves by registered users. In between step to calculate the success percentage.' AFTER author_user_id,
			ADD COLUMN IF NOT EXISTS failed int(11) DEFAULT NULL COMMENT 'Number of fails by registered users. In between step to calculate the success percentage.' AFTER solved,
			ADD COLUMN IF NOT EXISTS activity_value int(11) NOT NULL DEFAULT 0 AFTER pass");
		$this->execute("CREATE TABLE IF NOT EXISTS tsumego_comment_before_sgf_coordinates (
			tsumego_comment_id int(10) unsigned NOT NULL,
			message varchar(2048) NOT NULL,
			position varchar(300) DEFAULT NULL,
			PRIMARY KEY (tsumego_comment_id),
			CONSTRAINT tsumego_comment_before_sgf_coordinates_tsumego_comment_id
				FOREIGN KEY (tsumego_comment_id) REFERENCES tsumego_comment (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='The text and position of comments before LegacyCommentConverter moved them to SGF coordinates'");
		$this->execute('CREATE TABLE IF NOT EXISTS site (
			id int(11) NOT NULL AUTO_INCREMENT,
			title mediumtext DEFAULT NULL,
			body mediumtext DEFAULT NULL,
			PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$this->execute('CREATE TABLE IF NOT EXISTS signature (
			id int(11) NOT NULL AUTO_INCREMENT,
			tsumego_id int(11) NOT NULL,
			signature varchar(500) DEFAULT NULL,
			created timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
			PRIMARY KEY (id),
			KEY tsumego_id (tsumego_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$this->execute('CREATE TABLE IF NOT EXISTS publish_date (
			id int(11) NOT NULL AUTO_INCREMENT,
			tsumego_id int(11) DEFAULT NULL,
			date datetime DEFAULT NULL,
			PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$this->execute('CREATE TABLE IF NOT EXISTS cake_sessions (
			id varchar(255) NOT NULL,
			data text DEFAULT NULL,
			expires int(11) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY expires_idx (expires)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
	}
}
