<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The golden streak (App\Utility\DailyStreak), the hardcore mode of the daily streak a player picks in
//the settings (streak_mode_setting): its own length and best, and what today did towards its
//requirement, counted per local day of the player. Every player starts in the normal mode.
final class AddUserGoldenStreak extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE streak_mode_setting (
			id int(10) unsigned NOT NULL,
			name varchar(30) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute("INSERT INTO streak_mode_setting (id, name) VALUES (1, 'normal'), (2, 'golden')");
		$this->execute("ALTER TABLE user
			ADD COLUMN streak_mode_setting_id int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'The mode of the daily streak the player plays: normal or golden',
			ADD COLUMN golden_streak_length INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row counted in the golden mode, as of streak_last_day',
			ADD COLUMN golden_streak_best INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest golden_streak_length the player reached',
			ADD COLUMN streak_day DATE NULL DEFAULT NULL COMMENT 'The local day the streak_day_ counters are of',
			ADD COLUMN streak_day_xp INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The XP the solves of streak_day gave',
			ADD COLUMN streak_day_reviews INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The mistake training reviews solved on streak_day',
			ADD COLUMN streak_day_reviews_required TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'The reviews the golden mode asked of streak_day, NULL until worked out',
			ADD KEY user_streak_mode_setting_id (streak_mode_setting_id),
			ADD CONSTRAINT user_streak_mode_setting_id FOREIGN KEY (streak_mode_setting_id) REFERENCES streak_mode_setting (id) ON UPDATE CASCADE");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user DROP FOREIGN KEY user_streak_mode_setting_id, DROP KEY user_streak_mode_setting_id,
			DROP COLUMN streak_mode_setting_id, DROP COLUMN golden_streak_length, DROP COLUMN golden_streak_best, DROP COLUMN streak_day,
			DROP COLUMN streak_day_xp, DROP COLUMN streak_day_reviews, DROP COLUMN streak_day_reviews_required');
		$this->execute('DROP TABLE streak_mode_setting');
	}
}
