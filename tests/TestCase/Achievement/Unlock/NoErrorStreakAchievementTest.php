<?php

use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\HeroPowers;

/**
 * No Error Streak Achievement Test
 *
 * Tests achievements IDs 53-58 for completing streaks without errors
 * using achievement_condition table with category='err'
 */
class NoErrorStreakAchievementTest extends AchievementTestCase
{
	public function testNoErrorStreakIncreasedThroughBrowser()
	{
		$contextInput = [];
		for ($i = 0; $i < Achievement::NO_ERROR_STREAK_I_STREAK_COUNT; $i++)
			$contextInput['tsumegos'] [] = $i + 1;
		$browser = Browser::instance();
		$context = new ContextPreparator($contextInput);
		for ($i = 0; $i < Achievement::NO_ERROR_STREAK_I_STREAK_COUNT; $i++)
		{
			$browser->get('/' . $context->setConnections[$i]['id']);
			$browser->playWithResult('S');
			$browser->get('/' . $context->setConnections[$i]['id']);
			$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
				['conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'err']]);
			$this->assertNotNull($achievementCondition);
			$this->assertEquals($achievementCondition['AchievementCondition']['value'], $i + 1);
		}

		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");
	}

	public function testNoErrorStreakGetsClearedWhenErrorHappensThroughBrowser()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => 'err', 'value' => 1]],
			'tsumego' => 1]);

		// we check the value is 1 to start with
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// then we get a fail, and the progress gets reset
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('F'); // one fail
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 0);
	}

	/**
	 * A problem solved only after a mistake in the same play is not an error-free
	 * solve: the streak stays at 0 instead of counting this solve.
	 */
	public function testSolveAfterMisplayInTheSamePlayKeepsTheStreakAtZero()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],
			'tsumego' => 1]);

		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('F');
		$browser->playWithResult('S'); // the same play, no reload in between

		$this->assertSame(0, $this->noErrorStreak(), 'A solve that followed a misplay of the same play must not count');
	}

	/**
	 * A problem misplayed in an earlier play is solved cleanly today: the mistake
	 * already broke the streak back then, so this solve extends the new run.
	 */
	public function testSolvingAProblemMisplayedInAnEarlierPlayExtendsTheStreak()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],
			'tsumego' => 1]);
		$link = '/' . $context->setConnections[0]['id'];

		$browser->get($link);
		$browser->playWithResult('F');
		$browser->get($link); // a new play of the same problem
		$browser->playWithResult('S');

		$this->assertSame(1, $this->noErrorStreak(), 'The clean solve should start a new run');
	}

	/**
	 * A rejuvenation restores hearts, not the mistake: a solve that follows one in
	 * the same play is still not an error-free solve.
	 */
	public function testRejuvenationDoesNotUndoTheMisplayForTheStreak()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REJUVENATION_MINIMUM_LEVEL, 'health' => 0],
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],
			'tsumego' => 1]);
		$context->changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->setConnections[0]['id']);

		$browser->playWithResult('F');
		$browser->clickId('rejuvenation');
		$browser->driver->wait(10, 500)->until(function () use ($context) {
			return $context->reloadUser()['damage'] == 0;
		});
		$browser->playWithResult('S');

		$this->assertSame(0, $this->noErrorStreak(), 'A healed misplay is still a misplay');
	}

	public function testNoErrorStreakDoesntGetAffectedOnSolvedTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'achievement-conditions' => [['category' => 'err', 'value' => 1]],
			'tsumego' => ['status' => 'S', 'set_order' => 1]]);

		// we check the value is 1 to start with
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		$this->assertNotNull($achievementCondition);
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// First we try to solve the already solved one
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->playWithResult('S');
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		$this->assertNotNull($achievementCondition);
		// nothing happen, solving a solved tsuomego doesn't do anything
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);

		// then we get a fail on solved tsumego
		$browser->playWithResult('F'); // one fail
		$browser->get('/' . $context->setConnections[0]['id']);
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		$this->assertNotNull($achievementCondition);
		// anod nothing still happens, failing a solved tsumego doesn't matter
		$this->assertEquals($achievementCondition['AchievementCondition']['value'], 1);
	}

	public function testSingleNoErrorStreakAchievements()
	{
		// Arrange: Create user and set err=10 (just meets threshold for Achievement::NO_ERROR_STREAK_I)
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => Achievement::NO_ERROR_STREAK_I_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_I, "No Error Streak I should unlock at 10");

		// Assert: Higher achievements should NOT be unlocked yet
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_II);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_III);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_IV);
		$this->assertAchievementNotUnlocked(Achievement::NO_ERROR_STREAK_V);
	}

	public function testTwoHundredStreakUnlocksAll()
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => Achievement::NO_ERROR_STREAK_VI_STREAK_COUNT]]]);
		new AchievementChecker()->checkNoErrorAchievements();

		// Assert: All 6 achievements should be unlocked
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_I, "No Error Streak I");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_II, "No Error Streak II");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_III, "No Error Streak III");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_IV, "No Error Streak IV");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_V, "No Error Streak V");
		$this->assertAchievementUnlocked(Achievement::NO_ERROR_STREAK_VI, "No Error Streak VI");
	}

	public function testAllNoErrorStreakAchievements()
	{
		$thresholds = [
			Achievement::NO_ERROR_STREAK_I => Achievement::NO_ERROR_STREAK_I_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_II => Achievement::NO_ERROR_STREAK_II_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_III => Achievement::NO_ERROR_STREAK_III_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_IV => Achievement::NO_ERROR_STREAK_IV_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_V => Achievement::NO_ERROR_STREAK_V_STREAK_COUNT,
			Achievement::NO_ERROR_STREAK_VI => Achievement::NO_ERROR_STREAK_VI_STREAK_COUNT,
		];

		foreach ($thresholds as $achievementId => $errValue)
		{
			$context = new ContextPreparator(['achievement-conditions' => [['category' => 'err', 'value' => $errValue]]]);
			new AchievementChecker()->checkNoErrorAchievements();
			$this->assertAchievementUnlocked($achievementId, "Achievement $achievementId should unlock at err=$errValue");
		}
	}

	private function noErrorStreak(): int
	{
		$condition = ClassRegistry::init('AchievementCondition')->find('first',
			['conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		return (int) $condition['AchievementCondition']['value'];
	}
}
