<?php

App::uses('Achievement', 'Model');

/**
 * Time Mode Achievement Test
 *
 * Tests Time Mode achievements IDs 70-91:
 * - Rank achievements (70-87): Pass ranks in Slow/Fast/Blitz modes
 * - Precision achievements (88-91): Score thresholds at specific ranks
 */
class TimeModeAchievementTest extends AchievementTestCase
{
	public function testSlowTimeModeAchievements()
	{
		$context = new ContextPreparator([
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '5k']]]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_APPRENTICE_SLOW, "Slow 5k achievement should unlock");
	}

	public function testFastTimeModeAchievements()
	{
		$context = new ContextPreparator([
			'time-mode-ranks' => ['4k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_FAST_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '4k']]]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_SCHOLAR_FAST, "Fast 4k achievement should unlock");
	}

	public function testBlitzTimeModeAchievements()
	{
		$context = new ContextPreparator([
			'time-mode-ranks' => ['1d'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '1d']]]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_MASTER_BLITZ, "Blitz 1d achievement should unlock");
	}

	public function testAllRankAchievements()
	{
		$ranks = ['5k', '4k', '3k', '2k', '1k', '1d'];
		$achievementIds = [
			Achievement::TIME_MODE_APPRENTICE_SLOW,
			Achievement::TIME_MODE_SCHOLAR_SLOW,
			Achievement::TIME_MODE_LABOURER_SLOW,
			Achievement::TIME_MODE_ADEPT_SLOW,
			Achievement::TIME_MODE_EXPERT_SLOW,
			Achievement::TIME_MODE_MASTER_SLOW
		];

		foreach ($ranks as $index => $rank)
		{
			// Test Slow mode
			$context = new ContextPreparator([
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'rank' => $rank]]]);
			new AchievementChecker()->checkTimeModeAchievements();
			$this->assertAchievementUnlocked($achievementIds[$index], "Slow $rank achievement");

			// Test Fast mode
			$context = new ContextPreparator([
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_FAST_SPEED,
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'rank' => $rank,
					'attempts' => []]]]);
			new AchievementChecker()->checkTimeModeAchievements();
			$this->assertAchievementUnlocked(Achievement::TIME_MODE_APPRENTICE_FAST + $index, "Fast $rank achievement");

			// Test Blitz mode
			$context = new ContextPreparator([
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'rank' => $rank]]]);
			new AchievementChecker()->checkTimeModeAchievements();
			$this->assertAchievementUnlocked(Achievement::TIME_MODE_APPRENTICE_BLITZ + $index, "Blitz $rank achievement");
		}
	}

	public function testFailedAttemptDoesNotUnlockAchievement()
	{
		$context = new ContextPreparator([
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_FAILED,
				'rank' => '5k']]]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementNotUnlocked( Achievement::TIME_MODE_APPRENTICE_SLOW);
	}

	/**
	 * Test Precision achievements (IDs 88-91)
	 * Note: Precision achievements require both points AND rank threshold
	 */
	public function testPrecisionAchievements()
	{
		// Test 88: 950 points at 10k or stronger
		$context = new ContextPreparator([
			'time-mode-ranks' => ['10k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '10k']]]);

		// Manually set points (ContextPreparator doesn't support 'points' field yet)
		ClassRegistry::init('TimeModeSession')->updateAll(['points' => 950], ['user_id' => $context->user['id']]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_PRECISION_I, "Precision I: 950+ at 10k");

		// Test 89: 900 points at 8k or stronger
		$context = new ContextPreparator([
			'time-mode-ranks' => ['8k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '8k']]]);
		ClassRegistry::init('TimeModeSession')->updateAll(['points' => 900], ['user_id' => $context->user['id']]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_PRECISION_II, "Precision II: 900+ at 8k");

		// Test 90: 875 points at 6k or stronger
		$context = new ContextPreparator([
			'time-mode-ranks' => ['6k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'rank' => '6k']]]);
		ClassRegistry::init('TimeModeSession')->updateAll(['points' => 875], ['user_id' => $context->user['id']]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_PRECISION_III, "Precision III: 875+ at 6k");

		// Test 91: 850 points at 4k or stronger
		$context = new ContextPreparator([
			'time-mode-ranks' => ['4k'],
			'time-mode-sessions' => [[
				'category' => 3,
				'status' => 3,
				'rank' => '4k']]]);
		ClassRegistry::init('TimeModeSession')->updateAll(['points' => 850], ['user_id' => $context->user['id']]);
		new AchievementChecker()->checkTimeModeAchievements();
		$this->assertAchievementUnlocked(Achievement::TIME_MODE_PRECISION_IV, "Precision IV: 850+ at 4k");
	}
}
