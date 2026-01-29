<?php

class AppControllerTest extends TestCaseWithAuth
{
	/**
	 * Test that updateGems correctly increments gem counters based on solved puzzle rank.
	 */
	public function testUpdateGemsIncrementsCorrectCounter(): void
	{
		// Create a day record with specific gem configuration
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0', // 15k, 9k, 1d gems active
				'gemCounter1' => 0,
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			]],
		]);

		// Test emerald gem (15k rank)
		AppController::updateGems('15k');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter1'], 'Solving 15k should increment gemCounter1');
		$this->assertSame(0, (int) $dayRecord['DayRecord']['gemCounter2'], 'gemCounter2 should remain 0');
		$this->assertSame(0, (int) $dayRecord['DayRecord']['gemCounter3'], 'gemCounter3 should remain 0');

		// Test sapphire gem (9k rank)
		AppController::updateGems('9k');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter1'], 'gemCounter1 should remain 1');
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter2'], 'Solving 9k should increment gemCounter2');
		$this->assertSame(0, (int) $dayRecord['DayRecord']['gemCounter3'], 'gemCounter3 should remain 0');

		// Test ruby gem (1d rank)
		AppController::updateGems('1d');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter1'], 'gemCounter1 should remain 1');
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter2'], 'gemCounter2 should remain 1');
		$this->assertSame(1, (int) $dayRecord['DayRecord']['gemCounter3'], 'Solving 1d should increment gemCounter3');
	}

	/**
	 * Test that updateGems correctly increments counter multiple times.
	 */
	public function testUpdateGemsIncrementsMultipleTimes(): void
	{
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'gemCounter1' => 0,
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			]],
		]);

		// Solve 5 problems at 15k rank
		for ($i = 0; $i < 5; $i++)
			AppController::updateGems('15k');

		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(5, (int) $dayRecord['DayRecord']['gemCounter1'], 'gemCounter1 should be 5 after 5 solves');
	}

	/**
	 * Test that updateGems ignores ranks that don't match the current gem configuration.
	 */
	public function testUpdateGemsIgnoresNonMatchingRanks(): void
	{
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0', // 15k, 9k, 1d gems active
				'gemCounter1' => 0,
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			]],
		]);

		// Solve at 14k (not matching 15k gem)
		AppController::updateGems('14k');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(0, (int) $dayRecord['DayRecord']['gemCounter1'], 'gemCounter1 should not increment for non-matching rank');

		// Solve at 8k (not matching 9k gem)
		AppController::updateGems('8k');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(0, (int) $dayRecord['DayRecord']['gemCounter2'], 'gemCounter2 should not increment for non-matching rank');
	}

	/**
	 * Test that counter increments beyond threshold and same user doesn't get achievement twice
	 */
	public function testCounterIncrementsBeyondThreshold(): void
	{
		// Create test user
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser', 'rating' => 1500],
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'gemCounter1' => 499, // One away from threshold
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			]],
		]);

		$testUserId = Auth::getUserID();

		// Test user solves 15k problem (crosses threshold)
		AppController::updateGems('15k');

		// Verify test user got the achievement
		$testUserAchievement = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $testUserId, 'category' => 'emerald'],
		]);
		$this->assertNotEmpty($testUserAchievement, 'Test user should get emerald achievement');
		$this->assertSame(1, (int) $testUserAchievement['AchievementCondition']['value']);

		// Verify counter incremented to 500
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(500, (int) $dayRecord['DayRecord']['gemCounter1']);

		// Same user solves another 15k problem
		AppController::updateGems('15k');

		// Counter increments to 501 (past threshold)
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(501, (int) $dayRecord['DayRecord']['gemCounter1']);

		// User still has only ONE achievement (not duplicated)
		$achievements = ClassRegistry::init('AchievementCondition')->find('all', [
			'conditions' => ['user_id' => $testUserId, 'category' => 'emerald'],
		]);
		$this->assertCount(1, $achievements, 'User should not get duplicate achievement');
	}

	/**
	 * Test that when user already has achievement and counter is at 499, it stays at 499
	 * This prevents users with existing achievements from "wasting" the threshold slot
	 */
	public function testUserWithAchievementDoesNotIncrementAtThreshold(): void
	{
		// Create user who ALREADY has emerald achievement
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser', 'rating' => 1500],
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'gemCounter1' => 499, // At threshold-1
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			]],
		]);

		// Manually create achievement for user
		ClassRegistry::init('AchievementCondition')->save([
			'AchievementCondition' => [
				'user_id' => Auth::getUserID(),
				'category' => 'emerald',
				'value' => 1,
			],
		]);

		// User solves 15k problem (would cross threshold)
		AppController::updateGems('15k');

		// Counter STAYS at 499 (doesn't increment when user already has achievement)
		// This preserves old behavior: increment 499→500, find achievement, decrement 500→499
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => date('Y-m-d')]]);
		$this->assertSame(499, (int) $dayRecord['DayRecord']['gemCounter1'], 'Counter should stay at 499 to allow other users to unlock');
	}
}
