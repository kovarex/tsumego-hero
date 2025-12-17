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
		$this->triggerSetCompletionAchievementCheck($context->user['id'], 10);
		$this->assertAchievementUnlocked($context->user['id'], Achievement::COMPLETE_SETS_I);
	}

	public function testAchievementCompleteSets1DoesNotUnlockBelow10Sets()
	{
		$context = new ContextPreparator();
		$this->triggerSetCompletionAchievementCheck($context->user['id'], 9);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::COMPLETE_SETS_I);
	}

	public function testAllCollectionSetsAchievements()
	{
		$achievementSets = [
			Achievement::COMPLETE_SETS_I => 10,
			Achievement::COMPLETE_SETS_II => 20,
			Achievement::COMPLETE_SETS_III => 30,
			Achievement::COMPLETE_SETS_IV => 40,
			Achievement::COMPLETE_SETS_V => 50,
			Achievement::COMPLETE_SETS_VI => 60,
		];

		foreach ($achievementSets as $achievementId => $requiredSets)
		{
			$context = new ContextPreparator();
			$this->triggerSetCompletionAchievementCheck($context->user['id'], $requiredSets);
			$this->assertAchievementUnlocked($context->user['id'], $achievementId, "Achievement $achievementId should unlock at $requiredSets completed sets");
		}
	}
}
