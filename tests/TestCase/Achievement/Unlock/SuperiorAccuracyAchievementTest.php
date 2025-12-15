<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

/**
 * Test Superior Accuracy achievement (ID 46)
 *
 * "Finish a collection with 100% accuracy"
 * Requires: set with 100+ tsumegos, 100% accuracy (acA.value >= 100)
 */
class SuperiorAccuracyAchievementTest extends AchievementTestCase
{
	/**
	 * Test that Superior Accuracy unlocks at 100% accuracy on 100+ tsumego set
	 */
	public function testSuperiorAccuracyAchievement()
	{
		$context = new ContextPreparator([]);

		// Create set with 100 tsumegos + SetConnection records
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 100% accuracy condition
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'AchievementCondition' => [
				'user_id' => $context->user['id'],
				'set_id' => $setId,
				'category' => '%',
				'value' => 100
			]
		]);

		// Trigger check
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkSetAchievements($setId);

		// Assert achievement unlocked
		$this->assertAchievementUnlocked(
			$context->user['id'],
			Achievement::SUPERIOR_ACCURACY,
			'Superior Accuracy (100%) should unlock'
		);
	}

	/**
	 * Test that Superior Accuracy does NOT unlock below 100% accuracy
	 */
	public function testSuperiorAccuracyDoesNotUnlockBelow100Percent()
	{
		$context = new ContextPreparator([]);

		// Create set with 100 tsumegos
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 99% accuracy condition (just below threshold)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'AchievementCondition' => [
				'user_id' => $context->user['id'],
				'set_id' => $setId,
				'category' => '%',
				'value' => 99
			]
		]);

		// Trigger check
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkSetAchievements($setId);

		// Assert achievement NOT unlocked
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::SUPERIOR_ACCURACY);
	}
}
