<?php
App::uses('Achievement', 'Model');

class SolvedCountAchievementTest extends AchievementTestCase
{
	public function testAchievement1UnlocksAt1000Solved()
	{
		new ContextPreparator(['user' => ['solved' => 1000]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000);
	}

	public function testAchievement1DoesNotUnlockBelow1000()
	{
		new ContextPreparator(['user' => ['solved' => 999]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementNotUnlocked(Achievement::PROBLEMS_1000);
	}

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
			new ContextPreparator(['user' => ['solved' => $solvedCount]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId);
		}
	}
}
