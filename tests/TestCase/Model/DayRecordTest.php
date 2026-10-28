<?php

use App\Model\Entity\DayRecord;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\DayRecordTable;
use App\Utility\Auth;

class DayRecordTest extends IntegrationTestCase
{
	//Test that countGemSolve correctly increments gem counters based on solved puzzle rank.
	public function testUpdateGemsIncrementsCorrectCounter(): void
	{
		// Create a day record with specific gem configuration
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0', // 15k, 9k, 1d gems active
				'emerald_solves' => 0,
				'sapphire_solves' => 0,
				'ruby_solves' => 0,
			]],
		]);

		// Test emerald gem (15k rank)
		DayRecordTable::countGemSolve('15k');
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(1, $dayRecord->emerald_solves, 'Solving 15k should increment emerald_solves');
		$this->assertSame(0, $dayRecord->sapphire_solves, 'sapphire_solves should remain 0');
		$this->assertSame(0, $dayRecord->ruby_solves, 'ruby_solves should remain 0');

		// Test sapphire gem (9k rank)
		DayRecordTable::countGemSolve('9k');
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(1, $dayRecord->emerald_solves, 'emerald_solves should remain 1');
		$this->assertSame(1, $dayRecord->sapphire_solves, 'Solving 9k should increment sapphire_solves');
		$this->assertSame(0, $dayRecord->ruby_solves, 'ruby_solves should remain 0');

		// Test ruby gem (1d rank)
		DayRecordTable::countGemSolve('1d');
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(1, $dayRecord->emerald_solves, 'emerald_solves should remain 1');
		$this->assertSame(1, $dayRecord->sapphire_solves, 'sapphire_solves should remain 1');
		$this->assertSame(1, $dayRecord->ruby_solves, 'Solving 1d should increment ruby_solves');
	}

	//Test that countGemSolve correctly increments counter multiple times.
	public function testUpdateGemsIncrementsMultipleTimes(): void
	{
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'emerald_solves' => 0,
				'sapphire_solves' => 0,
				'ruby_solves' => 0,
			]],
		]);

		// Solve 5 problems at 15k rank
		for ($i = 0; $i < 5; $i++)
			DayRecordTable::countGemSolve('15k');

		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(5, $dayRecord->emerald_solves, 'emerald_solves should be 5 after 5 solves');
	}

	//Test that countGemSolve ignores ranks that don't match the current gem configuration.
	public function testUpdateGemsIgnoresNonMatchingRanks(): void
	{
		$context = new ContextPreparator([
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0', // 15k, 9k, 1d gems active
				'emerald_solves' => 0,
				'sapphire_solves' => 0,
				'ruby_solves' => 0,
			]],
		]);

		// Solve at 14k (not matching 15k gem)
		DayRecordTable::countGemSolve('14k');
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(0, $dayRecord->emerald_solves, 'emerald_solves should not increment for non-matching rank');

		// Solve at 8k (not matching 9k gem)
		DayRecordTable::countGemSolve('8k');
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(0, $dayRecord->sapphire_solves, 'sapphire_solves should not increment for non-matching rank');
	}

	//Test that counter increments beyond threshold and same user doesn't get achievement twice
	public function testCounterIncrementsBeyondThreshold(): void
	{
		// Create test user
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser', 'rating' => 1500],
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'emerald_solves' => 499, // One away from threshold
				'sapphire_solves' => 0,
				'ruby_solves' => 0,
			]],
		]);

		$testUserId = Auth::getUserID();

		// Test user solves 15k problem (crosses threshold)
		DayRecordTable::countGemSolve('15k');

		// Verify test user got the achievement
		$testUserAchievement = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $testUserId, 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_EMERALD])->first();
		$this->assertNotEmpty($testUserAchievement, 'Test user should get emerald achievement');
		$this->assertSame(1, (int) $testUserAchievement->value);

		// Verify counter incremented to 500
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(500, $dayRecord->emerald_solves);

		// Same user solves another 15k problem
		DayRecordTable::countGemSolve('15k');

		// Counter increments to 501 (past threshold)
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(501, $dayRecord->emerald_solves);

		// User still has only ONE achievement (not duplicated)
		$achievements = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $testUserId, 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_EMERALD])->all()->toList();
		$this->assertCount(1, $achievements, 'User should not get duplicate achievement');
	}

	//Test that when user already has achievement and counter is at 499, it stays at 499
	//This prevents users with existing achievements from "wasting" the threshold slot
	public function testUserWithAchievementDoesNotIncrementAtThreshold(): void
	{
		// Create user who ALREADY has emerald achievement
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser', 'rating' => 1500],
			'day-records' => [[
				'date' => date('Y-m-d'),
				'gems' => '0-0-0',
				'emerald_solves' => 499, // At threshold-1
				'sapphire_solves' => 0,
				'ruby_solves' => 0,
			]],
		]);

		// Manually create achievement for user
		$achievementConditionTable = $this->fetchTable('AchievementCondition');
		$achievementConditionTable->saveOrFail($achievementConditionTable->newEntity([
			'user_id' => Auth::getUserID(),
			'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_EMERALD,
			'value' => 1,
		]));

		// User solves 15k problem (would cross threshold)
		DayRecordTable::countGemSolve('15k');

		// Counter STAYS at 499 (doesn't increment when user already has achievement)
		// This preserves old behavior: increment 499→500, find achievement, decrement 500→499
		$dayRecord = $this->todaysDayRecord();
		$this->assertSame(499, $dayRecord->emerald_solves, 'Counter should stay at 499 to allow other users to unlock');
	}

	private function todaysDayRecord(): DayRecord
	{
		return $this->fetchTable('DayRecord')->find()->where(['date' => date('Y-m-d')])->firstOrFail();
	}
}
