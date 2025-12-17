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
	public function testSingleNoErrorStreakAchievements()
	{
		// Arrange: Create user and set err=10 (just meets threshold for Achievement::NO_ERROR_STREAK_I)
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => 10]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");

		// Assert: Higher achievements should NOT be unlocked yet
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_II);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_III);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_IV);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_V);
	}

	public function testTwoHundredStreakUnlocksAll()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => 200]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		// Assert: All 6 achievements should be unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_I, "No Error Streak I");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_II, "No Error Streak II");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_III, "No Error Streak III");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_IV, "No Error Streak IV");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_V, "No Error Streak V");
		$this->assertAchievementUnlocked($context->user['id'], Achievement::NO_ERROR_STREAK_VI, "No Error Streak VI");
	}

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
			$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => $errValue]]]);
			new AchievementChecker()->checkNoErrorAchievements();
			$this->assertAchievementUnlocked($context->user['id'], $achievementId, "Achievement $achievementId should unlock at err=$errValue");
		}
	}
}
