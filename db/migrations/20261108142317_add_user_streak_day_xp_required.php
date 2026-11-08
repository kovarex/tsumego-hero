<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The XP the day of the daily streak asks (App\Utility\DailyStreak), fixed by the day's first result like
//the reviews it asks, so the solves that raise the rating during the day don't raise it.
final class AddUserStreakDayXpRequired extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user
			ADD COLUMN streak_day_xp_required INT UNSIGNED NULL DEFAULT NULL COMMENT 'The XP streak_day asks, NULL until its first result fixed it'
			AFTER streak_day_reviews_required");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user DROP COLUMN streak_day_xp_required');
	}
}
