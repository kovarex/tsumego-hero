<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The achievements of the daily streak (AchievementChecker::checkDailyStreakAchievements): 7, 14, 30, 182
//and 365 days in a row. A player whose streak is already that long gets them on the next page load.
final class AddDailyStreakAchievements extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT INTO achievement (id, name, description, image, color, `order`, xp) VALUES
			(116, 'Kindling', 'Reach a daily streak of 7 days.', 'ac116', 'achievementColorOrange', 140, 500),
			(117, 'Steady Flame', 'Reach a daily streak of 14 days.', 'ac117', 'achievementColorOrange', 141, 1000),
			(118, 'Burning Bright', 'Reach a daily streak of 30 days.', 'ac118', 'achievementColorOrange', 142, 2500),
			(119, 'Wildfire', 'Reach a daily streak of 182 days.', 'ac119', 'achievementColorOrange', 143, 10000),
			(120, 'Eternal Flame', 'Reach a daily streak of 365 days.', 'ac120', 'achievementColorOrange', 144, 25000)");
	}

	public function down(): void
	{
		$this->execute('DELETE FROM achievement WHERE id BETWEEN 116 AND 120');
	}
}
