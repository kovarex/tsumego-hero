<?php

App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');
App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('TsumegoXPAndRating', 'Utility');
App::uses('Level', 'Utility');
App::uses('Progress', 'Utility');
App::uses('TimeMode', 'Utility');
App::uses('MistakeTraining', 'Utility');

class PlayResultProcessorComponent extends Component
{
	/**
	 * Process a play result submitted via AJAX. Takes explicit params, no cookies.
	 *
	 * @param array $params Keys: tsumego_id, seconds, solved, mode, type, sprint, timeout
	 * @return array Result with xp_gained, rating_change, new_rating, etc.
	 */
	public function processResult(array $params): array
	{
		$tsumegoID = (int) $params['tsumego_id'];
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			return ['error' => 'Tsumego not found'];

		$result = [];
		$result['solved'] = !empty($params['solved']);

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'tsumego_id' => $tsumegoID,
				'user_id' => (int) Auth::getUserID(),
			],
		]);

		$previousStatusValue = $tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : 'N';
		$this->processDamage($result, $previousStatusValue);
		if (!Auth::isInMistakeTrainingMode())
			$this->updateTsumegoStatus($tsumego, $result, $tsumegoStatus);

		if (HeroPowers::getSprintRemainingSeconds() > 0)
			$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SPRINT_MULTIPLIER;

		$originalTsumegoRating = $tsumego['Tsumego']['rating'];

		$this->processRatingChange($tsumego, $result, $previousStatusValue);
		if (!$result['solved'])
			$result['potion_triggered'] = $this->processPotion();
		$this->processXpChange($tsumego, $result, $previousStatusValue, $originalTsumegoRating);
		$this->updateTsumegoAttempt($tsumego, $result, $previousStatusValue, (float) $params['seconds']);
		$this->maybeAddToMistakeTraining($tsumego, $result, $tsumegoStatus);
		$this->updateMistakeTrainingDue($tsumego);
		$this->processErrorAchievement($result, $previousStatusValue, $tsumegoID);
		if (!Auth::isInMistakeTrainingMode())
			$this->processUnsortedStuff($tsumego, $result, $previousStatusValue, $params['type'] ?? null, $params['sprint'] ?? null);

		if (Auth::isInTimeMode())
		{
			$timeMode = new TimeMode();
			$playResult = ['solved' => !empty($params['solved'])];
			$timeMode->processPlayResult($tsumego, $playResult, (float) ($params['seconds'] ?? 0), !empty($params['timeout']));
		}

		$response = [
			'xp_gained' => $result['xp-gained'] ?? 0,
			'new_rating' => Auth::getUser()['rating'],
			'new_xp' => Auth::getUser()['xp'],
			'new_level' => Auth::getUser()['level'],
			'new_damage' => Auth::getUser()['damage'],
			'status' => $tsumegoStatus['TsumegoStatus']['status'],
			'potion_triggered' => $result['potion_triggered'] ?? false,
		];

		return $response;
	}

	/**
	 * Marks a tsumego as visited (status 'V') if no status exists yet.
	 */
	public function markAsVisited(int $tsumegoID): void
	{
		Util::execute(
			'INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = status',
			[Auth::getUserID(), $tsumegoID, 'V']
		);
	}

	/**
	 * Potion: triggers after a fail when damage meets or exceeds max health.
	 */
	public function processPotion(): bool
	{
		if (!HeroPowers::canPotionTrigger())
			return false;

		$excessDeaths = Auth::getUser()['damage'] - Util::getHealthBasedOnLevel(Auth::getUser()['level']);
		$chance = min($excessDeaths * HeroPowers::$POTION_CHANCE_PER_DEATH, 100);

		if (rand(1, 100) <= $chance)
		{
			Auth::saveUserFields([
				'damage' => 0,
				'used_potion' => 1,
			]);
			return true;
		}

		AppController::updatePotionCondition();
		return false;
	}

	private function getNewStatus($solved, $currentStatus, &$result)
	{
		if ($solved)
		{
			if ($currentStatus == 'W') // half xp state
			{$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SECOND_SOLVE_XP_MULTIPLIER;
				return 'C'; // double solved
			}
			if ($currentStatus == 'G')
			{
				$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER;
				return 'S';
			}
			if ($currentStatus == 'V' || $currentStatus == 'N')
				return 'S';
			return $currentStatus; // failed can't be unfailed by solving, user has to wait until next day or rejuvenation
		}

		// not solved from now
		if ($currentStatus == 'V') // if it was just visited so far (so we don't overwrite solved)
		{if (Auth::getUser()['damage'] > Util::getHealthBasedOnLevel(Auth::getUser()['level']))
			return 'F';  // only mark as failed when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == 'W')
		{
			if (Auth::getUser()['damage'] > Util::getHealthBasedOnLevel(Auth::getUser()['level']))
				return 'X'; // only mark as 'stale failed' when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == 'G')
			return 'V'; // failed golden tsumego
		return $currentStatus;
	}

	private function updateTsumegoStatus(array $previousTsumego, array &$result, ?array $previousTsumegoStatus): void
	{
		if ($previousTsumegoStatus == null)
		{
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
		}

		if (isset($result['solved']))
		{
			$newStatus = $this->getNewStatus($result['solved'], $previousTsumegoStatus['TsumegoStatus']['status'], $result);
			if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousTsumegoStatus['TsumegoStatus']['status']))
				Auth::incrementUserField('solved', 1);
			$previousTsumegoStatus['TsumegoStatus']['status'] = $newStatus;
		}
		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoStatus')->save($previousTsumegoStatus);
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result, $previousTsumegoStatus, float $seconds): void
	{
		if (Auth::isInTimeMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousTsumegoStatus))
			return;
		$lastTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find(
			'first',
			['conditions'
				=> ['user_id' => Auth::getUserID(),
					'tsumego_id' => $previousTsumego['Tsumego']['id']],
				'order' => 'id DESC']
		);

		// only not solved ones are updated (misplays get accumulated)
		if (!$lastTsumegoAttempt || $lastTsumegoAttempt['TsumegoAttempt']['solved'])
		{
			$tsumegoAttempt = [];
			$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
			$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
			$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = 0;
		}
		else
			$tsumegoAttempt = $lastTsumegoAttempt;

		$tsumegoAttempt['TsumegoAttempt']['user_rating'] = Auth::getUser()['rating'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = $result['xp-gained'] ?: 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] += $seconds;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
		if ($result['solved'])
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = (int) $tsumegoAttempt['TsumegoAttempt']['misplays'];
		else
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = (int) $tsumegoAttempt['TsumegoAttempt']['misplays'] + 1;
		$tsumegoAttempt['TsumegoAttempt']['created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	/**
	 * Add to mistake training pool on first-encounter mistakes.
	 *
	 * Entry criteria: previous status was V or N (first encounter) AND
	 * not a clean first-try solve. Sets mt_due = NOW() + 1 day.
	 */
	private function maybeAddToMistakeTraining(array $tsumego, array $result, ?array $tsumegoStatus): void
	{
		if (!Auth::isLoggedIn())
			return;

		$oldStatus = $tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : 'N';
		if ($oldStatus !== 'V' && $oldStatus !== 'N')
			return;

		// A clean first-try solve (no accumulated failures) does not enter training.
		if ($result['solved'] && !$this->hadMisplaysBeforeSolve((int) $tsumego['Tsumego']['id']))
			return;

		$tsumegoId = (int) $tsumego['Tsumego']['id'];
		$due = date('Y-m-d H:i:s', strtotime('+1 day'));

		ClassRegistry::init('TsumegoStatus')->updateAll(
			['mt_due' => "'" . $due . "'"],
			['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoId]
		);
	}

	/**
	 * Recompute mt_due for any tsumego currently in the training pool.
	 * Called after every solve/fail so the SM-2 interval stays up to date.
	 * If the interval reaches graduation (>= 365 days), mt_due is cleared.
	 */
	private function updateMistakeTrainingDue(array $tsumego): void
	{
		if (!Auth::isLoggedIn())
			return;

		$tsumegoId = (int) $tsumego['Tsumego']['id'];
		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoId],
		]);
		if (!$status || empty($status['TsumegoStatus']['mt_due']))
			return;

		$newDue = MistakeTraining::computeNextDue(Auth::getUserID(), $tsumegoId);
		ClassRegistry::init('TsumegoStatus')->updateAll(
			['mt_due' => $newDue ? "'" . $newDue . "'" : 'NULL'],
			['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoId]
		);
	}

	private static function processRatingChangeStep(float &$userRating, float &$tsumegoRating, bool $isWin): void
	{
		$userRatingDelta = Rating::calculateRatingChange($userRating, $tsumegoRating, $isWin ? 1 : 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$tsumegoRatingDelta = Rating::calculateRatingChange($tsumegoRating, $userRating, $isWin ? 0 : 1, Constants::$TSUMEGO_RATING_CALCULATION_MODIFIER);
		$userRating += $userRatingDelta;
		$tsumegoRating += $tsumegoRatingDelta;
	}

	private function processRatingChange(array &$previousTsumego, array $result, string $previousTsumegoStatus): void
	{
		if (!Auth::ratingisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		$userRating = (float) Auth::getUser()['rating'];
		$tsumegoRating = (float) $previousTsumego['Tsumego']['rating'];

		// Each AJAX call is a single atomic event: fail or solve, never both.
		// Prior misplays were already processed by their own AJAX calls.
		self::processRatingChangeStep($userRating, $tsumegoRating, $result['solved']);

		Auth::saveUserField('rating', $userRating);

		$previousTsumego['Tsumego']['rating'] = Util::clampOptional(
			$tsumegoRating,
			$previousTsumego['Tsumego']['minimum_rating'],
			$previousTsumego['Tsumego']['maximum_rating']);
		$previousTsumego['Tsumego']['activity_value']++;
		ClassRegistry::init('Tsumego')->save($previousTsumego);
	}

	private function processDamage(array $result, $previousStatusValue): void
	{
		if ($result['solved'])
			return;
		if (!Auth::isInLevelMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousStatusValue))
			return;
		Auth::incrementUserField('damage', 1);
	}

	private function processXpChange(array $previousTsumego, array &$result, string $previousTsumegoStatus, $originalTsumegoRating): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result['solved'])
			return;

		$multiplier = ($result['xp-modifier'] ?: 1);
		if ($previousTsumegoStatus != 'G')
			$multiplier *= TsumegoXPAndRating::getProgressDeletionMultiplier(TsumegoUtil::getProgressDeletionCount($previousTsumego['Tsumego']));

		$user = & Auth::getUser();
		$result['xp-gained'] = Rating::ratingToXP($originalTsumegoRating, $multiplier);
		Level::addXPAsResultOfTsumegoSolving($user, $result['xp-gained']);
		Auth::saveUserFields([
			'xp' => $user['xp'],
			'level' => $user['level'],
			'daily_xp' => $user['daily_xp'],
			'daily_solved' => $user['daily_solved'],
		]);
	}

	private function processErrorAchievement(array $result, $previousTsumegoStatus, int $tsumegoID): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;

		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err']]);
		if (!$achievementCondition)
		{
			$achievementCondition = [];
			$achievementCondition['AchievementCondition']['category'] = 'err';
			$achievementCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			ClassRegistry::init('AchievementCondition')->create();
		}
		$solvedWithoutErrors = $result['solved'] && !$this->hadMisplaysBeforeSolve($tsumegoID);
		if ($solvedWithoutErrors)
			$achievementCondition['AchievementCondition']['value']++;
		else
			$achievementCondition['AchievementCondition']['value'] = 0;
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);
	}

	private function hadMisplaysBeforeSolve(int $tsumegoID): bool
	{
		$attempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $tsumegoID,
			],
			'order' => 'id DESC',
		]);
		return $attempt && (int) $attempt['TsumegoAttempt']['misplays'] > 0;
	}

	private function processUnsortedStuff(array $previousTsumego, array $result, string $previousTsumegoStatus, ?string $type = null, ?string $sprint = null): void
	{
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result['solved'])
			return;

		$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['rating']);
		AppController::saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
		AppController::updateGems($solvedTsumegoRank);
		if ($sprint === '1')
			AppController::updateSprintCondition(true);
		else
			AppController::updateSprintCondition();
		if ($type === 'g')
			AppController::updateGoldenCondition(true);
	}
}
