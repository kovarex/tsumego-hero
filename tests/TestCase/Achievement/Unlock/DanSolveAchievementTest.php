<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/**
 * Test Dan Solve Achievements (IDs 101-110)
 *
 * These achievements unlock when solving dan-level problems:
 * - Achievement 101: Solve 1 x 1d problem
 * - Achievement 102: Solve 1 x 2d problem
 * - Achievement 103: Solve 1 x 3d problem
 * - Achievement 104: Solve 1 x 4d problem
 * - Achievement 105: Solve 1 x 5d problem
 * - Achievement 106: Solve 10 x 1d problems
 * - Achievement 107: Solve 10 x 2d problems
 * - Achievement 108: Solve 10 x 3d problems
 * - Achievement 109: Solve 10 x 4d problems
 * - Achievement 110: Solve 10 x 5d problems
 *
 * These use achievement_condition with categories: danSolve1d, danSolve2d, etc.
 */
class DanSolveAchievementTest extends AchievementTestCase
{
	/**
	 * Test single solve achievements (IDs 101-105)
	 */
	public function testSingleDanSolveAchievements()
	{
		$achievements = [
			Achievement::SOLVE_1D => '1d',
			Achievement::SOLVE_2D => '2d',
			Achievement::SOLVE_3D => '3d',
			Achievement::SOLVE_4D => '4d',
			Achievement::SOLVE_5D => '5d',
		];

		foreach ($achievements as $achievementId => $danLevel)
		{
			// Arrange: Create user with dan solve condition
			$context = new ContextPreparator([
				'user' => ['name' => "user_$achievementId"],
				'achievement-conditions' => [
					['category' => "danSolve$danLevel", 'value' => 1]
				]
			]);

			// Act: Trigger check
			AppController::checkDanSolveAchievements();

			// Assert: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock after solving 1x $danLevel problem"
			);
		}
	}

	/**
	 * Test 10-solve achievements (IDs 106-110)
	 */
	public function testTenDanSolveAchievements()
	{
		$achievements = [
			Achievement::SOLVE_10_1D => '1d',
			Achievement::SOLVE_10_2D => '2d',
			Achievement::SOLVE_10_3D => '3d',
			Achievement::SOLVE_10_4D => '4d',
			Achievement::SOLVE_10_5D => '5d',
		];

		foreach ($achievements as $achievementId => $danLevel)
		{
			// Arrange: Create user with dan solve condition
			$context = new ContextPreparator([
				'user' => ['name' => "user_$achievementId"],
				'achievement-conditions' => [
					['category' => "danSolve$danLevel", 'value' => 10]
				]
			]);

			// Act: Trigger check
			AppController::checkDanSolveAchievements();

			// Assert: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock after solving 10x $danLevel problems"
			);
		}
	}

	/**
	 * Test boundary: 9 solves should NOT unlock 10-solve achievement
	 */
	public function testNineSolvesDoesNotUnlockTenSolveAchievement()
	{
		// Arrange: Create user with 9 1d solves
		$context = new ContextPreparator([
			'user' => ['name' => 'boundary_user'],
			'achievement-conditions' => [
				['category' => 'danSolve1d', 'value' => 9]
			]
		]);

		// Act: Trigger check
		AppController::checkDanSolveAchievements();

		// Assert: Achievement 106 (10x 1d) should NOT be unlocked
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::SOLVE_10_1D);
	}
}
