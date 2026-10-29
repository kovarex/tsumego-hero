<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\Constants;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

//A player has each achievement condition once, per set for the set records, and the writes
//update it in place instead of adding a second one.
class AchievementConditionTest extends AppTestCase
{
	public function testValueIsNullForAConditionThePlayerDoesNotHave()
	{
		$context = new ContextPreparator();

		$this->assertNull(AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SPRINT));
	}

	public function testPutReplacesTheValue()
	{
		$context = new ContextPreparator();

		AchievementConditionTable::put($context->user['id'], AchievementConditionTable::CATEGORY_COMPLETED_SETS, 3);
		AchievementConditionTable::put($context->user['id'], AchievementConditionTable::CATEGORY_COMPLETED_SETS, 2);

		$this->assertSame(2.0, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_COMPLETED_SETS));
		$this->assertSame(1, $this->countConditions($context->user['id']));
	}

	public function testIncrementStartsAtOneAndAddsOne()
	{
		$context = new ContextPreparator();

		AchievementConditionTable::increment($context->user['id'], AchievementConditionTable::CATEGORY_GOLDEN);
		$this->assertSame(1.0, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_GOLDEN));

		AchievementConditionTable::increment($context->user['id'], AchievementConditionTable::CATEGORY_GOLDEN);
		$this->assertSame(2.0, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_GOLDEN));
		$this->assertSame(1, $this->countConditions($context->user['id']));
	}

	public function testSetRecordsKeepTheBestValuePerSet()
	{
		$context = new ContextPreparator();
		$firstSetID = $this->createSet('first');
		$secondSetID = $this->createSet('second');
		$userID = $context->user['id'];

		AchievementConditionTable::keepLowest($userID, AchievementConditionTable::CATEGORY_SET_TIME, 30, $firstSetID);
		AchievementConditionTable::keepLowest($userID, AchievementConditionTable::CATEGORY_SET_TIME, 20, $firstSetID);
		AchievementConditionTable::keepLowest($userID, AchievementConditionTable::CATEGORY_SET_TIME, 25, $firstSetID);
		AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 80, $firstSetID);
		AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 70, $firstSetID);
		AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 90, $secondSetID);

		$this->assertSame(20.0, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $firstSetID));
		$this->assertSame(80.0, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $firstSetID));
		$this->assertSame(90.0, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $secondSetID));
		$this->assertNull(AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $secondSetID));
		$this->assertSame(3, $this->countConditions($userID));
	}

	public function testValuesReturnsTheConditionsThePlayerHasByCategory()
	{
		$context = new ContextPreparator(['achievement-conditions' => [
			['category' => AchievementConditionTable::CATEGORY_DAN_SOLVE_1D, 'value' => 4],
			['category' => AchievementConditionTable::CATEGORY_DAN_SOLVE_3D, 'value' => 1]]]);

		$values = AchievementConditionTable::values($context->user['id'], array_values(AchievementConditionTable::DAN_SOLVE_CATEGORIES));

		$this->assertSame([AchievementConditionTable::CATEGORY_DAN_SOLVE_1D => 4.0, AchievementConditionTable::CATEGORY_DAN_SOLVE_3D => 1.0], $values);
	}

	public function testTheDatabaseRefusesASecondConditionOfTheSameCategory()
	{
		$context = new ContextPreparator(['achievement-conditions' => [
			['category' => AchievementConditionTable::CATEGORY_USER_OF_THE_DAY, 'value' => 1]]]);

		$this->expectException(PDOException::class);
		Util::execute(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, value) VALUES ('
			. $context->user['id'] . ', ' . AchievementConditionTable::CATEGORY_USER_OF_THE_DAY . ', 1)');
	}

	private function countConditions(int $userID): int
	{
		return TableRegistry::getTableLocator()->get('AchievementCondition')->find()->where(['user_id' => $userID])->count();
	}

	private function createSet(string $title): int
	{
		$setTable = TableRegistry::getTableLocator()->get('Set');
		$createdSet = $setTable->saveOrFail($setTable->newEntity(['title' => $title, 'public' => 1, 'order' => Constants::$DEFAULT_SET_ORDER]));
		return (int) $createdSet->id;
	}
}
