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
 * - Achievement 52: 60 collections
 */
class CollectionSetsAchievementTest extends AchievementTestCase
{
	/**
	 * Test that achievement 47 unlocks at 10 completed sets
	 */
	public function testAchievement47UnlocksAt10Sets()
	{
		// Arrange: Create user with default settings
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'], // Need at least one field
		]);

		// Act: Trigger set completion achievement check with 10 sets
		$this->triggerSetCompletionAchievementCheck($context->user['id'], 10);

		// Assert: Achievement 47 should be unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::COMPLETE_SETS_I);
	}

	/**
	 * Test that achievement 47 does NOT unlock below 10 sets
	 */
	public function testAchievement47DoesNotUnlockBelow10Sets()
	{
		// Arrange: Create user with default settings
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser2'],
		]);

		// Act: Trigger set completion achievement check with 9 sets
		$this->triggerSetCompletionAchievementCheck($context->user['id'], 9);

		// Assert: Achievement 47 should NOT be unlocked
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::COMPLETE_SETS_I);
	}

	/**
	 * Test all collection sets achievements unlock at correct thresholds
	 */
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
			// Arrange: Create user with default settings
			$context = new ContextPreparator([
				'user' => ['name' => "user_$achievementId"],  // Unique name per iteration
			]);

			// Act: Trigger set completion achievement check
			$this->triggerSetCompletionAchievementCheck($context->user['id'], $requiredSets);

			// Assert: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock at $requiredSets completed sets"
			);
		}
	}
}
