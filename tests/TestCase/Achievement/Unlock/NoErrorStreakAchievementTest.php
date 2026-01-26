<?php

App::uses('Achievement', 'Model');
App::uses('AchievementChecker', 'Utility');

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
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => Achievement::NO_ERROR_STREAK_I_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");

		// Assert: Higher achievements should NOT be unlocked yet
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_II);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_III);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_IV);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_V);
	}

	public function testTwoHundredStreakUnlocksAll()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => Achievement::NO_ERROR_STREAK_VI_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		// Assert: All 6 achievements should be unlocked
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_I, "No Error Streak I");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_II, "No Error Streak II");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_III, "No Error Streak III");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_IV, "No Error Streak IV");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_V, "No Error Streak V");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_VI, "No Error Streak VI");
	}

	public function testAllNoErrorStreakAchievements()
	{
		$thresholds = [
			Achievement::NO_ERROR_STREAK_I => Achievement::NO_ERROR_STREAK_I_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_II => Achievement::NO_ERROR_STREAK_II_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_III => Achievement::NO_ERROR_STREAK_III_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_IV => Achievement::NO_ERROR_STREAK_IV_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_V => Achievement::NO_ERROR_STREAK_V_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_VI => Achievement::NO_ERROR_STREAK_VI_STREAK_COUNT,
		];

		foreach ($thresholds as $achievementId => $errValue)
		{
			$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => $errValue]]]);
			new AchievementChecker()->checkNoErrorAchievements();
			$this->assertAchievementUnlocked($achievementId, "Achievement $achievementId should unlock at err=$errValue");
		}
	}
}
