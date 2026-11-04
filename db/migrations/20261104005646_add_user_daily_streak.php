<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The daily streak (App\Utility\DailyStreak): the days in a row with a solve that gave XP, kept as it
//was at the last day that counted, and the player's time zone, whose day ends at 3 AM local time.
//Every player starts at 0.
final class AddUserDailyStreak extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user
			ADD COLUMN streak_length INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row with a solve that gave XP, as of streak_last_day',
			ADD COLUMN streak_best INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest streak_length the player reached',
			ADD COLUMN streak_freezes TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Freezes held, each covers one missed day',
			ADD COLUMN streak_last_day DATE NULL DEFAULT NULL COMMENT 'The last local day that counted for the streak, or was covered by a freeze',
			ADD COLUMN time_zone VARCHAR(64) NULL DEFAULT NULL COMMENT 'IANA time zone from the browser; NULL is UTC'");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user DROP COLUMN streak_length, DROP COLUMN streak_best, DROP COLUMN streak_freezes, DROP COLUMN streak_last_day, DROP COLUMN time_zone');
	}
}
