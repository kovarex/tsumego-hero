<?php

use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Level;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\SetCompletionCounter;
use App\Utility\TimeMode;
use App\Utility\TsumegoUtil;
use App\Utility\TsumegoXPAndRating;
use App\Utility\Util;

class PlayResultProcessorComponent extends Component
{
	//Process a play result submitted via AJAX. Takes explicit values, no cookies.
	//$seconds: Time spent on the problem, in seconds.
	//$setConnectionID: Part of the set the problem is played from; null means
	//the display set connection is deduced instead.
	//$pageMode: Mode of the page the problem was played on, see playMode(); null means the stored mode.
	//Returns result with xp_gained, new_rating, new_xp, new_level, new_damage, status,
	//potion_triggered and achievement_updates, and in the mistake training
	//mistake_training_button.
	public function processResult(int $tsumegoID, PlayOutcome $outcome, float $seconds, ?int $setConnectionID = null, ?int $pageMode = null): array
	{
		Auth::overrideMode($this->playMode($tsumegoID, $pageMode));
		try
		{
			return $this->processResultInPlayMode($tsumegoID, $outcome, $seconds, $setConnectionID);
		}
		finally
		{
			Auth::overrideMode(null);
		}
	}

	//The mode a result counts in, when it is not the stored one. The mode is stored on
	//the player, and opening another mode in another tab, or going back to a page in
	//the browser history, switches it under a page that is still open, so the page says
	//which mode it was played in. A page of the training counts as training only while
	//the problem is in today's training, so claiming it spares no hearts on any other
	//problem. A page of a normal mode counts in its own mode when the stored one is the
	//training, which is what the other tab switched to. Any other disagreement keeps
	//the stored mode.
	private function playMode(int $tsumegoID, ?int $pageMode): ?int
	{
		$storedMode = Auth::getMode();
		if ($pageMode === null || $pageMode === $storedMode)
			return null;

		if ($pageMode === Constants::$MISTAKE_TRAINING_MODE)
		{
			$row = MistakeTraining::getPoolRow((int) Auth::getUserID(), $tsumegoID);
			return $row !== null && MistakeTraining::isInTodaysTraining($row) ? $pageMode : null;
		}

		$normalModes = [Constants::$LEVEL_MODE, Constants::$RATING_MODE, Constants::$TIME_MODE];
		if ($storedMode === Constants::$MISTAKE_TRAINING_MODE && in_array($pageMode, $normalModes, true))
			return $pageMode;
		return null;
	}

