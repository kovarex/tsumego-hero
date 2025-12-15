<?php

App::uses('Achievement', 'Model');

/**
 * No Error Streak Achievement Test
 *
 * Tests achievements IDs 53-58 for completing streaks without errors
 * using achievement_condition table with category='err'
 */
class NoErrorStreakAchievementTest extends AchievementTestCase
{
	/**
	 * Test single No Error Streak achievements
	 */
	public function testSingleNoErrorStreakAchievements()
	{
		// Arrange: Create user and set err=10 (just meets threshold for achievement 53)
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'achievement-conditions' => [
				['category' => 'err', 'value' => 10]
			]
		]);

		// Act: Trigger achievement check
		AppController::checkNoErrorAchievements();

		// Assert: Achievement 53 should be unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");

		// Assert: Higher achievements should NOT be unlocked yet
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_II);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_III);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_IV);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_V);
	}

	/**
	 * Test 200 streak unlocks all achievements
	 */
	public function testTwoHundredStreakUnlocksAll()
	{
		// Arrange: Create user and set err=200 (meets all thresholds)
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'achievement-conditions' => [
				['category' => 'err', 'value' => 200]
			]
		]);

		// Act: Trigger achievement check
		AppController::checkNoErrorAchievements();

		// Assert: All 6 achievements should be unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_I, "No Error Streak I");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_II, "No Error Streak II");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_III, "No Error Streak III");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_IV, "No Error Streak IV");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_V, "No Error Streak V");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_VI, "No Error Streak VI");
	}

	/**
	 * Test all No Error Streak thresholds
	 */
	public function testAllNoErrorStreakAchievements()
	{
		$thresholds = [
			Achievement::NO_ERROR_STREAK_I => 10,
			Achievement::NO_ERROR_STREAK_II => 20,
			Achievement::NO_ERROR_STREAK_III => 30,
			Achievement::NO_ERROR_STREAK_IV => 50,
			Achievement::NO_ERROR_STREAK_V => 100,
			Achievement::NO_ERROR_STREAK_VI => 200,
		];

		foreach ($thresholds as $achievementId => $errValue)
		{
			// Arrange: Fresh context for each test
			$context = new ContextPreparator([
				'user' => ['name' => "testuser_$achievementId"],
				'achievement-conditions' => [
					['category' => 'err', 'value' => $errValue]
				]
			]);

			// Act: Trigger achievement check
			AppController::checkNoErrorAchievements();

			// Assert: This achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock at err=$errValue"
			);
		}
	}
}
