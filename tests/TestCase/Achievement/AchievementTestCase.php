<?php

// Base class for all achievement tests
abstract class AchievementTestCase extends ControllerTestCase
{
	protected function assertAchievementUnlockedWhen($when, $achievementId, $message = null)
	{
		if ($when)
			$this->assertAchievementUnlocked($achievementId, $message);
		else
			$this->assertAchievementNotUnlocked($achievementId);
	}

	protected function assertAchievementUnlocked($achievementId, $message = null)
	{
		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		$exists = $AchievementStatus->find('count', [
			'conditions' => ['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId]]);

		$this->assertGreaterThan(0, $exists, $message ?: "Achievement {$achievementId} should be unlocked");
	}

	protected function assertAchievementNotUnlocked($achievementId)
	{
		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		$exists = $AchievementStatus->find('count', [
			'conditions' => ['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId]]);
		$this->assertEquals(0, $exists, "Achievement {$achievementId} should NOT be unlocked");
	}

	/**
	 * Get all unlocked achievement IDs for a user
	 *
	 * @param int $userId User ID
	 * @return array Achievement IDs
	 */
	protected function getUserAchievements($userId)
	{
		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		$achievements = $AchievementStatus->find('list', [
			'conditions' => ['user_id' => $userId],
			'fields' => ['achievement_id', 'achievement_id']
		]);

		return array_values($achievements);
	}

	/**
	 * Trigger achievement check (simulates what happens after login)
	 *
	 * This sets the 'initialLoading' cookie and makes a request,
	 * which causes AppController::beforeFilter to check achievements.
	 *
	 * @param int $userId User ID
	 */
	protected function triggerAchievementCheck()
	{
		// Set the cookie that triggers achievement checking
		$_COOKIE['initialLoading'] = 'true';

		// Login as this user (AppController requires it)
		$_COOKIE['hackedLoggedInUserID'] = Auth::getUserID();

		// Make a simple request - achievements checked in AppController::beforeFilter
		// beforeFilter will call Auth::init() which loads fresh user data from DB
		$this->testAction('/sites/index', ['return' => 'view']);
	}

	/**
	 * Trigger set completion achievement check
	 *
	 * This simulates having completed N sets by inserting achievement_condition
	 * and then calling the achievement check method directly.
	 *
	 * Note: This is semi-black-box - we directly invoke the check method
	 * since set completion achievements are checked immediately when completing
	 * a set, not on every page load.
	 *
	 * @param int $userId User ID
	 * @param int $completedSetsCount Number of completed sets
	 */
	protected function triggerSetCompletionAchievementCheck($completedSetsCount)
	{
		// Save set completion count to achievement_condition (simulates completing sets)
		$condition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => Auth::getUserID(), 'category' => 'set']]) ?: [];

		$condition['AchievementCondition']['category'] = 'set';
		$condition['AchievementCondition']['user_id'] = Auth::getUserID();
		$condition['AchievementCondition']['value'] = $completedSetsCount;
		ClassRegistry::init('AchievementCondition')->save($condition);

		new AchievementChecker()->checkSetCompletedAchievements()->finalize();
	}

	/**
	 * Create a set with multiple tsumegos + SetConnection records
	 *
	 * KEY DISCOVERY: TsumegoUtil::collectTsumegosFromSet() requires SetConnection records!
	 * Without them, the method returns empty array and achievements don't unlock.
	 *
	 * @param int $rating Set difficulty rating
	 * @param int $count Number of tsumegos to create
	 * @return int Created set ID
	 */
	protected function createSetWithTsumegosAndConnections($rating, $count)
	{
		$Set = ClassRegistry::init('Set');
		$Tsumego = ClassRegistry::init('Tsumego');
		$SetConnection = ClassRegistry::init('SetConnection');

		// Create Set (use auto-increment ID to avoid collisions)
		$Set->create();
		$Set->save([
			'public' => 1,
			'title' => "Test Set (rating $rating)"]);
		$setId = $Set->getInsertID();

		// Create Tsumegos + SetConnection records (BOTH required!)
		for ($i = 0; $i < $count; $i++)
		{
			$Tsumego->create();
			$Tsumego->save(['rating' => $rating]);
			$tsumegoId = $Tsumego->getInsertID();

			$SetConnection->create();
			$SetConnection->save(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => $i + 1]);

			ClassRegistry::init('TsumegoStatus')->create();
			ClassRegistry::init('TsumegoStatus')->save(['tsumego_id' => $tsumegoId, 'status' => 'S', 'user_id' => Auth::getUserID()]);
		}

		return $setId;
	}
}
