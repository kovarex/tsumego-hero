<?php

App::uses('Achievement', 'Model');
App::uses('Level', 'Utility');

/**
 * Achievement XP Amount Tests
 *
 * Tests that achievements grant correct XP amounts and XP is added correctly
 */
class XPAmountTest extends AchievementTestCase
{
	public function testAchievement_PROBLEMS_1000_Grants1000XP()
	{
		$browser = Browser::instance();
		// Arrange: User with 500 existing XP at level 69 (won't level up from 1000 XP - needs 4350 for next level)
		$context = new ContextPreparator(['user' => ['solved' => 1000]]);
		$browser->get('/');
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000, 'Achievement #1 should unlock at 1000 solved');
		$this->assertEquals(1000, $context->XPGained());
	}

	/**
	 * Test that Achievement #2 (2000 solved) grants 2000 XP
	 */
	public function testAchievement2Grants2000XP()
	{
		// Arrange: User at level 69 with 2000 solved (needs 4350 to level up)
		$context = new ContextPreparator(['user' => ['xp' => 0, 'level' => 69, 'solved' => 2000]]);

		// Act: Trigger achievement check
		new AchievementChecker()->checkProblemNumberAchievements()->finalize();

		// Assert: Both achievements #1 and #2 unlock (1000 solved + 2000 solved)
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000);
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_2000);

		// Assert: User XP is 3000 (0 + 1000 from #1 + 2000 from #2)
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals(3000, $user['User']['xp'], 'User should have 3000 XP (1000 from #1 + 2000 from #2)');
		$this->assertEquals(69, $user['User']['level'], 'User should remain at level 69');
	}

	public function testLevelAchievement_LEVEL_UP_Grants100XP()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['xp' => 0, 'level' => 10]]);
		$browser->get('/');
		$this->assertAchievementUnlocked(Achievement::LEVEL_UP);

		$this->assertEquals(100, $context->XPGained(), '100 XP from level achievement');
		$this->assertEquals(10, $context->reloadUser()['level'], 'Level 10 (needs 175 to reach 11, only has 100)');
	}

	/**
	 * Test that multiple achievements grant combined XP
	 */
	public function testMultipleAchievementsGrantCombinedXP()
	{
		// Arrange: User at level 69 qualifying for both Achievement #1 (1000 solved, 1000 XP) and #2 (2000 solved, 2000 XP)
		$context = new ContextPreparator(['user' => ['xp' => 100, 'level' => 69, 'solved' => 2000]]);

		// Act: Trigger achievement check (should unlock both #1 and #2)
		new AchievementChecker()->checkProblemNumberAchievements()->finalize();

		// Assert: Both achievements unlocked
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000);
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_2000);

		// Assert: User XP is 3100 (100 existing + 1000 from #1 + 2000 from #2)
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals(3100, $user['User']['xp'], 'User should have 3100 XP (100 + 1000 + 2000)');
		$this->assertEquals(69, $user['User']['level'], 'User should remain at level 69');
	}

	/**
	 * Test that existing XP is preserved when achievement unlocks
	 * This is the critical test for the = vs += bug
	 */
	public function testExistingXPIsNotOverwritten()
	{
		// Arrange: User at level 69 with significant existing XP (3000) - needs 4350 to level up
		$context = new ContextPreparator(['user' => ['xp' => 3000, 'level' => 69, 'solved' => 1000]]);

		// Act: Unlock achievement
		new AchievementChecker()->checkProblemNumberAchievements()->finalize();

		// Assert: Achievement unlocked
		$this->assertAchievementUnlocked(Achievement::PROBLEMS_1000);

		// Assert: Existing XP is NOT overwritten - should be 4000 (3000 + 1000)
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals(4000, $user['User']['xp'], 'Existing XP should be preserved! Expected 4000 (3000 existing + 1000 from achievement), but updateXP() overwrites with = instead of +=');
		$this->assertEquals(69, $user['User']['level'], 'User should remain at level 69');
	}
}
