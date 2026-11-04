<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The daily streak (App\Utility\DailyStreak): the days in a row on which the player did what the day
//asks, kept as it was at the last day that counted, what today did so far, and the player's time zone,
//whose day ends at 3 AM local time. Every player starts at 0.
final class AddUserDailyStreak extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user
			ADD COLUMN streak_length INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row that did what the day asks, as of streak_last_day',
			ADD COLUMN streak_best INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest streak_length the player reached',
			ADD COLUMN streak_freezes TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Freezes held, each covers one missed day',
			ADD COLUMN streak_last_day DATE NULL DEFAULT NULL COMMENT 'The last local day that counted for the streak, or was covered by a freeze',
			ADD COLUMN time_zone VARCHAR(64) NULL DEFAULT NULL COMMENT 'IANA time zone from the browser; NULL is UTC',
			ADD COLUMN streak_day DATE NULL DEFAULT NULL COMMENT 'The local day the streak_day_ counters are of',
			ADD COLUMN streak_day_xp INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The XP the solves of streak_day gave',
			ADD COLUMN streak_day_reviews INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The mistake training reviews solved on streak_day',
			ADD COLUMN streak_day_reviews_required TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'The reviews streak_day asks, NULL until its first result worked it out'");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user DROP COLUMN streak_length, DROP COLUMN streak_best, DROP COLUMN streak_freezes, DROP COLUMN streak_last_day, DROP COLUMN time_zone,
			DROP COLUMN streak_day, DROP COLUMN streak_day_xp, DROP COLUMN streak_day_reviews, DROP COLUMN streak_day_reviews_required');
	}
}
