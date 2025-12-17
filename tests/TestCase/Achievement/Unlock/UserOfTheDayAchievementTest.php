<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

/**
 * Test "User of the Day" achievement (ID 11)
 *
 * This is a special achievement awarded by the system (probably via a cron job)
 * when a user becomes the most active user of the day. The achievement is checked
 * via achievement_condition with category='uotd'.
 */
class UserOfTheDayAchievementTest extends AchievementTestCase
{
	/**
	 * Test that User of the Day achievement unlocks when uotd condition exists
	 */
	public function testUserOfTheDayAchievement()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'uotd', 'value' => 1]
			]
		]);

		// Trigger achievement check (happens on login/page load)
		$this->triggerAchievementCheck();

		// Assert achievement 11 unlocked
		$this->assertAchievementUnlocked(
			$context->user['id'],
			Achievement::USER_OF_THE_DAY,
			'User of the Day achievement should unlock when uotd condition exists'
		);
	}

	/**
	 * Test that User of the Day does NOT unlock without uotd condition
	 */
	public function testUserOfTheDayDoesNotUnlockWithoutCondition()
	{
		$context = new ContextPreparator([]);

		// Trigger achievement check WITHOUT creating uotd condition
		$this->triggerAchievementCheck();

		// Assert achievement 11 NOT unlocked
		$this->assertAchievementNotUnlocked( Achievement::USER_OF_THE_DAY);
	}
}
