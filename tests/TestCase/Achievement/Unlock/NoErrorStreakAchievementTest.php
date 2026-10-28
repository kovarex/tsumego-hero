<?php

use App\Model\Table\AchievementTable;
use App\Utility\AchievementChecker;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\HeroPowers;

//Tests achievements IDs 53-58 for completing streaks without errors
//using achievement_condition table with category CATEGORY_NO_ERROR_STREAK
class NoErrorStreakAchievementTest extends AchievementTestCase
{
	public function testNoErrorStreakIncreasedThroughBrowser()
	{
		$contextInput = [];
		for ($i = 0; $i < AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT; $i++)
			$contextInput['tsumegos'] [] = $i + 1;
		$browser = Browser::instance();
		$context = new ContextPreparator($contextInput);
		for ($i = 0; $i < AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT; $i++)
		{
			$browser->get('/' . $context->setConnections[$i]['id']);
			$browser->playWithResult('S');
			$browser->get('/' . $context->setConnections[$i]['id']);
			$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
				['conditions' => [
					'user_id' => Auth::getUserID(),
					'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
			$this->assertNotNull($achievementCondition);
			$this->assertEquals($achievementCondition['AchievementCondition']['value'], $i + 1);
		}

		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");
	}

	public function testNoErrorStreakGetsClearedWhenErrorHappensThroughBrowser()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => 1]],
			'tsumego' => 1]);

		// we check the value is 1 to start with
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// then we get a fail, and the progress gets reset
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('F'); // one fail
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 0);
	}

	//A problem solved only after a mistake in the same play is not an error-free solve.
	public function testSolveAfterMisplayInTheSamePlayKeepsTheStreakAtZero()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => 9]],
			'tsumego' => 1]);

		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('F');
		$browser->playWithResult('S'); // the same play

		$this->assertSame(0, $this->noErrorStreak(), 'A solve after a mistake in the same play does not extend the streak');
	}

	//A problem misplayed in an earlier play still counts when it is solved cleanly later.
	public function testSolvingAProblemMisplayedInAnEarlierPlayExtendsTheStreak()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => 9]],
			'tsumego' => 1]);
		$link = '/' . $context->setConnections[0]['id'];

		$browser->get($link);
		$browser->playWithResult('F');
		$browser->get($link); // a new play of the same problem
		$browser->playWithResult('S');

		$this->assertSame(1, $this->noErrorStreak(), 'A clean solve extends the streak');
	}

	//A rejuvenation restores hearts only: a solve that follows a mistake in the same play is not an error-free solve.
	public function testRejuvenationKeepsTheMisplayForTheStreak()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REJUVENATION_MINIMUM_LEVEL, 'health' => 0],
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => 9]],
			'tsumego' => 1]);
		$context->changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->setConnections[0]['id']);

		$browser->playWithResult('F');
		$browser->clickId('rejuvenation');
		$browser->waitUntil(function () use ($context) {
			return $context->reloadUser()['damage'] == 0;
		}, 10, 'rejuvenation to restore the damage');
		$browser->playWithResult('S');

		$this->assertSame(0, $this->noErrorStreak(), 'A rejuvenated play still counts as a play with a mistake');
	}

	public function testNoErrorStreakDoesntGetAffectedOnSolvedTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => 1]],
			'tsumego' => ['status' => 'S', 'set_order' => 1]]);

		// we check the value is 1 to start with
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// First we try to solve the already solved one
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('S');
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		$this->assertNotNull($achievementCondition);
		// nothing happen, solving a solved tsuomego doesn't do anything
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// then we get a fail on solved tsumego
		$browser->playWithResult('F'); // one fail
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		$this->assertNotNull($achievementCondition);
		// anod nothing still happens, failing a solved tsumego doesn't matter
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);
	}

	public function testSingleNoErrorStreakAchievements()
	{
		// Arrange: Create user and set err=10 (just meets threshold for AchievementId::NO_ERROR_STREAK_I)
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");

		// Assert: Higher achievements should NOT be unlocked yet
		$this->assertAchievementNotUnlocked(AchievementId::NO_ERROR_STREAK_II);
		$this->assertAchievementNotUnlocked(AchievementId::NO_ERROR_STREAK_III);
		$this->assertAchievementNotUnlocked(AchievementId::NO_ERROR_STREAK_IV);
		$this->assertAchievementNotUnlocked(AchievementId::NO_ERROR_STREAK_V);
	}

	public function testTwoHundredStreakUnlocksAll()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => AchievementTable::NO_ERROR_STREAK_VI_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		// Assert: All 6 achievements should be unlocked
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_I, "No Error Streak I");
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_II, "No Error Streak II");
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_III, "No Error Streak III");
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_IV, "No Error Streak IV");
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_V, "No Error Streak V");
		$this->assertAchievementUnlocked(AchievementId::NO_ERROR_STREAK_VI, "No Error Streak VI");
	}

	public function testAllNoErrorStreakAchievements()
	{
		$thresholds = [
			[AchievementId::NO_ERROR_STREAK_I, AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT],
			[AchievementId::NO_ERROR_STREAK_II, AchievementTable::NO_ERROR_STREAK_II_STREAK_COUNT],
			[AchievementId::NO_ERROR_STREAK_III, AchievementTable::NO_ERROR_STREAK_III_STREAK_COUNT],
			[AchievementId::NO_ERROR_STREAK_IV, AchievementTable::NO_ERROR_STREAK_IV_STREAK_COUNT],
			[AchievementId::NO_ERROR_STREAK_V, AchievementTable::NO_ERROR_STREAK_V_STREAK_COUNT],
			[AchievementId::NO_ERROR_STREAK_VI, AchievementTable::NO_ERROR_STREAK_VI_STREAK_COUNT],
		];

		foreach ($thresholds as [$achievementId, $errValue])
		{
			$context = new ContextPreparator(['achievement-conditions' => [['category' => AchievementCondition::CATEGORY_NO_ERROR_STREAK, 'value' => $errValue]]]);
			new AchievementChecker()->checkNoErrorAchievements();
			$this->assertAchievementUnlocked($achievementId, "Achievement {$achievementId->name} should unlock at err=$errValue");
		}
	}

	private function noErrorStreak(): int
	{
		$condition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_NO_ERROR_STREAK]]);
		return (int) $condition['AchievementCondition']['value'];
	}
}
