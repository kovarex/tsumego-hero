<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\SemeaiTypeTable;
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

	//A collection with a semeai problem, whose question is answered with a click, neither records the
	//player's time and accuracy nor unlocks a set achievement
	public function testCollectionWithASemeaiProblemDoesNotUnlockSpeedAchievement()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$tsumegoId = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->firstOrFail()->tsumego_id;
		$semeaiTable = $this->fetchTable('TsumegoSemeai');
		$semeaiTable->saveOrFail($semeaiTable->newEntity(
			['tsumego_id' => $tsumegoId, 'semeai_type_id' => SemeaiTypeTable::TYPE_EYE_VS_NO_EYE, 'liberty_count' => 4, 'variance' => 0],
			['accessibleFields' => ['tsumego_id' => true]]));
		$this->saveAverageTime($context->user['id'], $setId, 4);

		$this->get('sets/view/' . $setId);

		$this->assertAchievementNotUnlocked(AchievementId::SPEED_I);
		$this->assertNull(AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $setId));
	}

	//The same collection without one records the accuracy, so it is the semeai problem that leaves it out
	public function testCollectionWithoutASemeaiProblemRecordsTheAccuracy()
	{
		$context = new ContextPreparator();
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		$this->get('sets/view/' . $setId);

		$this->assertNotNull(AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $setId));
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
