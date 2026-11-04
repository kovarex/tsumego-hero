<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The daily streak (App\Utility\DailyStreak): the days in a row on which the player did what the day
//asks, kept as it was at the last day that counted, what today did so far, and the player's time zone,
//whose day ends at 3 AM local time. Every player starts at 0.
//It replaces two migrations that ran only on the test server (20261104005646, 20261104183235), which
//also made a streak mode setting and a golden streak that are gone; there it drops those, keeps the
//streak columns with their values, and forgets the two old versions.
final class AddUserDailyStreak extends AbstractMigration
{
	private const array COLUMNS = [
		'streak_length' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row that did what the day asks, as of streak_last_day'",
		'streak_best' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest streak_length the player reached'",
		'streak_freezes' => "TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Freezes held, each covers one missed day'",
		'streak_last_day' => "DATE NULL DEFAULT NULL COMMENT 'The last local day that counted for the streak, or was covered by a freeze'",
		'time_zone' => "VARCHAR(64) NULL DEFAULT NULL COMMENT 'IANA time zone from the browser; NULL is UTC'",
		'streak_day' => "DATE NULL DEFAULT NULL COMMENT 'The local day the streak_day_ counters are of'",
		'streak_day_xp' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The XP the solves of streak_day gave'",
		'streak_day_reviews' => "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The mistake training reviews solved on streak_day'",
		'streak_day_reviews_required' => "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'The reviews streak_day asks, NULL until its first result worked it out'",
	];

	public function up(): void
	{
		if (!$this->table('user')->hasColumn('streak_length'))
		{
			$this->execute('ALTER TABLE user ' . implode(', ', array_map(fn($name) => 'ADD COLUMN ' . $name . ' ' . self::COLUMNS[$name], array_keys(self::COLUMNS))));
			return;
		}
		$this->execute('ALTER TABLE user DROP FOREIGN KEY IF EXISTS user_streak_mode_setting_id, DROP KEY IF EXISTS user_streak_mode_setting_id,
			DROP COLUMN IF EXISTS streak_mode_setting_id, DROP COLUMN IF EXISTS golden_streak_length, DROP COLUMN IF EXISTS golden_streak_best');
		$this->execute('DROP TABLE IF EXISTS streak_mode_setting');
		$this->execute('ALTER TABLE user ' . implode(', ', array_map(fn($name) => 'ADD COLUMN IF NOT EXISTS ' . $name . ' ' . self::COLUMNS[$name], array_keys(self::COLUMNS))));
		$this->execute('ALTER TABLE user ' . implode(', ', array_map(fn($name) => 'MODIFY COLUMN ' . $name . ' ' . self::COLUMNS[$name], array_keys(self::COLUMNS))));
		$this->execute('DELETE FROM phinxlog WHERE version IN (20261104005646, 20261104183235)');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user ' . implode(', ', array_map(fn($name) => 'DROP COLUMN ' . $name, array_keys(self::COLUMNS))));
	}
}
