<?php

/**
 * Base class for all achievement tests
 */
abstract class AchievementTestCase extends ControllerTestCase
{
	/**
	 * Assert that an achievement is unlocked for a user
	 *
	 * @param int $userId User ID
	 * @param int $achievementId Achievement ID
	 * @param string|null $message Optional custom assertion message
	 */
	protected function assertAchievementUnlocked($userId, $achievementId, $message = null)
	{
		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		$exists = $AchievementStatus->find('count', [
			'conditions' => [
				'user_id' => $userId,
				'achievement_id' => $achievementId
			]
		]);

		$this->assertGreaterThan(
			0,
			$exists,
			$message ?: "Achievement {$achievementId} should be unlocked for user {$userId}"
		);
	}

	/**
	 * Assert that an achievement is NOT unlocked for a user
	 *
	 * @param int $userId User ID
	 * @param int $achievementId Achievement ID
	 */
	protected function assertAchievementNotUnlocked($userId, $achievementId)
	{
		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		$exists = $AchievementStatus->find('count', [
			'conditions' => [
				'user_id' => $userId,
				'achievement_id' => $achievementId
			]
		]);

		$this->assertEquals(
			0,
			$exists,
			"Achievement {$achievementId} should NOT be unlocked for user {$userId}"
		);
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
	protected function triggerAchievementCheck($userId)
	{
		// Set the cookie that triggers achievement checking
		$_COOKIE['initialLoading'] = 'true';

		// Login as this user (AppController requires it)
		$_COOKIE['hackedLoggedInUserID'] = $userId;

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
	protected function triggerSetCompletionAchievementCheck($userId, $completedSetsCount)
	{
		// Save set completion count to achievement_condition (simulates completing sets)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$condition = $AchievementCondition->find('first', [
			'conditions' => [
				'user_id' => $userId,
				'category' => 'set',
			]
		]);

		if (!$condition)
			$condition = [];

		$condition['AchievementCondition']['category'] = 'set';
		$condition['AchievementCondition']['user_id'] = $userId;
		$condition['AchievementCondition']['value'] = $completedSetsCount;
		$AchievementCondition->save($condition);

		// Login as this user
		$_COOKIE['hackedLoggedInUserID'] = $userId;
		Auth::init();

		$controller = new SetsController();
		$controller->constructClasses();
		$controller->checkSetCompletedAchievements();
	}

	/**
	 * Create a set with multiple tsumegos + SetConnection records
	 *
	 * KEY DISCOVERY: TsumegoUtil::collectTsumegosFromSet() requires SetConnection records!
	 * Without them, the method returns empty array and achievements don't unlock.
	 *
	 * @param int $difficulty Set difficulty rating
	 * @param int $count Number of tsumegos to create
	 * @return int Created set ID
	 */
	protected function createSetWithTsumegosAndConnections($difficulty, $count)
	{
		$Set = ClassRegistry::init('Set');
		$Tsumego = ClassRegistry::init('Tsumego');
		$SetConnection = ClassRegistry::init('SetConnection');

		// Create Set (use auto-increment ID to avoid collisions)
		$Set->create();
		$Set->save([
			'Set' => [
				'difficulty' => $difficulty,
				'public' => 0,
				'title' => "Test Set (difficulty $difficulty)",
			],
		]);
		$setId = $Set->getInsertID();

		// Create Tsumegos + SetConnection records (BOTH required!)
		for ($i = 0; $i < $count; $i++)
		{
			$Tsumego->create();
			$Tsumego->save([
				'Tsumego' => [
					'set_id' => $setId,
					'num' => $i + 1,
					'rating' => $difficulty,
					'sgf' => '(;GM[1]FF[4])',
				],
			]);
			$tsumegoId = $Tsumego->getInsertID();

			// CRITICAL: SetConnection required for collectTsumegosFromSet()
			$SetConnection->create();
			$SetConnection->save([
				'SetConnection' => [
					'set_id' => $setId,
					'tsumego_id' => $tsumegoId,
					'num' => $i + 1,
				],
			]);
		}

		return $setId;
	}
}
