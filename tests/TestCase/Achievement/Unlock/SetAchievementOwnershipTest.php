<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\SemeaiTypeTable;
use App\Utility\Util;
use App\Utility\AchievementId;

//The accuracy and speed achievements belong to the collections the site itself
//published. A collection a player assembled, or a sandbox build in progress, is not
//site content, however perfectly or fast it is finished.
class SetAchievementOwnershipTest extends AchievementTestCase
{
	public function testPersonalCollectionDoesNotUnlockSpeedAchievement()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100, 0, $context->user['id']);
		$this->saveAverageTime($context->user['id'], $setId, 4);

		$this->get('sets/view/' . $setId);

		$this->assertAchievementNotUnlocked(AchievementId::SPEED_I);
	}

	public function testSandboxCollectionDoesNotUnlockSpeedAchievement()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100, 0);
		$this->saveAverageTime($context->user['id'], $setId, 4);

		$this->get('sets/view/' . $setId);

		$this->assertAchievementNotUnlocked(AchievementId::SPEED_I);
	}

	public function testCollectionOfTheSiteUnlocksSpeedAchievement()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$this->saveAverageTime($context->user['id'], $setId, 4);

		$this->get('sets/view/' . $setId);

		$this->assertAchievementUnlocked(AchievementId::SPEED_I);
	}

	//A collection of semeai problems counts like any other: set 210 was once left out by its id
	public function testCollectionOfSemeaiProblemsUnlocksSpeedAchievement()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$semeaiTable = $this->fetchTable('TsumegoSemeai');
		foreach ($this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->all() as $setConnection)
			$semeaiTable->saveOrFail($semeaiTable->newEntity(
				['tsumego_id' => $setConnection->tsumego_id, 'semeai_type_id' => SemeaiTypeTable::TYPE_EYE_VS_NO_EYE, 'liberty_count' => 4, 'variance' => 0],
				['accessibleFields' => ['tsumego_id' => true]]));
		$this->saveAverageTime($context->user['id'], $setId, 4);

		$this->get('sets/view/' . $setId);

		$this->assertAchievementUnlocked(AchievementId::SPEED_I);
		$this->assertNotNull(AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $setId), 'the accuracy is recorded');
		$this->assertSame(4.0, (float) AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_TIME, $setId), 'the best time is kept');
	}

	//Set 210 itself is no exception any more
	public function testSet210RecordsItsAccuracy()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);
		Util::execute('SET FOREIGN_KEY_CHECKS = 0');
		Util::execute('UPDATE set_connection SET set_id = 210 WHERE set_id = ?', [$setId]);
		Util::execute('UPDATE `set` SET id = 210 WHERE id = ?', [$setId]);
		Util::execute('SET FOREIGN_KEY_CHECKS = 1');

		$this->get('sets/view/210');

		$this->assertNotNull(AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, 210));
	}

	//The average seconds per problem the set page reads back when it scores a finished
	//collection. A collection without attempts scores 60 seconds, so a stored value below
	//that survives the page view.
	private function saveAverageTime(int $userId, int $setId, float $seconds): void
	{
		$achievementConditionTable = $this->fetchTable('AchievementCondition');
		$achievementConditionTable->saveOrFail($achievementConditionTable->newEntity([
			'user_id' => $userId,
			'set_id' => $setId,
			'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_SET_TIME,
			'value' => $seconds]));
	}
}
