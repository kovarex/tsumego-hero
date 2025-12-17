<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/**
 * Test Level Achievements (IDs 36-45)
 *
 * These achievements unlock when user reaches specific level thresholds:
 * - Achievement 36: Level 10
 * - Achievement 37: Level 20
 * - Achievement 38: Level 30
 * - Achievement 39: Level 40
 * - Achievement 40: Level 50
 * - Achievement 41: Level 60
 * - Achievement 42: Level 70
 * - Achievement 43: Level 80
 * - Achievement 44: Level 90
 * - Achievement 45: Level 100
 */
class LevelAchievementTest extends AchievementTestCase
{
	// Test that Achievement::LEVEL_UP unlocks at level 10
	public function testAchievement_LEVEL_UP_UnlocksAtLevel10()
	{
		foreach ([10, 9] as $level)
		{
			$context = new ContextPreparator(['user' => ['level' => $level]]);
			$this->triggerAchievementCheck();
			if ($level >= 10)
				$this->assertAchievementUnlocked(Achievement::LEVEL_UP);
			else
				$this->assertAchievementNotUnlocked(Achievement::LEVEL_UP);
		}
	}

	/**
	 * Test all level achievements unlock at correct thresholds
	 */
	public function testAllLevelAchievements()
	{
		$achievementLevels = [
			Achievement::LEVEL_UP => 10,
			Achievement::FIRST_HERO_POWER => 20,
			Achievement::UPGRADED_INTUITION => 30,
			Achievement::MORE_POWER => 40,
			Achievement::HALF_WAY_TO_TOP => 50,
			Achievement::CONGRATS_MORE_PROBLEMS => 60,
			Achievement::NICE_LEVEL => 70,
			Achievement::DID_LOT_OF_TSUMEGO => 80,
			Achievement::STILL_DOING_TSUMEGO => 90,
			Achievement::THE_TOP => 100,
		];

		foreach ($achievementLevels as $achievementId => $requiredLevel)
		{
			// Arrange: Create user with required level
			$context = new ContextPreparator([
				'user' => ['level' => $requiredLevel],
			]);

			// Act: Trigger achievement check
			$this->triggerAchievementCheck();

			// Assert: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock at level $requiredLevel"
			);
		}
	}

	// Test premium Achievement::PREMIUM
	public function testPremiumAchievementUnlocksForPremiumUser()
	{
		$context = new ContextPreparator(['user' => ['premium' => 1, 'level' => 1]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::PREMIUM, "Premium achievement should unlock for premium users");
	}

	/**
	 * Test premium achievement does NOT unlock for non-premium user
	 */
	public function testPremiumAchievementDoesNotUnlockForNonPremiumUser()
	{
		// Arrange: Create non-premium user
		$context = new ContextPreparator([
			'user' => ['premium' => 0, 'level' => 1],
		]);

		// Act: Trigger achievement check
		$this->triggerAchievementCheck();

		// Assert: Achievement 100 should NOT be unlocked
		$this->assertAchievementNotUnlocked( Achievement::PREMIUM);
	}
}
