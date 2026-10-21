<?php

use App\Utility\AchievementId;

class SolvedCountAchievementTest extends AchievementTestCase
{
	public function testAchievement1UnlocksAt1000Solved()
	{
		new ContextPreparator(['user' => ['solved' => 1000]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(AchievementId::PROBLEMS_1000);
	}

	public function testAchievement1DoesNotUnlockBelow1000()
	{
		new ContextPreparator(['user' => ['solved' => 999]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementNotUnlocked(AchievementId::PROBLEMS_1000);
	}

	public function testAllSolvedCountAchievements()
	{
		$thresholds = [
			[AchievementId::PROBLEMS_1000, 1000],
			[AchievementId::PROBLEMS_2000, 2000],
			[AchievementId::PROBLEMS_3000, 3000],
			[AchievementId::PROBLEMS_4000, 4000],
			[AchievementId::PROBLEMS_5000, 5000],
			[AchievementId::PROBLEMS_6000, 6000],
			[AchievementId::PROBLEMS_7000, 7000],
			[AchievementId::PROBLEMS_8000, 8000],
			[AchievementId::PROBLEMS_9000, 9000],
			[AchievementId::PROBLEMS_10000, 10000]
		];

		foreach ($thresholds as [$achievementId, $solvedCount])
		{
			new ContextPreparator(['user' => ['solved' => $solvedCount]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId);
		}
	}
}
