<?php

use App\Utility\AchievementId;

/**
 * Test AchievementId::USER_OF_THE_DAY
 *
 * This is a special achievement awarded by the system (probably via a cron job)
 * when a user becomes the most active user of the day. The achievement is checked
 * via achievement_condition with category='uotd'.
 */
class UserOfTheDayAchievementTest extends AchievementTestCase
{
	public function testUserOfTheDayAchievement()
	{
		new ContextPreparator(['achievement-conditions' => [['category' => 'uotd', 'value' => 1]]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(AchievementId::USER_OF_THE_DAY);
	}

	public function testUserOfTheDayDoesNotUnlockWithoutCondition()
	{
		new ContextPreparator();
		$this->triggerAchievementCheck();
		$this->assertAchievementNotUnlocked(AchievementId::USER_OF_THE_DAY);
	}
}