	private function processResultInPlayMode(int $tsumegoID, PlayOutcome $outcome, float $seconds, ?int $setConnectionID): array
	{
		$seconds = max(0.01, $seconds);
		$solved = $outcome === PlayOutcome::SolvedClean || $outcome === PlayOutcome::SolvedAfterMistake;

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			return ['error' => 'Tsumego not found'];

		if (Auth::isInTimeMode())
		{
			$timeMode = new TimeMode();
			$playResult = ['solved' => $solved];
			$timeMode->processPlayResult($tsumego, $playResult, $seconds, $outcome === PlayOutcome::TimedOut);
		}

		$result = [];
		$result['solved'] = $solved;

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'tsumego_id' => $tsumegoID,
				'user_id' => (int) Auth::getUserID(),
			],
		]);

		$previousStatusValue = $tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : TsumegoStatus::$NOT_VISITED;
		$this->processDamage($result, $previousStatusValue);
		$newStatus = $this->updateTsumegoStatus($tsumego, $result, $tsumegoStatus);
		if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousStatusValue))
			SetCompletionCounter::updateAfterSolve((int) Auth::getUserID(), $tsumegoID);

		if (HeroPowers::getSprintRemainingSeconds() > 0)
			$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SPRINT_MULTIPLIER;

		$originalTsumegoRating = $tsumego['Tsumego']['rating'];

		// the resets of the part of the set the problem is played from, in the last month
		$progressDeletionCount = ProgressDeletion::countRecentForProblem(Auth::getUserID(), $tsumegoID, $setConnectionID);

		$lastTsumegoAttempt = TsumegoAttempt::findLast((int) Auth::getUserID(), $tsumegoID);

		$this->processRatingChange($tsumego, $result, $previousStatusValue, $progressDeletionCount, TsumegoAttempt::failsInARow($lastTsumegoAttempt));
		if (!$result['solved'])
			$result['potion_triggered'] = $this->processPotion();
		$this->processXpChange($tsumego, $result, $previousStatusValue, $originalTsumegoRating, $progressDeletionCount);
		$this->updateTsumegoAttempt($tsumego, $result, $previousStatusValue, $seconds, $lastTsumegoAttempt);
		ClassroomAssignment::recordResult((int) Auth::getUserID(), $tsumegoID, $outcome);
		// The pool is fed from every mode: a solved problem that goes wrong enters the
		// review ladder, a clean solve climbs it. The training says it is the one playing, since a replay there counts for nothing,
		// and so does the time mode, the one mode where a solved problem is not free play.
		$trainingXp = null;
		$review = MistakeTraining::recordResult(Auth::getUserID(), $tsumegoID, $outcome, $previousStatusValue, Auth::isInMistakeTrainingMode(), Auth::isInTimeMode());
		if ($review !== null && Auth::isInMistakeTrainingMode())
			$trainingXp = $this->processTrainingXp($review, (float) $originalTsumegoRating);
		$this->processErrorAchievement($result, $previousStatusValue, $outcome);
		$this->processUnsortedStuff($tsumego, $result, $previousStatusValue);
		$this->processGoldenRun($result, $previousStatusValue);

		// Check solve-dependent achievements right away (not only on the next page
		// load) so the user sees the popup immediately after solving.
		$achievementChecker = new AchievementChecker();
		$achievementChecker->checkStandardAchievements()->finalize();

		$response = [
			'xp_gained' => $trainingXp !== null ? array_sum($trainingXp) : ($result['xp-gained'] ?? 0),
			'new_rating' => Auth::getUser()['rating'],
			'new_xp' => Auth::getUser()['xp'],
			'new_level' => Auth::getUser()['level'],
			'new_damage' => Auth::getUser()['damage'],
			'status' => $newStatus,
			'potion_triggered' => $result['potion_triggered'] ?? false,
		];
		if (!empty($achievementChecker->updated))
			$response['achievement_updates'] = $achievementChecker->updated;
		// The training row shows what today did with the problem, and this result just
		// changed it, so the page redraws the button from the review as it was stored.
		if (Auth::isInMistakeTrainingMode())
		{
			$response['mistake_training_button'] = MistakeTraining::describeTodaysReview(Auth::getUserID(), $tsumegoID);
			$response['mistake_training_xp'] = $trainingXp ?? ['review' => 0, 'graduation' => 0, 'clear' => 0];
		}

		return $response;
	}

	//The XP of a review in the training (see MistakeTraining::REVIEW_XP_SHARE). It
	//counts like the XP of a solve, for the level and the day's XP; the bonus for
	//clearing the day is not a solve, so it leaves the day's solved count alone.
	/**
	 * @param array{id: int, result: int, climbed: bool, clear_bonus: bool} $review
	 * @return array{review: int, graduation: int, clear: int}
	 */
	private function processTrainingXp(array $review, float $problemRating): array
	{
		$xp = MistakeTraining::awardXp((int) Auth::getUserID(), $review, $problemRating);
		if (array_sum($xp) == 0)
			return $xp;

		$user = & Auth::getUser();
		if ($xp['review'] + $xp['graduation'] > 0)
			Level::addXPAsResultOfTsumegoSolving($user, $xp['review'] + $xp['graduation']);
		if ($xp['clear'] > 0)
		{
			Level::addXP($user, $xp['clear']);
			$user['daily_xp'] += $xp['clear'];
		}
		Auth::saveUserFields([
			'xp' => $user['xp'],
			'level' => $user['level'],
			'daily_xp' => $user['daily_xp'],
			'daily_solved' => $user['daily_solved'],
		]);
		return $xp;
	}

	//Marks a tsumego as visited (status 'V') if no status exists yet.
	public function markAsVisited(int $tsumegoID): void
	{
		Util::execute(
			'INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = status',
			[Auth::getUserID(), $tsumegoID, TsumegoStatus::$VISITED]
		);
	}

	//Potion: triggers after a fail when damage meets or exceeds max health.
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

		AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_POTION);
		return false;
	}

	private function getNewStatus(bool $solved, string $currentStatus, array &$result): string
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

	private function updateTsumegoStatus(array $previousTsumego, array &$result, ?array $previousTsumegoStatus): string
	{
		if (Auth::isInMistakeTrainingMode())
			return $previousTsumegoStatus['TsumegoStatus']['status'] ?? TsumegoStatus::$NOT_VISITED;

		if ($previousTsumegoStatus == null)
		{
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = TsumegoStatus::$VISITED;
		}

		$newStatus = $this->getNewStatus($result['solved'], $previousTsumegoStatus['TsumegoStatus']['status'], $result);

		if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousTsumegoStatus['TsumegoStatus']['status']))
			Auth::incrementUserField('solved', 1);
		$previousTsumegoStatus['TsumegoStatus']['status'] = $newStatus;
		ClassRegistry::init('TsumegoStatus')->save($previousTsumegoStatus);
		return $newStatus;
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result, string $previousTsumegoStatus, float $seconds, ?array $lastTsumegoAttempt): void
	{
		if (Auth::isInTimeMode() || Auth::isInMistakeTrainingMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousTsumegoStatus))
			return;

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

	private static function processRatingChangeStep(float &$userRating, float &$tsumegoRating, bool $isWin): void
	{
		$userRatingDelta = Rating::calculateRatingChange($userRating, $tsumegoRating, $isWin ? 1 : 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$tsumegoRatingDelta = Rating::calculateRatingChange($tsumegoRating, $userRating, $isWin ? 0 : 1, Constants::$TSUMEGO_RATING_CALCULATION_MODIFIER);
		$userRating += $userRatingDelta;
		$tsumegoRating += $tsumegoRatingDelta;
	}

	private function processRatingChange(array &$previousTsumego, array $result, string $previousTsumegoStatus, int $progressDeletionCount, int $failsInARow): void
	{
		if (!Auth::ratingisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;

		// After a reset of its part in the last month the problem is known, solving it again says little
		// about the player or the problem, so neither rating changes.
		if ($progressDeletionCount != 0)
			return;

		// Failing the same problem again and again tells nothing new about the player or the problem,
		// so only the first fails in a row change the ratings, until the problem is solved.
		if (!$result['solved'] && $failsInARow >= Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW)
			return;

		$userRating = (float) Auth::getUser()['rating'];
		$originalTsumegoRating = (float) $previousTsumego['Tsumego']['rating'];
		$tsumegoRating = $originalTsumegoRating;

		// Each AJAX call is a single atomic event: fail or solve, never both.
		// Prior misplays were already processed by their own AJAX calls.
		self::processRatingChangeStep($userRating, $tsumegoRating, $result['solved']);

		Auth::saveUserFields([
			'rating' => $userRating,
			'highest_rating' => max((float) Auth::getUser()['highest_rating'], $userRating)]);

		$previousTsumego['Tsumego']['rating'] = Util::clampOptional(
			$tsumegoRating,
			$previousTsumego['Tsumego']['minimum_rating'],
			$previousTsumego['Tsumego']['maximum_rating']);
		Tsumego::addToRating((int) $previousTsumego['Tsumego']['id'], $tsumegoRating - $originalTsumegoRating);
	}

	private function processDamage(array $result, string $previousStatusValue): void
	{
		if ($result['solved'])
			return;
		if (!Auth::isInLevelMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousStatusValue))
			return;
		Auth::incrementUserField('damage', 1);
	}

	private function processXpChange(array $previousTsumego, array &$result, string $previousTsumegoStatus, int|float|string $originalTsumegoRating, int $progressDeletionCount): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result['solved'])
			return;

		$multiplier = ($result['xp-modifier'] ?: 1);
		if ($previousTsumegoStatus != TsumegoStatus::$GOLDEN)
			$multiplier *= TsumegoXPAndRating::getProgressDeletionMultiplier($progressDeletionCount);

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

	private function processErrorAchievement(array $result, string $previousTsumegoStatus, PlayOutcome $outcome): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;

		// The streak counts the problems solved since the last error: only a clean solve
		// extends it, anything else resets it.
		if ($outcome === PlayOutcome::SolvedClean)
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_NO_ERROR_STREAK);
		else
			AchievementCondition::put(Auth::getUserID(), AchievementCondition::CATEGORY_NO_ERROR_STREAK, 0);
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
		if (isset(AchievementCondition::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]))
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]);
		DayRecord::countGemSolve($solvedTsumegoRank);
		//The sprint run counts the problems solved in a row while a sprint is active (user.sprint_start)
		if (HeroPowers::getSprintRemainingSeconds() > 0)
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_SPRINT);
		else
			AchievementCondition::put(Auth::getUserID(), AchievementCondition::CATEGORY_SPRINT, 0);
	}

	//The Gold Digger run counts the golden tsumego solved in a row. A golden tsumego is a
	//single attempt, so a solve moves the run one further and a fail starts it over.
	private function processGoldenRun(array $result, string $previousTsumegoStatus): void
	{
		if (Auth::isInMistakeTrainingMode())
			return;
		if ($previousTsumegoStatus != TsumegoStatus::$GOLDEN)
			return;
		if ($result['solved'])
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_GOLDEN);
		else
			AchievementCondition::put(Auth::getUserID(), AchievementCondition::CATEGORY_GOLDEN, 0);
	}
}
