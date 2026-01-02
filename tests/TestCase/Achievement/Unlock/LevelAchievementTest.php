<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/**
 * These achievements unlock when user reaches specific level thresholds:
 * - Achievement::LEVEL_UP: Level 10
 * - Achievement::FIRST_HERO_POWER: Level 20
 * - Achievement::UPGRADED_INTUITION: Level 30
 * - Achievement::MORE_POWER: Level 40
 * - Achievement::HALF_WAY_TO_TOP: Level 50
 * - Achievement::CONGRATS_MORE_PROBLEMS: Level 60
 * - Achievement::NICE_LEVEL: Level 70
 * - Achievement::DID_LOT_OF_TSUMEGO: Level 80
 * - Achievement::STILL_DOING_TSUMEGO: Level 90
 * - Achievement::THE_TOP: Level 100
 */
class LevelAchievementTest extends AchievementTestCase
{
	// Test that Achievement::LEVEL_UP unlocks at level 10
	public function testAchievement_LEVEL_UP_UnlocksAtLevel10()
	{
		foreach ([10, 9] as $level)
		{
			new ContextPreparator(['user' => ['level' => $level]]);
			$this->triggerAchievementCheck();
			if ($level >= 10)
				$this->assertAchievementUnlocked(Achievement::LEVEL_UP);
			else
				$this->assertAchievementNotUnlocked(Achievement::LEVEL_UP);
		}
	}

	public function testAllLevelAchievements()
	{
		$achievementLevels = [
			Achievement::LEVEL_UP => 10,
			Achievement::FIRST_HERO_POWER => 20,
			Achievement::UPGRADED_INTUITION => 30,
			Achievement::MORE_POWER => 40,
			Achievement::HALF_WAY_TO_TOP => 50,
			Achievement::CONGRATS_MORE_PROBLEMS => 60,
			Achievement::NICE_LEVEL => 70,
			Achievement::DID_LOT_OF_TSUMEGO => 80,
			Achievement::STILL_DOING_TSUMEGO => 90,
			Achievement::THE_TOP => 100,
		];

		foreach ($achievementLevels as $achievementId => $requiredLevel)
		{
			new ContextPreparator(['user' => ['level' => $requiredLevel]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId, "Achievement $achievementId should unlock at level $requiredLevel");
		}
	}

	public function testAjaxRequestDoesntTriggerAchievementCheck()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$browser->get('/' . $context->setConnections[0]['id']);

		Auth::getUser()['rating'] = Rating::getRankMiddleRatingFromReadableRank('1d');
		Auth::saveUser();

		$browser->clickId('sprint');
		usleep(1000 * 100); // if this fails often, we should check the ajax success and wait until that
		$this->assertTrue($browser->driver->executeScript('return window.xpStatus.isSprintActive();'));

		// 1d achievement wasn't unlocked by the ajax event
		$this->assertAchievementNotUnlocked(Achievement::RATING_1_DAN);

		// it gets unlocked on refresh
		$browser->get('/' . $context->setConnections[0]['id']);
		$this->assertAchievementUnlocked(Achievement::RATING_1_DAN);
	}
}
