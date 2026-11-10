<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\Level;
use App\Utility\Rating;

//These achievements unlock when user reaches specific level thresholds:
//- AchievementId::LEVEL_UP: Level 10
//- AchievementId::FIRST_HERO_POWER: Level 20
//- AchievementId::UPGRADED_INTUITION: Level 30
//- AchievementId::MORE_POWER: Level 40
//- AchievementId::HALF_WAY_TO_TOP: Level 50
//- AchievementId::CONGRATS_MORE_PROBLEMS: Level 60
//- AchievementId::NICE_LEVEL: Level 70
//- AchievementId::DID_LOT_OF_TSUMEGO: Level 80
//- AchievementId::STILL_DOING_TSUMEGO: Level 90
//- AchievementId::THE_TOP: Level 100
class LevelAchievementTest extends AchievementTestCase
{
	// Test that AchievementId::LEVEL_UP unlocks at level 10
	public function testAchievement_LEVEL_UP_UnlocksAtLevel10()
	{
		foreach ([10, 9] as $level)
		{
			new ContextPreparator(['user' => ['level' => $level]]);
			$this->triggerAchievementCheck();
			if ($level >= 10)
				$this->assertAchievementUnlocked(AchievementId::LEVEL_UP);
			else
				$this->assertAchievementNotUnlocked(AchievementId::LEVEL_UP);
		}
	}

	public function testAllLevelAchievements()
	{
		$achievementLevels = [
			[AchievementId::LEVEL_UP, 10],
			[AchievementId::FIRST_HERO_POWER, 20],
			[AchievementId::UPGRADED_INTUITION, 30],
			[AchievementId::MORE_POWER, 40],
			[AchievementId::HALF_WAY_TO_TOP, 50],
			[AchievementId::CONGRATS_MORE_PROBLEMS, 60],
			[AchievementId::NICE_LEVEL, 70],
			[AchievementId::DID_LOT_OF_TSUMEGO, 80],
			[AchievementId::STILL_DOING_TSUMEGO, 90],
			[AchievementId::THE_TOP, 100],
		];

		foreach ($achievementLevels as [$achievementId, $requiredLevel])
		{
			new ContextPreparator(['user' => ['level' => $requiredLevel]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId, "Achievement {$achievementId->name} should unlock at level $requiredLevel");
		}
	}

	//The XP of the achievements a page load unlocks can raise the level past a level achievement, which
	//was checked before that XP came: the same page load unlocks it, not the next one
	public function testLevelReachedByTheXPOfOtherAchievementsUnlocksOnTheSamePageLoad()
	{
		$context = new ContextPreparator([
			'user' => ['level' => 1],
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 20]]]);
		$this->triggerAchievementCheck();

		$this->assertAchievementUnlocked(AchievementId::COMPLETE_SETS_II);
		$level = (int) $context->reloadUser()['level'];
		$this->assertGreaterThanOrEqual(10, $level, 'The XP of the set achievements is enough for level 10');
		foreach ([10 => AchievementId::LEVEL_UP, 20 => AchievementId::FIRST_HERO_POWER, 30 => AchievementId::UPGRADED_INTUITION] as $threshold => $achievementID)
			$this->assertAchievementUnlockedWhen($level >= $threshold, $achievementID);
	}

	public function testAjaxRequestDoesntTriggerAchievementCheck()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		$context->changeUserSoSprintCanBeUsed();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$browser->get('/' . $context->setConnections[0]['id']);

		Auth::saveUserField('rating', Rating::getRankMiddleRatingFromReadableRank('1d'));

		$browser->clickId('sprint');
		$browser->waitUntilJs('window.xpStatus.isSprintActive()', 5);

		// 1d achievement wasn't unlocked by the ajax event
		$this->assertAchievementNotUnlocked(AchievementId::RATING_1_DAN);

		// it gets unlocked on refresh
		$browser->get('/' . $context->setConnections[0]['id']);
		$this->assertAchievementUnlocked(AchievementId::RATING_1_DAN);
	}
}
