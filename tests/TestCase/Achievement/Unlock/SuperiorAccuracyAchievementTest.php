<?php

/* Test Achievement::SUPERIOR_ACCURACY
 * "Finish a collection with 100% accuracy"
 * Requires: set with 100+ tsumegos, 100% accuracy (acA.value >= 100) */
use App\Utility\AchievementChecker;

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

	/**
	 * The first perfect collection pays out Superior Accuracy like any other
	 * achievement: its XP and its popup.
	 */
	public function testSuperiorAccuracyPaysOutOnTheFirstCollection()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id']);
		$checker = new AchievementChecker();
		$checker->checkSetAchievements($setId)->finalize();

		$this->assertContains(
			Achievement::SUPERIOR_ACCURACY,
			array_column($checker->updated, 'id'),
			'Superior Accuracy should be among the achievements unlocked by the first perfect collection');

		// 5000 XP for Superior Accuracy itself + 26000 for the accuracy and speed
		// achievements the same collection unlocks
		$this->assertSame(31000, $context->XPGained());
	}

	/**
	 * A further perfect collection raises the count without paying out again.
	 */
	public function testSuperiorAccuracyGrantsNothingForAFurtherCollection()
	{
		$context = new ContextPreparator();

		$firstSet = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($firstSet)->finalize();
		$this->assertGreaterThan(0, $context->XPGained(), 'The first collection is the one that pays out');

		$secondSet = $this->createFinishedCollection($context->user['id']);
		$repeat = new AchievementChecker();
		$repeat->checkSetAchievements($secondSet)->finalize();

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => Achievement::SUPERIOR_ACCURACY]]);
		$this->assertSame(2, (int) $status['AchievementStatus']['value'], 'The second collection should raise the count to 2');

		$this->assertSame([], $repeat->updated, 'A further collection should not queue a popup');
		$this->assertSame(0, $context->XPGained(), 'A further collection should not grant XP');
	}

	/**
	 * A collection of 100+ problems finished at 100% accuracy.
	 */
	private function createFinishedCollection(int $userId): int
	{
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $userId,
			'set_id' => $setId,
			'category' => '%',
			'value' => 100]);

		return $setId;
	}
}
