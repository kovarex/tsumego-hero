<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Utility\AchievementId;

//The achievements of several conditions unlock together, on one page load, which reads the conditions once
class ConditionAchievementsTest extends AchievementTestCase
{
	public function testOnePageLoadUnlocksTheAchievementsOfEveryCondition(): void
	{
		new ContextPreparator(['achievement-conditions' => [
			['category' => AchievementConditionTable::CATEGORY_USER_OF_THE_DAY, 'value' => 1],
			['category' => AchievementConditionTable::CATEGORY_EMERALD, 'value' => 1],
			['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT],
			['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => AchievementTable::COMPLETE_SETS_I_SETS_COUNT]]]);

		$this->triggerAchievementCheck();

		$this->assertAchievementUnlocked(AchievementId::USER_OF_THE_DAY);
		$this->assertAchievementUnlocked(AchievementId::EMERALD);
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_I);
		$this->assertAchievementUnlocked(AchievementId::COMPLETE_SETS_I);
		$this->assertAchievementNotUnlocked(AchievementId::SAPPHIRE);
		$this->assertAchievementNotUnlocked(AchievementId::NO_ERROR_STREAK_II);
	}
}
