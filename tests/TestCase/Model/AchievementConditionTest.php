<?php

use App\Utility\Constants;

/**
 * A player has each achievement condition once, per set for the set records, and the writes
 * update it in place instead of adding a second one.
 */
class AchievementConditionTest extends CakeTestCase
{
	public function testValueIsNullForAConditionThePlayerDoesNotHave()
	{
		$context = new ContextPreparator();

		$this->assertNull(AchievementCondition::value($context->user['id'], AchievementCondition::CATEGORY_SPRINT));
	}

	public function testPutReplacesTheValue()
	{
		$context = new ContextPreparator();

		AchievementCondition::put($context->user['id'], AchievementCondition::CATEGORY_COMPLETED_SETS, 3);
		AchievementCondition::put($context->user['id'], AchievementCondition::CATEGORY_COMPLETED_SETS, 2);

		$this->assertSame(2.0, AchievementCondition::value($context->user['id'], AchievementCondition::CATEGORY_COMPLETED_SETS));
		$this->assertSame(1, $this->countConditions($context->user['id']));
	}

	public function testIncrementStartsAtOneAndAddsOne()
	{
		$context = new ContextPreparator();

		AchievementCondition::increment($context->user['id'], AchievementCondition::CATEGORY_GOLDEN);
		$this->assertSame(1.0, AchievementCondition::value($context->user['id'], AchievementCondition::CATEGORY_GOLDEN));

		AchievementCondition::increment($context->user['id'], AchievementCondition::CATEGORY_GOLDEN);
		$this->assertSame(2.0, AchievementCondition::value($context->user['id'], AchievementCondition::CATEGORY_GOLDEN));
		$this->assertSame(1, $this->countConditions($context->user['id']));
	}

	public function testSetRecordsKeepTheBestValuePerSet()
	{
		$context = new ContextPreparator();
		$firstSetID = $this->createSet('first');
		$secondSetID = $this->createSet('second');
		$userID = $context->user['id'];

		AchievementCondition::keepLowest($userID, AchievementCondition::CATEGORY_SET_TIME, 30, $firstSetID);
		AchievementCondition::keepLowest($userID, AchievementCondition::CATEGORY_SET_TIME, 20, $firstSetID);
		AchievementCondition::keepLowest($userID, AchievementCondition::CATEGORY_SET_TIME, 25, $firstSetID);
		AchievementCondition::keepHighest($userID, AchievementCondition::CATEGORY_SET_ACCURACY, 80, $firstSetID);
		AchievementCondition::keepHighest($userID, AchievementCondition::CATEGORY_SET_ACCURACY, 70, $firstSetID);
		AchievementCondition::keepHighest($userID, AchievementCondition::CATEGORY_SET_ACCURACY, 90, $secondSetID);

		$this->assertSame(20.0, AchievementCondition::value($userID, AchievementCondition::CATEGORY_SET_TIME, $firstSetID));
		$this->assertSame(80.0, AchievementCondition::value($userID, AchievementCondition::CATEGORY_SET_ACCURACY, $firstSetID));
		$this->assertSame(90.0, AchievementCondition::value($userID, AchievementCondition::CATEGORY_SET_ACCURACY, $secondSetID));
		$this->assertNull(AchievementCondition::value($userID, AchievementCondition::CATEGORY_SET_TIME, $secondSetID));
		$this->assertSame(3, $this->countConditions($userID));
	}

	public function testValuesReturnsTheConditionsThePlayerHasByCategory()
	{
		$context = new ContextPreparator(['achievement-conditions' => [
			['category' => AchievementCondition::CATEGORY_DAN_SOLVE_1D, 'value' => 4],
			['category' => AchievementCondition::CATEGORY_DAN_SOLVE_3D, 'value' => 1]]]);

		$values = AchievementCondition::values($context->user['id'], array_values(AchievementCondition::DAN_SOLVE_CATEGORIES));

		$this->assertSame([AchievementCondition::CATEGORY_DAN_SOLVE_1D => 4.0, AchievementCondition::CATEGORY_DAN_SOLVE_3D => 1.0], $values);
	}

	public function testTheDatabaseRefusesASecondConditionOfTheSameCategory()
	{
		$context = new ContextPreparator(['achievement-conditions' => [
			['category' => AchievementCondition::CATEGORY_USER_OF_THE_DAY, 'value' => 1]]]);

		$this->expectException(PDOException::class);
		ClassRegistry::init('AchievementCondition')->getDataSource()->getConnection()->exec(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, value) VALUES ('
			. $context->user['id'] . ', ' . AchievementCondition::CATEGORY_USER_OF_THE_DAY . ', 1)');
	}

	private function countConditions(int $userID): int
	{
		return ClassRegistry::init('AchievementCondition')->find('count', ['conditions' => ['user_id' => $userID]]);
	}

	private function createSet(string $title): int
	{
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save(['Set' => ['title' => $title, 'public' => 1, 'order' => Constants::$DEFAULT_SET_ORDER]]);
		return (int) $setModel->id;
	}
}
