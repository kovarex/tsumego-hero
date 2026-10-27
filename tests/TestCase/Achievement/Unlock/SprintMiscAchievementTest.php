<?php

use App\Utility\AchievementId;
use App\Utility\AchievementChecker;
use App\Utility\HeroPowers;

class SprintMiscAchievementTest extends AchievementTestCase
{
	//Test Sprint achievement (ID 96): "Solve 30 problems within a sprint"
	//Requires achievement_condition with category CATEGORY_SPRINT and value >= 30
	public function testSprintAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_SPRINT, 'value' => 30]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(AchievementId::SPRINT, 'Sprint achievement should unlock when sprint condition >= 30');
	}

	public function testSprintDoesNotUnlockBelow30()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_SPRINT, 'value' => 29]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementNotUnlocked(AchievementId::SPRINT);
	}

	//Test Gold Digger achievement (ID 97): "Don't fail 10 times in a row on a golden tsumego"
	//Requires achievement_condition with category CATEGORY_GOLDEN and value >= 10
	public function testGoldDiggerAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_GOLDEN, 'value' => 10]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(AchievementId::GOLD_DIGGER, 'Gold Digger achievement should unlock when golden condition >= 10');
	}

	//Test Bad Potion achievement checker (ID 98): "Have the potion not triggered
	//enough times on the same day." Requires achievement_condition with
	//category CATEGORY_POTION and value >= BAD_POTION_THRESHOLD.
	//This tests only the AchievementChecker. The real trigger path
	//(_updatePotionCondition incrementing the counter) is tested in
	//PlayResultProcessorComponentTest::testPotionConditionIncrementsOnPreviousFail.
	public function testBadPotionAchievementChecker(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_POTION, 'value' => HeroPowers::$BAD_POTION_THRESHOLD]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(AchievementId::BAD_POTION, 'Bad Potion checker should unlock when potion condition >= ' . HeroPowers::$BAD_POTION_THRESHOLD);
	}

	public function testBadPotionDoesNotUnlockBelow15(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_POTION, 'value' => HeroPowers::$BAD_POTION_THRESHOLD - 1]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementNotUnlocked(AchievementId::BAD_POTION);
	}
}
