<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/**
 * Test Collection Sets Achievements (IDs 47-52)
 *
 * These achievements unlock when user completes specific numbers of collections:
 * - Achievement 47: 10 collections
 * - Achievement 48: 20 collections
 * - Achievement 49: 30 collections
 * - Achievement 50: 40 collections
 * - Achievement 51: 50 collections
 * - Achievement 52: 60 collections */
class CollectionSetsAchievementTest extends AchievementTestCase
{
	public function testAchievementCompleteSets1UnlocksAt10Sets()
	{
		$context = new ContextPreparator();
		$this->triggerSetCompletionAchievementCheck(Achievement::COMPLETE_SETS_I_SETS_COUNT);
		$this->assertAchievementUnlocked(Achievement::COMPLETE_SETS_I);
	}

	public function testAchievementCompleteSets1DoesNotUnlockBelow10Sets()
	{
		$context = new ContextPreparator();
		$this->triggerSetCompletionAchievementCheck(Achievement::COMPLETE_SETS_I_SETS_COUNT - 1);
		$this->assertAchievementNotUnlocked(Achievement::COMPLETE_SETS_I);
	}

	public function testAllCollectionSetsAchievements()
	{
		$achievementSets = [
			Achievement::COMPLETE_SETS_I => Achievement::COMPLETE_SETS_I_SETS_COUNT,
			Achievement::COMPLETE_SETS_II => Achievement::COMPLETE_SETS_II_SETS_COUNT,
			Achievement::COMPLETE_SETS_III => Achievement::COMPLETE_SETS_III_SETS_COUNT,
			Achievement::COMPLETE_SETS_IV => Achievement::COMPLETE_SETS_IV_SETS_COUNT,
			Achievement::COMPLETE_SETS_V => Achievement::COMPLETE_SETS_V_SETS_COUNT,
			Achievement::COMPLETE_SETS_VI => Achievement::COMPLETE_SETS_VI_SETS_COUNT,
		];

		foreach ($achievementSets as $achievementId => $requiredSets)
		{
			$context = new ContextPreparator();
			$this->triggerSetCompletionAchievementCheck($requiredSets);
			$this->assertAchievementUnlocked($achievementId, "Achievement $achievementId should unlock at $requiredSets completed sets");
		}
	}
}
