<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The user table is loaded on every request, these columns of it were dead:
 *
 * - lastRefresh, promoted, penalty, sortOrder, sortColor, ip, location: nothing reads or writes them,
 * - reward: the date of a donation, nothing reads it since the "member since" backfill
 *   (20261025100000) used it as evidence,
 * - login_token: the forum signs in with the JWT auth_token cookie now,
 * - readingTrial, reuse4: only the daily cron reset them, nothing read them.
 *
 * Two columns get names saying what they are: t_glicko is the difficulty a player picked for the
 * rating mode (1 easiest, 4 normal, 7 hardest), reuse5 = 1 blocked the account (its play page
 * locks the board). Nothing sets the block, an admin does it in the database.
 *
 * IRREVERSIBLE in part: a rollback brings the columns back with their defaults, the data is gone.
 */
final class DropUnusedUserColumns extends AbstractMigration
{
	public function up(): void
	{
		// the difficulty came from a cookie unchecked, the site reads anything else as normal
		$this->execute('UPDATE user SET t_glicko = 4 WHERE t_glicko NOT BETWEEN 1 AND 7');
		$this->execute('UPDATE user SET reuse5 = 0 WHERE reuse5 <> 1');
		$this->execute("
			ALTER TABLE user
				DROP COLUMN lastRefresh,
				DROP COLUMN promoted,
				DROP COLUMN penalty,
				DROP COLUMN reward,
				DROP COLUMN sortOrder,
				DROP COLUMN sortColor,
				DROP COLUMN ip,
				DROP COLUMN location,
				DROP COLUMN login_token,
				DROP COLUMN readingTrial,
				DROP COLUMN reuse4,
				CHANGE t_glicko rating_mode_difficulty_selection TINYINT NOT NULL DEFAULT 4
					COMMENT 'The difficulty picked for the rating mode, 1 easiest, 4 normal, 7 hardest.',
				CHANGE reuse5 blocked TINYINT(1) NOT NULL DEFAULT 0
					COMMENT 'A blocked account cannot solve problems, for cheaters or toxic players. Set by hand in the database.'");
	}

	public function down(): void
	{
		$this->execute("
			ALTER TABLE user
				CHANGE rating_mode_difficulty_selection t_glicko INT(11) NOT NULL DEFAULT 4
					COMMENT 'Rating mode formula for calculating the changing value.',
				CHANGE blocked reuse5 INT(11) NOT NULL DEFAULT 0 COMMENT 'Locks accounts. For example cheaters or toxic people.',
				ADD COLUMN lastRefresh DATE DEFAULT NULL,
				ADD COLUMN promoted INT(11) NOT NULL DEFAULT 0 COMMENT 'How active is the user in general.',
				ADD COLUMN penalty INT(11) NOT NULL DEFAULT 0,
				ADD COLUMN reward DATETIME DEFAULT NULL COMMENT 'Shows date and time of people who donated.',
				ADD COLUMN sortOrder INT(11) NOT NULL DEFAULT 0,
				ADD COLUMN sortColor INT(11) NOT NULL DEFAULT 0,
				ADD COLUMN ip VARCHAR(40) DEFAULT NULL,
				ADD COLUMN location VARCHAR(10) DEFAULT NULL,
				ADD COLUMN login_token VARCHAR(64) DEFAULT NULL,
				ADD COLUMN readingTrial INT(11) NOT NULL DEFAULT 30,
				ADD COLUMN reuse4 INT(11) NOT NULL DEFAULT 0");
	}
}
