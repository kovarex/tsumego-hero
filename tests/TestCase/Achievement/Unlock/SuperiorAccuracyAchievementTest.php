<?php
App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

/* Test Achievement::SUPERIOR_ACCURACY
 * "Finish a collection with 100% accuracy"
 * Requires: set with 100+ tsumegos, 100% accuracy (acA.value >= 100) */
class SuperiorAccuracyAchievementTest extends AchievementTestCase
{
	public function testSuperiorAccuracyAchievement()
	{
		$context = new ContextPreparator();

		// Create set with 100 tsumegos + SetConnection records
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 100% accuracy condition
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 100]);

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementUnlocked(Achievement::SUPERIOR_ACCURACY, 'Superior Accuracy (100%) should unlock');
	}

	public function testSuperiorAccuracyDoesNotUnlockBelow100Percent()
	{
		$context = new ContextPreparator();

		// Create set with 100 tsumegos
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 99% accuracy condition (just below threshold)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
				'user_id' => $context->user['id'],
				'set_id' => $setId,
				'category' => '%',
				'value' => 99]);

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementNotUnlocked(Achievement::SUPERIOR_ACCURACY);
	}
}
