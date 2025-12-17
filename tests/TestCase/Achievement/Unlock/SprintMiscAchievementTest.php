<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
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
	 * Test Bad Potion achievement (ID 98): "Have the potion not triggered 15 times on the same day"
	 * Actually checks potion >= 1 in code (not 15 as description says)
	 * Requires achievement_condition with category='potion' and value >= 1
	 */
	public function testBadPotionAchievement()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'potion', 'value' => 1]]]);
		new AchievementChecker()->checkDanSolveAchievements();
		$this->assertAchievementUnlocked(Achievement::BAD_POTION, 'Bad Potion achievement should unlock when potion condition >= 1');
	}
}
