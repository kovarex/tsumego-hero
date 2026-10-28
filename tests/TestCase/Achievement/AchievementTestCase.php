<?php

use App\Model\Table\SgfTable;
/**
 * Base class for all achievement tests.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementChecker;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\TsumegoUtil;

abstract class AchievementTestCase extends IntegrationTestCase
{
	protected function assertAchievementUnlockedWhen($when, AchievementId $achievementId, $message = null)
	{
		if ($when)
			$this->assertAchievementUnlocked($achievementId, $message);
		else
			$this->assertAchievementNotUnlocked($achievementId);
	}

	protected function assertAchievementUnlocked(AchievementId $achievementId, $message = null)
	{
		$exists = $this->fetchTable('AchievementStatus')->find()
			->where(['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId->value])->count();

		$this->assertGreaterThan(0, $exists, $message ?: $achievementId->name . ' should be unlocked');
	}

	protected function assertAchievementNotUnlocked(AchievementId $achievementId)
	{
		$exists = $this->fetchTable('AchievementStatus')->find()
			->where(['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId->value])->count();
		$this->assertEquals(0, $exists, $achievementId->name . ' should NOT be unlocked');
	}

	//Get all unlocked achievement IDs for a user
	/**
	 * @param int $userId User ID
	 * @return array Achievement IDs
	 */
	protected function getUserAchievements($userId)
	{
		return $this->fetchTable('AchievementStatus')->find()
			->where(['user_id' => $userId])
			->all()
			->extract('achievement_id')
			->toList();
	}

	//Trigger achievement check (simulates what happens after login)
	//This sets the 'initialLoading' cookie and makes a request,
	//which causes AppController::beforeFilter to check achievements.
	protected function triggerAchievementCheck()
	{
		// Set the cookie that triggers achievement checking
		$_COOKIE['initialLoading'] = 'true';

		// Login as this user (AppController requires it)
		$_COOKIE['hackedLoggedInUserID'] = Auth::getUserID();

		// Make a simple request - achievements checked in AppController::beforeFilter
		// beforeFilter will call Auth::init() which loads fresh user data from DB
		$this->get('/sites/index');
	}

	//Trigger set completion achievement check
	//This simulates having completed N sets by inserting achievement_condition
	//and then calling the achievement check method directly.
	//Note: This is semi-black-box - we directly invoke the check method
	//since set completion achievements are checked immediately when completing
	//a set, not on every page load.
	/** @param int $completedSetsCount Number of completed sets */
	protected function triggerSetCompletionAchievementCheck($completedSetsCount)
	{
		// Save set completion count to achievement_condition (simulates completing sets)
		AchievementConditionTable::put(Auth::getUserID(), AchievementConditionTable::CATEGORY_COMPLETED_SETS, $completedSetsCount);

		new AchievementChecker()->checkSetCompletedAchievements()->finalize();
	}

	//Create a set with multiple tsumegos + SetConnection records
	//KEY DISCOVERY: TsumegoUtil::collectTsumegosFromSet() requires SetConnection records!
	//Without them, the method returns empty array and achievements don't unlock.
	//$public: Set visibility (1 = public)
	//$userId: Owner of the collection (null = the site's own collection)
	/**
	 * @param int $rating Set difficulty rating
	 * @param int $count Number of tsumegos to create
	 * @return int Created set ID
	 */
	protected function createSetWithTsumegosAndConnections($rating, $count, int $public = 1, ?int $userId = null)
	{
		$Set = ClassRegistry::init('Set');
		$tsumegoTable = $this->fetchTable('Tsumego');
		$SetConnection = ClassRegistry::init('SetConnection');

		// Create Set (use auto-increment ID to avoid collisions)
		$Set->create();
		$Set->save([
			'public' => $public,
			'user_id' => $userId,
			'title' => "Test Set (rating $rating)"]);
		$setId = $Set->getInsertID();

		// Create Tsumegos + SetConnection records (BOTH required!)
		for ($i = 0; $i < $count; $i++)
		{
			$tsumegoId = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['rating' => $rating, 'author' => '']))->id;

			$SetConnection->create();
			$SetConnection->save(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => $i + 1]);

			$this->fetchTable('Sgf')->saveOrFail($this->fetchTable('Sgf')->newEntity(['tsumego_id' => $tsumegoId, 'sgf' => '(;GM[1]FF[4]SZ[19])', 'user_id' => Auth::getUserID(), 'sgf_status_id' => SgfTable::STATUS_ACCEPTED]));

			$this->fetchTable('TsumegoStatus')->saveOrFail($this->fetchTable('TsumegoStatus')->newEntity(['tsumego_id' => $tsumegoId, 'status' => 'S', 'user_id' => Auth::getUserID()]));
		}

		return $setId;
	}
}
