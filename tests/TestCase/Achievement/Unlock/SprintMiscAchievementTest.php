<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('HeroPowers', 'Utility');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class SprintMiscAchievementTest extends AchievementTestCase
{
	/**
	 * Test Sprint achievement (ID 96): "Solve 30 problems within a sprint"
	 * Requires achievement_condition with category='sprint' and value >= 30
	 */
	public function testSprintAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'sprint', 'value' => 30]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(Achievement::SPRINT, 'Sprint achievement should unlock when sprint condition >= 30');
	}

	public function testSprintDoesNotUnlockBelow30()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'sprint', 'value' => 29]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementNotUnlocked(Achievement::SPRINT);
	}

	/**
	 * Test Gold Digger achievement (ID 97): "Don't fail 10 times in a row on a golden tsumego"
	 * Requires achievement_condition with category='golden' and value >= 10
	 */
	public function testGoldDiggerAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'golden', 'value' => 10]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(Achievement::GOLD_DIGGER, 'Gold Digger achievement should unlock when golden condition >= 10');
	}

	/**
	 * Test Bad Potion achievement checker (ID 98): "Have the potion not triggered
	 * enough times on the same day." Requires achievement_condition with
	 * category='potion' and value >= BAD_POTION_THRESHOLD.
	 *
	 * This tests only the AchievementChecker. The real trigger path
	 * (updatePotionCondition incrementing the counter) is tested in
	 * PlayResultProcessorComponentTest::testPotionConditionIncrementsOnPreviousFail.
	 */
	public function testBadPotionAchievementChecker(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'potion', 'value' => HeroPowers::$BAD_POTION_THRESHOLD]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(Achievement::BAD_POTION, 'Bad Potion checker should unlock when potion condition >= ' . HeroPowers::$BAD_POTION_THRESHOLD);
	}

	public function testBadPotionDoesNotUnlockBelow15(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'potion', 'value' => HeroPowers::$BAD_POTION_THRESHOLD - 1]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementNotUnlocked(Achievement::BAD_POTION);
	}
}
