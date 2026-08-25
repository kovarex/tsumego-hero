<?php

App::uses('TsumegoStatus', 'Model');
App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('TsumegoXPAndRating', 'Utility');
App::uses('Level', 'Utility');
App::uses('TimeMode', 'Utility');
App::uses('MistakeTraining', 'Utility');

class PlayResultProcessorComponent extends Component
{
	/**
	 * Process a play result submitted via AJAX. Takes explicit values, no cookies.
	 *
	 * @return array Result with xp_gained, rating_change, new_rating, etc.
	 */
	public function processResult(int $tsumegoId, bool $solved, float $seconds, bool $timeout): array
	{
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		if (!$tsumego)
			return ['error' => 'Tsumego not found'];

		$result = [];
		$result['solved'] = $solved;

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'tsumego_id' => $tsumegoId,
				'user_id' => (int) Auth::getUserID(),
			],
		]);

		$previousStatusValue = $tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : TsumegoStatus::$NOT_VISITED;
		$this->processDamage($result, $previousStatusValue);
		$newStatus = $this->updateTsumegoStatus($tsumego, $result, $tsumegoStatus);

		if (HeroPowers::getSprintRemainingSeconds() > 0)
			$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SPRINT_MULTIPLIER;

		$originalTsumegoRating = $tsumego['Tsumego']['rating'];

		$this->processRatingChange($tsumego, $result, $previousStatusValue);
		if (!$result['solved'])
			$result['potion_triggered'] = $this->processPotion();
		$this->processXpChange($tsumego, $result, $previousStatusValue, $originalTsumegoRating);
		$this->updateTsumegoAttempt($tsumego, $result, $previousStatusValue, $seconds);
		// Graduation is known only after the attempt is recorded, and
		// updateTsumegoStatus already ran before XP for the XP multiplier — so apply
		// it with a second call (it re-reads the current status itself).
		$graduated = $this->updateMistakeTraining($tsumego, $result, $tsumegoStatus);
		if ($graduated)
			$newStatus = $this->updateTsumegoStatus($tsumego, $result, $tsumegoStatus, true);
		$this->processErrorAchievement($result, $previousStatusValue, $tsumegoId);
		$this->processUnsortedStuff($tsumego, $result, $previousStatusValue);

		if (Auth::isInTimeMode())
		{
			$timeMode = new TimeMode();
			$playResult = ['solved' => $solved];
			$timeMode->processPlayResult($tsumego, $playResult, $seconds, $timeout);
		}

		// Check solve-dependent achievements right away (not only on the next page
		// load) so the user sees the popup immediately after solving.
		$achievementChecker = new AchievementChecker();
		$achievementChecker->checkStandardAchievements()->finalize();

		$response = [
			'xp_gained' => $result['xp-gained'] ?? 0,
			'new_rating' => Auth::getUser()['rating'],
			'new_xp' => Auth::getUser()['xp'],
			'new_level' => Auth::getUser()['level'],
			'new_damage' => Auth::getUser()['damage'],
			'status' => $newStatus,
			'potion_triggered' => $result['potion_triggered'] ?? false,
		];
		if (!empty($achievementChecker->updated))
			$response['achievement_updates'] = $achievementChecker->updated;

		return $response;
	}

	/**
	 * Marks a tsumego as visited (status 'V') if no status exists yet.
	 */
	public function markAsVisited(int $tsumegoID): void
	{
		Util::execute(
			'INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = status',
			[Auth::getUserID(), $tsumegoID, TsumegoStatus::$VISITED]
		);
	}

	/**
	 * Potion: triggers after a fail when damage meets or exceeds max health.
	 */
	public function processPotion(): bool
	{
		if (Auth::isInMistakeTrainingMode())
			return false;
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
			if ($currentStatus == TsumegoStatus::$REVIEW) // half xp state
			{$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SECOND_SOLVE_XP_MULTIPLIER;
				return TsumegoStatus::$MASTERED; // double solved
			}
			if ($currentStatus == TsumegoStatus::$GOLDEN)
			{
				$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER;
				return TsumegoStatus::$SOLVED;
			}
			if ($currentStatus == TsumegoStatus::$VISITED || $currentStatus == TsumegoStatus::$NOT_VISITED)
				return TsumegoStatus::$SOLVED;
			return $currentStatus; // failed can't be unfailed by solving, user has to wait until next day or rejuvenation
		}

		// not solved from now
		if ($currentStatus == TsumegoStatus::$VISITED) // if it was just visited so far (so we don't overwrite solved)
		{if (Auth::getUser()['damage'] > Util::getHealthBasedOnLevel(Auth::getUser()['level']))
			return TsumegoStatus::$LOCKED;  // only mark as failed when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == TsumegoStatus::$REVIEW)
		{
			if (Auth::getUser()['damage'] > Util::getHealthBasedOnLevel(Auth::getUser()['level']))
				return TsumegoStatus::$FORGOTTEN; // only mark as 'stale failed' when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == TsumegoStatus::$GOLDEN)
			return TsumegoStatus::$VISITED; // failed golden tsumego
		return $currentStatus;
	}

	private function updateTsumegoStatus(array $previousTsumego, array &$result, ?array $previousTsumegoStatus, bool $trainingGraduation = false): string
	{
		// On graduation the caller's row predates the pool update (mt_due cleared),
		// so re-read the current one instead of saving a stale mt_due.
		if ($trainingGraduation)
			$previousTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
				'conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $previousTsumego['Tsumego']['id']],
			]);

		if ($previousTsumegoStatus == null)
		{
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = TsumegoStatus::$VISITED;
		}

		// Training freezes status; the only exception is graduation, which marks a
		// still-unsolved (V/N) problem solved. That is a narrow transition, not a
		// normal play result, so it deliberately skips getNewStatus.
		if (Auth::isInMistakeTrainingMode())
		{
			$status = $previousTsumegoStatus['TsumegoStatus']['status'];
			if (!$trainingGraduation || ($status !== TsumegoStatus::$VISITED && $status !== TsumegoStatus::$NOT_VISITED))
				return $status;
			$newStatus = TsumegoStatus::$SOLVED;
			// NOTE: consider a small XP reward here via $result['xp-gained'] (a
			// "solved via training" bonus), like getNewStatus sets the XP modifier.
		}
		else
			$newStatus = $this->getNewStatus($result['solved'], $previousTsumegoStatus['TsumegoStatus']['status'], $result);

		if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousTsumegoStatus['TsumegoStatus']['status']))
			Auth::incrementUserField('solved', 1);
		$previousTsumegoStatus['TsumegoStatus']['status'] = $newStatus;
		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoStatus')->save($previousTsumegoStatus);
		return $newStatus;
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result, $previousTsumegoStatus, float $seconds): void
	{
		if (Auth::isInTimeMode())
			return;
		// Training deliberately ignores tsumego status (it is a level-mode
		// concern): every review must record an attempt, because the review
		// ladder is driven entirely by attempt history. Without this, a training
		// review of a problem that was solved in another mode mid-ladder (status
		// S/C but still in the pool) would silently record nothing and the
		// schedule would never move.
		if (!Auth::isInMistakeTrainingMode() && TsumegoUtil::isRecentlySolved($previousTsumegoStatus))
			return;
		// Only level mode resumes an open buffer — it is the only mode a player
		// can leave and come back to (hearts lockout). Rating/training sessions
		// always end in a solve or a fail, so they record one fresh attempt and
		// never resume. The mode filter keeps a rating/training fail row (also
		// solved=0) from being mistaken for the level buffer.
		$tsumegoAttempt = Auth::isInLevelMode()
			? ClassRegistry::init('TsumegoAttempt')->find('first', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $previousTsumego['Tsumego']['id'],
					'solved' => 0,
					'mode' => Constants::$LEVEL_MODE,
				],
				'order' => 'id DESC',
			])
			: null;

		if (!$tsumegoAttempt)
		{
			$tsumegoAttempt = [];
			$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
			$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
			$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = 0;
		}

		$tsumegoAttempt['TsumegoAttempt']['user_rating'] = Auth::getUser()['rating'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = $result['xp-gained'] ?: 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] += $seconds;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
		if ($result['solved'])
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = (int) $tsumegoAttempt['TsumegoAttempt']['misplays'];
		else
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = (int) $tsumegoAttempt['TsumegoAttempt']['misplays'] + 1;
		$tsumegoAttempt['TsumegoAttempt']['mode'] = Auth::getMode();
		$tsumegoAttempt['TsumegoAttempt']['created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	/**
	 * Keep the training pool in sync and report whether the review ladder
	 * graduated (a clean solve at the top rung), so the caller can mark the
	 * problem solved through updateTsumegoStatus — the only status writer.
	 *
	 * Entry: only first-encounter mistakes (V/N) that are not a clean first-try
	 * solve. Problems stay in the pool regardless of status; only graduation
	 * removes them.
	 */
	private function updateMistakeTraining(array $tsumego, array $result, ?array $tsumegoStatus): bool
	{
		if (!Auth::isLoggedIn())
			return false;

		$tsumegoId = (int) $tsumego['Tsumego']['id'];
		$alreadyInTraining = $tsumegoStatus && !empty($tsumegoStatus['TsumegoStatus']['mt_due']);

		if (!$alreadyInTraining)
		{
			// Entry: only first-encounter mistakes (V/N) that are not a clean first-try solve.
			$oldStatus = $tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : TsumegoStatus::$NOT_VISITED;
			if ($oldStatus !== TsumegoStatus::$VISITED && $oldStatus !== TsumegoStatus::$NOT_VISITED)
				return false;
			if ($result['solved'] && !$this->hadMisplaysBeforeSolve($tsumegoId))
				return false;
		}

		$newDue = MistakeTraining::computeNextDue(Auth::getUserID(), $tsumegoId);
		Util::execute(
			'UPDATE tsumego_status SET mt_due = ? WHERE user_id = ? AND tsumego_id = ?',
			[$newDue, Auth::getUserID(), $tsumegoId]
		);

		// Graduation: a null due for a real pool member means it left via
		// graduation.
		return $newDue === null && $alreadyInTraining;
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
		if ($previousTsumegoStatus != TsumegoStatus::$GOLDEN)
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

	private function processUnsortedStuff(array $previousTsumego, array $result, string $previousTsumegoStatus): void
	{
		if (Auth::isInMistakeTrainingMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result['solved'])
			return;

		$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['rating']);
		AppController::saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
		AppController::updateGems($solvedTsumegoRank);
		// Sprint state is server-authoritative (user.sprint_start). A solve only
		// counts toward the sprint achievement while a sprint is actually active.
		if (HeroPowers::getSprintRemainingSeconds() > 0)
			AppController::updateSprintCondition(true);
		else
			AppController::updateSprintCondition();
		if ($previousTsumegoStatus == TsumegoStatus::$GOLDEN)
			AppController::updateGoldenCondition(true);
	}
}
