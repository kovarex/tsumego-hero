<?php

use App\Utility\AchievementId;
/* These achievements unlock when solving dan-level problems:
 * - AchievementId::SOLVE_1D: Solve 1 x 1d problem
 * - AchievementId::SOLVE_2D: Solve 1 x 2d problem
 * - AchievementId::SOLVE_3D: Solve 1 x 3d problem
 * - AchievementId::SOLVE_4D: Solve 1 x 4d problem
 * - AchievementId::SOLVE_5D: Solve 1 x 5d problem
 * - AchievementId::SOLVE_10_1D: Solve 10 x 1d problems
 * - AchievementId::SOLVE_10_2D: Solve 10 x 2d problems
 * - AchievementId::SOLVE_10_3D: Solve 10 x 3d problems
 * - AchievementId::SOLVE_10_4D: Solve 10 x 4d problems
 * - AchievementId::SOLVE_10_5D: Solve 10 x 5d problems */
use App\Utility\AchievementChecker;

class DanSolveAchievementTest extends AchievementTestCase
{
	public function testSingleDanSolveAchievements()
	{
		$achievements = [
			[AchievementId::SOLVE_1D, '1d'],
			[AchievementId::SOLVE_2D, '2d'],
			[AchievementId::SOLVE_3D, '3d'],
			[AchievementId::SOLVE_4D, '4d'],
			[AchievementId::SOLVE_5D, '5d'],
		];

		foreach ($achievements as [$achievementId, $danLevel])
		{
			$context = new ContextPreparator([
				'user' => ['name' => "user_{$achievementId->name}"],
				'achievement-conditions' => [['category' => "danSolve$danLevel", 'value' => 1]]]);
			new AchievementChecker()->checkDanSolveAchievements();
			$this->assertAchievementUnlocked($achievementId);
		}
	}

	public function testTenDanSolveAchievements()
	{
		$achievements = [
			[AchievementId::SOLVE_10_1D, '1d'],
			[AchievementId::SOLVE_10_2D, '2d'],
			[AchievementId::SOLVE_10_3D, '3d'],
			[AchievementId::SOLVE_10_4D, '4d'],
			[AchievementId::SOLVE_10_5D, '5d'],
		];

		foreach ($achievements as [$achievementId, $danLevel])
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
		$this->assertAchievementNotUnlocked(AchievementId::SOLVE_10_1D);
	}
}
