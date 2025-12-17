<?php

App::uses('Achievement', 'Model');
App::uses('Level', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('AchievementTestCase', 'TestCase/Achievement');

/**
 * Level-Up Trigger Tests
 *
 * Tests that XP grants trigger level-ups at correct thresholds
 */
class LevelUpTriggerTest extends AchievementTestCase
{
	public function testUserLevelsUpWhenXPReachesThreshold()
	{
		// Arrange: User at level 1 with 45 XP (needs 50 to reach level 2)
		$context = new ContextPreparator(['user' => ['xp' => 45, 'level' => 1]]);

		// Act: Grant 10 XP (total 55, threshold 50)
		$context->user['xp'] += 10; // Now 55
		Level::checkLevelUp($context->user);

		// Assert: User is now level 2 with 5 XP remaining (55 - 50)
		$this->assertEquals(2, $context->user['level'], 'User should level up to 2');
		$this->assertEquals(5, $context->user['xp'], 'User should have 5 XP remaining after level-up');
	}

	/**
	 * Test that user does NOT level up when XP is below threshold
	 */
	public function testUserDoesNotLevelUpBelowThreshold()
	{
		// Arrange: User at level 1 with 45 XP (needs 50 to reach level 2)
		$context = new ContextPreparator(['user' => ['xp' => 45, 'level' => 1]]);

		// Act: Grant 4 XP (total 49, below threshold of 50)
		$user = Auth::getUser();
		$user['xp'] = 45;
		$user['level'] = 1;
		$user['xp'] += 4; // Now 49
		Level::checkLevelUp($user);

		// Assert: User stays at level 1 with 49 XP
		$this->assertEquals(1, $user['level'], 'User should remain at level 1');
		$this->assertEquals(49, $user['xp'], 'User should have 49 XP');
	}

	/**
	 * Test that multiple level-ups occur from single large XP grant
	 */
	public function testMultipleLevelUpsFromSingleXPGrant()
	{
		// Arrange: User at level 1 with 0 XP
		$context = new ContextPreparator(['user' => ['xp' => 0, 'level' => 1]]);

		// Act: Grant 500 XP (enough for multiple levels)
		// Level 1→2: 50 XP, Level 2→3: 60 XP, Level 3→4: 70 XP, etc.
		$context->user['xp'] += 500;
		Level::checkLevelUp($context->user);

		// Assert: User leveled up multiple times
		$this->assertGreaterThan(1, $context->user['level'], 'User should level up multiple times from 500 XP');
		$this->assertLessThan(500, $context->user['xp'], 'Remaining XP should be less than original grant');
	}

	/**
	 * Test level-up with achievement XP grant
	 */
	public function testLevelUpTriggeredByAchievementXP()
	{
		// Arrange: User at level 10 with 165 XP (needs 175 to reach level 11)
		// Achievement will grant 100 XP (total 265), enough to level up
		$context = new ContextPreparator(['user' => ['xp' => 165, 'level' => 10, 'solved' => 1000]]);

		// Act: Unlock achievement granting 1000 XP (way more than needed)
		new AchievementChecker()->checkProblemNumberAchievements()->finalize();

		// Assert: User leveled up past level 10
		$this->assertGreaterThan(10, $context->reloadUser()['level'], 'User should level up past 10 from achievement XP');
	}

	/**
	 * Test XP threshold increases at each level
	 */
	public function testXPThresholdIncreasesEachLevel()
	{
		// Test that level requirements increase
		$level1Req = Level::getXPForNext(1);
		$level2Req = Level::getXPForNext(2);
		$level10Req = Level::getXPForNext(10);
		$level11Req = Level::getXPForNext(11);
		$level50Req = Level::getXPForNext(50);

		$this->assertEquals(50, $level1Req, 'Level 1→2 requires 50 XP');
		$this->assertEquals(60, $level2Req, 'Level 2→3 requires 60 XP');
		$this->assertLessThan($level11Req, $level10Req, 'Level 11 requires more XP than level 10');
		$this->assertGreaterThan(1000, $level50Req, 'Level 50 requires over 1000 XP');
	}

	/**
	 * Test level-up at exactly threshold (boundary test)
	 */
	public function testLevelUpAtExactThreshold()
	{
		// Arrange: User at level 1 with 50 XP (exactly the threshold)
		$user = ['xp' => 50, 'level' => 1];

		// Act: Check level-up
		Level::checkLevelUp($user);

		// Assert: User levels up to 2 with 0 XP remaining
		$this->assertEquals(2, $user['level'], 'User should level up at exact threshold');
		$this->assertEquals(0, $user['xp'], 'User should have 0 XP after consuming exact threshold');
	}

	/**
	 * Test level-up doesn't occur at 1 XP below threshold
	 */
	public function testNoLevelUpOneBelowThreshold()
	{
		// Arrange: User at level 1 with 49 XP (1 below threshold of 50)
		$user = ['xp' => 49, 'level' => 1];

		// Act: Check level-up
		Level::checkLevelUp($user);

		// Assert: User stays at level 1
		$this->assertEquals(1, $user['level'], 'User should not level up at 1 XP below threshold');
		$this->assertEquals(49, $user['xp'], 'User XP should remain unchanged');
	}
}
