<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/* These achievements unlock when solving dan-level problems:
 * - Achievement::SOLVE_1D: Solve 1 x 1d problem
 * - Achievement::SOLVE_2D: Solve 1 x 2d problem
 * - Achievement::SOLVE_3D: Solve 1 x 3d problem
 * - Achievement::SOLVE_4D: Solve 1 x 4d problem
 * - Achievement::SOLVE_5D: Solve 1 x 5d problem
 * - Achievement::SOLVE_10_1D: Solve 10 x 1d problems
 * - Achievement::SOLVE_10_2D: Solve 10 x 2d problems
 * - Achievement::SOLVE_10_3D: Solve 10 x 3d problems
 * - Achievement::SOLVE_10_4D: Solve 10 x 4d problems
 * - Achievement::SOLVE_10_5D: Solve 10 x 5d problems */
class DanSolveAchievementTest extends AchievementTestCase
{
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
			$context = new ContextPreparator([
				'user' => ['name' => "user_$achievementId"],
				'achievement-conditions' => [['category' => "danSolve$danLevel", 'value' => 1]]]);
			new AchievementChecker()->checkDanSolveAchievements();
			$this->assertAchievementUnlocked($achievementId);
		}
	}

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
			$context = new ContextPreparator(['achievement-conditions' => [['category' => "danSolve$danLevel", 'value' => 10]]]);
			new AchievementChecker()->checkDanSolveAchievements();
			$this->assertAchievementUnlocked($achievementId);
		}
	}

	public function testNineSolvesDoesNotUnlockTenSolveAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'danSolve1d', 'value' => 9]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementNotUnlocked( Achievement::SOLVE_10_1D);
	}
}
