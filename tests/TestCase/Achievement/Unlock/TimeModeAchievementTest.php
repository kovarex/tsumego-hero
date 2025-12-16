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
	/**
	 * Test Slow mode rank achievements (IDs 70-75)
	 */
	public function testSlowModeRankAchievements()
	{
		// Arrange: User who passed 5k in Slow mode (category=3, status=3=solved)
		$context = new ContextPreparator([
			'user' => ['name' => 'slowtester'],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => 3, // Slow
				'status' => 3, // solved
				'rank' => '5k',
				'attempts' => [],
			]],
		]);

		// Act: Trigger achievement check
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();

		// Assert: Achievement 70 (5k slow) should unlock
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_APPRENTICE_SLOW, "Slow 5k achievement should unlock");
	}

	/**
	 * Test Fast mode rank achievements (IDs 76-81)
	 */
	public function testFastModeRankAchievements()
	{
		// Arrange: User who passed 4k in Fast mode (category=2, status=3=solved)
		$context = new ContextPreparator([
			'user' => ['name' => 'fasttester'],
			'time-mode-ranks' => ['4k'],
			'time-mode-sessions' => [[
				'category' => 2, // Fast
				'status' => 3, // solved
				'rank' => '4k',
				'attempts' => [],
			]],
		]);

		// Act: Trigger achievement check
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();

		// Assert: Achievement 77 (4k fast) should unlock
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_SCHOLAR_FAST, "Fast 4k achievement should unlock");
	}

	/**
	 * Test Blitz mode rank achievements (IDs 82-87)
	 */
	public function testBlitzModeRankAchievements()
	{
		// Arrange: User who passed 1d in Blitz mode (category=1, status=3=solved)
		$context = new ContextPreparator([
			'user' => ['name' => 'blitztester'],
			'time-mode-ranks' => ['1d'],
			'time-mode-sessions' => [[
				'category' => 1, // Blitz
				'status' => 3, // solved
				'rank' => '1d',
				'attempts' => [],
			]],
		]);

		// Act: Trigger achievement check
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();

		// Assert: Achievement 87 (1d blitz) should unlock
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_MASTER_BLITZ, "Blitz 1d achievement should unlock");
	}

	/**
	 * Test all rank achievements systematically
	 */
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
		]; // Slow achievements

		foreach ($ranks as $index => $rank)
		{
			// Test Slow mode
			$context = new ContextPreparator([
				'user' => ['name' => "slow_$rank"],
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => 3, // Slow
					'status' => 3, // solved
					'rank' => $rank,
					'attempts' => [],
				]],
			]);
			$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
			Auth::init();
			$controller = new AppController();
			$controller->constructClasses();
			$controller->checkTimeModeAchievements();
			$this->assertAchievementUnlocked($context->user['id'], $achievementIds[$index], "Slow $rank achievement");

			// Test Fast mode
			$context = new ContextPreparator([
				'user' => ['name' => "fast_$rank"],
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => 2, // Fast
					'status' => 3, // solved
					'rank' => $rank,
					'attempts' => [],
				]],
			]);
			$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
			Auth::init();
			$controller = new AppController();
			$controller->constructClasses();
			$controller->checkTimeModeAchievements();
			$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_APPRENTICE_FAST + $index, "Fast $rank achievement");

			// Test Blitz mode
			$context = new ContextPreparator([
				'user' => ['name' => "blitz_$rank"],
				'time-mode-ranks' => [$rank],
				'time-mode-sessions' => [[
					'category' => 1, // Blitz
					'status' => 3, // solved
					'rank' => $rank,
					'attempts' => [],
				]],
			]);
			$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
			Auth::init();
			$controller = new AppController();
			$controller->constructClasses();
			$controller->checkTimeModeAchievements();
			$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_APPRENTICE_BLITZ + $index, "Blitz $rank achievement");
		}
	}

	/**
	 * Test that failed attempts don't unlock achievements
	 */
	public function testFailedAttemptDoesNotUnlock()
	{
		// Arrange: User who failed 5k in Slow mode (status=2=failed)
		$context = new ContextPreparator([
			'user' => ['name' => 'failedtester'],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => 3, // Slow
				'status' => 2, // failed
				'rank' => '5k',
				'attempts' => [],
			]],
		]);

		// Act: Trigger achievement check
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();

		// Assert: Achievement 70 should NOT unlock
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::TIME_MODE_APPRENTICE_SLOW);
	}

	/**
	 * Test Precision achievements (IDs 88-91)
	 * Note: Precision achievements require both points AND rank threshold
	 */
	public function testPrecisionAchievements()
	{
		// Test 88: 950 points at 10k or stronger
		$context = new ContextPreparator([
			'user' => ['name' => 'precision88'],
			'time-mode-ranks' => ['10k'],
			'time-mode-sessions' => [[
				'category' => 3, // Slow
				'status' => 3, // solved
				'rank' => '10k',
				'attempts' => [],
			]],
		]);

		// Manually set points (ContextPreparator doesn't support 'points' field yet)
		ClassRegistry::init('TimeModeSession')->updateAll(
			['points' => 950],
			['user_id' => $context->user['id']]
		);

		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();

		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_PRECISION_I, "Precision I: 950+ at 10k");

		// Test 89: 900 points at 8k or stronger
		$context = new ContextPreparator([
			'user' => ['name' => 'precision89'],
			'time-mode-ranks' => ['8k'],
			'time-mode-sessions' => [[
				'category' => 3,
				'status' => 3,
				'rank' => '8k',
				'attempts' => [],
			]],
		]);
		ClassRegistry::init('TimeModeSession')->updateAll(
			['points' => 900],
			['user_id' => $context->user['id']]
		);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_PRECISION_II, "Precision II: 900+ at 8k");

		// Test 90: 875 points at 6k or stronger
		$context = new ContextPreparator([
			'user' => ['name' => 'precision90'],
			'time-mode-ranks' => ['6k'],
			'time-mode-sessions' => [[
				'category' => 3,
				'status' => 3,
				'rank' => '6k',
				'attempts' => [],
			]],
		]);
		ClassRegistry::init('TimeModeSession')->updateAll(
			['points' => 875],
			['user_id' => $context->user['id']]
		);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_PRECISION_III, "Precision III: 875+ at 6k");

		// Test 91: 850 points at 4k or stronger
		$context = new ContextPreparator([
			'user' => ['name' => 'precision91'],
			'time-mode-ranks' => ['4k'],
			'time-mode-sessions' => [[
				'category' => 3,
				'status' => 3,
				'rank' => '4k',
				'attempts' => [],
			]],
		]);
		ClassRegistry::init('TimeModeSession')->updateAll(
			['points' => 850],
			['user_id' => $context->user['id']]
		);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkTimeModeAchievements();
		$this->assertAchievementUnlocked($context->user['id'], Achievement::TIME_MODE_PRECISION_IV, "Precision IV: 850+ at 4k");
	}
}
