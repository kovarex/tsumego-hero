<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The user table is loaded on every request, these columns of it were dead:
//- lastRefresh, promoted, penalty, sortOrder, sortColor, ip, location: nothing reads or writes them,
//- reward: the date of a donation, nothing reads it since the "member since" backfill
//  (20261025100000) used it as evidence,
//- login_token: the forum signs in with the JWT auth_token cookie now,
//- readingTrial, reuse4: only the daily cron reset them, nothing read them,
//- lastProfileLeft, lastProfileRight: the chart tab of a profile is a string ('level', 'rating'...),
//  which never fit these INT columns, so they always stayed 0; the cookie alone remembers the tab.
//The columns left are renamed to snake_case, like the rest of the schema, and to names saying what
//they are: t_glicko is the difficulty a player picked for the rating mode (1 easiest, 4 normal,
//7 hardest), reuse5 = 1 blocked the account (its play page locks the board; nothing sets the block,
//an admin does it in the database), lastLight is the theme, lastHighscore the highscore page the
//menu links to. The cookies that carry these choices keep their names.
//IRREVERSIBLE in part: a rollback brings the columns back with their defaults, the data is gone.
final class CleanUpUserColumns extends AbstractMigration
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
				DROP COLUMN lastProfileLeft,
				DROP COLUMN lastProfileRight,
				CHANGE t_glicko rating_mode_difficulty_selection TINYINT NOT NULL DEFAULT 4
					COMMENT 'The difficulty picked for the rating mode, 1 easiest, 4 normal, 7 hardest.',
				CHANGE reuse5 blocked TINYINT(1) NOT NULL DEFAULT 0
					COMMENT 'A blocked account cannot solve problems, for cheaters or toxic players. Set by hand in the database.',
				CHANGE lastHighscore highscore_page INT(11) NOT NULL DEFAULT 0
					COMMENT 'The highscore page the menu links to: 1 level, 2 rating, 3 daily, 4 time mode, 0 none yet.',
				CHANGE lastLight theme TINYINT NOT NULL DEFAULT 0 COMMENT '0=light, 1=dark',
				CHANGE levelBar level_bar TINYINT NOT NULL DEFAULT 1 COMMENT 'What the bar in the header shows: 1=level, 2=rating',
				CHANGE passwordreset password_reset_token VARCHAR(50) NULL DEFAULT NULL,
				CHANGE passwordreset_created password_reset_token_created DATETIME NULL DEFAULT NULL");
	}

	public function down(): void
	{
		$this->execute("
			ALTER TABLE user
				CHANGE rating_mode_difficulty_selection t_glicko INT(11) NOT NULL DEFAULT 4
					COMMENT 'Rating mode formula for calculating the changing value.',
				CHANGE blocked reuse5 INT(11) NOT NULL DEFAULT 0 COMMENT 'Locks accounts. For example cheaters or toxic people.',
				CHANGE highscore_page lastHighscore INT(11) NOT NULL DEFAULT 0 COMMENT 'Last used highscore page.',
				CHANGE theme lastLight TINYINT NOT NULL DEFAULT 0 COMMENT '0=light, 1=dark',
				CHANGE level_bar levelBar TINYINT NOT NULL DEFAULT 1 COMMENT '1=level, 2=rating',
				CHANGE password_reset_token passwordreset VARCHAR(50) NULL DEFAULT NULL,
				CHANGE password_reset_token_created passwordreset_created DATETIME NULL DEFAULT NULL,
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
				ADD COLUMN reuse4 INT(11) NOT NULL DEFAULT 0,
				ADD COLUMN lastProfileLeft INT(11) NOT NULL DEFAULT 0,
				ADD COLUMN lastProfileRight INT(11) NOT NULL DEFAULT 0");
	}
}
