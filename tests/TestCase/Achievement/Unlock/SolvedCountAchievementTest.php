<?php

App::uses('Achievement', 'Model');

/**
 * Test solved count achievements (IDs 1-10)
 *
 * Achievement mapping:
 * - ID 1: 1000 problems solved
 * - ID 2: 2000 problems solved
 * - ID 3: 3000 problems solved
 * - ID 4: 4000 problems solved
 * - ID 5: 5000 problems solved
 * - ID 6: 6000 problems solved
 * - ID 7: 7000 problems solved
 * - ID 8: 8000 problems solved
 * - ID 9: 9000 problems solved
 * - ID 10: 10000 problems solved
 *
 * TESTING APPROACH:
 * - Setup: Create user with specific 'solved' count using ContextPreparator
 * - Exercise: Trigger achievement check
 * - Verify: Check achievement_status table for unlocked achievement
 *
 */
class SolvedCountAchievementTest extends AchievementTestCase
{
	/**
	 * Test that achievement 1 unlocks at 1000 solved problems
	 */
	public function testAchievement1UnlocksAt1000Solved()
	{
		// Setup: Create user with 1000 solved problems
		$context = new ContextPreparator([
			'user' => ['solved' => 1000]
		]);

		// Exercise: Trigger achievement check
		$this->triggerAchievementCheck();

		// Verify: Achievement 1 should be unlocked
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000);
	}

	/**
	 * Test boundary: 999 solved should NOT unlock achievement 1
	 */
	public function testAchievement1DoesNotUnlockBelow1000()
	{
		// Setup: Create user with 999 solved problems
		$context = new ContextPreparator([
			'user' => ['solved' => 999]
		]);

		// Exercise: Trigger achievement check
		$this->triggerAchievementCheck();

		// Verify: Achievement 1 should NOT be unlocked
		$this->assertAchievementNotUnlocked( Achievement::PROBLEMS_1000);
	}

	/**
	 * Test all solved count achievements unlock at correct thresholds
	 */
	public function testAllSolvedCountAchievements()
	{
		$thresholds = [
			Achievement::PROBLEMS_1000 => 1000,
			Achievement::PROBLEMS_2000 => 2000,
			Achievement::PROBLEMS_3000 => 3000,
			Achievement::PROBLEMS_4000 => 4000,
			Achievement::PROBLEMS_5000 => 5000,
			Achievement::PROBLEMS_6000 => 6000,
			Achievement::PROBLEMS_7000 => 7000,
			Achievement::PROBLEMS_8000 => 8000,
			Achievement::PROBLEMS_9000 => 9000,
			Achievement::PROBLEMS_10000 => 10000
		];

		foreach ($thresholds as $achievementId => $solvedCount)
		{
			// Setup: Create user with specific solved count
			$context = new ContextPreparator([
				'user' => ['solved' => $solvedCount]
			]);

			// Exercise: Trigger achievement check
			$this->triggerAchievementCheck();

			// Verify: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId
			);
		}
	}
}
