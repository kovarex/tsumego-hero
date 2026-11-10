<?php

namespace App\Controller\Component;

use Cake\Controller\Component;
use App\Model\Entity\Tsumego;
use App\Model\Entity\TsumegoAttempt;
use App\Model\Entity\TsumegoStatus;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\DayRecordTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\TsumegoAttemptTable;
use App\Model\Table\TsumegoTable;
use App\Model\Table\TsumegoStatusTable;
use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\DailyStreak;
use App\Utility\ChallengeFailedLine;
use App\Utility\HeroPowers;
use App\Utility\Level;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\PlayResult;
use App\Utility\Rating;
use App\Utility\SetCompletionCounter;
use App\Utility\SetPartBestScores;
use App\Utility\TimeMode;
use App\Utility\TsumegoUtil;
use App\Utility\TsumegoXPAndRating;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

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
	//$challengeFailedLine: What the player played until a fail or a time-out, kept for a run of a challenge.
	public function processResult(int $tsumegoID, PlayOutcome $outcome, float $seconds, ?int $setConnectionID = null, ?int $pageMode = null, ?ChallengeFailedLine $challengeFailedLine = null): array
	{
		Auth::overrideMode($this->playMode($tsumegoID, $pageMode));
		try
		{
			return $this->processResultInPlayMode($tsumegoID, $outcome, $seconds, $setConnectionID, $challengeFailedLine);
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

	private function processResultInPlayMode(int $tsumegoID, PlayOutcome $outcome, float $seconds, ?int $setConnectionID, ?ChallengeFailedLine $challengeFailedLine): array
	{
		$seconds = max(0.01, $seconds);
		//The first result of the day fixes the XP the daily streak asks by the rating before it
		$ratingBefore = (float) Auth::getUser()['rating'];
		$result = new PlayResult($outcome === PlayOutcome::SolvedClean || $outcome === PlayOutcome::SolvedAfterMistake);

		$tsumego = TableRegistry::getTableLocator()->get('Tsumego')->find()->where(['id' => $tsumegoID])->first();
		if (!$tsumego)
			return ['error' => 'Tsumego not found'];

		if (Auth::isInTimeMode())
			new TimeMode()->processPlayResult($tsumego, $result->solved, $seconds, $outcome === PlayOutcome::TimedOut, $challengeFailedLine);

		// updated isn't loaded: the database moves it when the status changes
		$tsumegoStatus = TableRegistry::getTableLocator()->get('TsumegoStatus')->find()
			->select(['id', 'status'])
			->where(['tsumego_id' => $tsumegoID, 'user_id' => Auth::getUserID()])
			->first();

		$previousStatusValue = $tsumegoStatus ? $tsumegoStatus->status : TsumegoStatusTable::$NOT_VISITED;
		$this->processDamage($result, $previousStatusValue);
		$newStatus = $this->updateTsumegoStatus($tsumego, $result, $tsumegoStatus);
		if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousStatusValue))
			SetCompletionCounter::updateAfterSolve((int) Auth::getUserID(), $tsumegoID);

		if (HeroPowers::getSprintRemainingSeconds() > 0)
			$result->xpMultiplier *= Constants::$SPRINT_MULTIPLIER;

		$originalTsumegoRating = $tsumego->rating;

		// the resets of the part of the set the problem is played from, in the last month
		$progressDeletionCount = ProgressDeletionTable::countRecentForProblem(Auth::getUserID(), $tsumegoID, $setConnectionID);

		$lastTsumegoAttempt = TsumegoAttemptTable::findLast((int) Auth::getUserID(), $tsumegoID);

		$this->processRatingChange($tsumego, $result, $previousStatusValue, $progressDeletionCount, TsumegoAttemptTable::failsInARow($lastTsumegoAttempt));
		if (!$result->solved)
			$result->potionTriggered = $this->processPotion();
		$this->processXpChange($result, $previousStatusValue, $originalTsumegoRating, $progressDeletionCount);
		$this->updateTsumegoAttempt($tsumego, $result, $previousStatusValue, $seconds, $lastTsumegoAttempt);
		// the solve that finishes a part of a collection keeps the scores of the run through it as the player's best
		// there, once its attempt is written (SetPartBestScores)
		$finishedParts = TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousStatusValue)
			? SetPartBestScores::recordAfterSolve((int) Auth::getUserID(), $tsumegoID) : [];
		ClassroomAssignmentTable::recordResult((int) Auth::getUserID(), $tsumegoID, $outcome);
		// The pool is fed from every mode: a solved problem that goes wrong enters the
		// review ladder, a clean solve climbs it. The training says it is the one playing, since a replay there counts for nothing,
		// and so does the time mode, the one mode where a solved problem is not free play.
		$trainingXp = null;
		$review = MistakeTraining::recordResult(Auth::getUserID(), $tsumegoID, $outcome, $previousStatusValue, Auth::isInMistakeTrainingMode(), Auth::isInTimeMode());
		if ($review !== null && Auth::isInMistakeTrainingMode())
			$trainingXp = $this->processTrainingXp($review, (float) $originalTsumegoRating);
		$this->processErrorAchievement($previousStatusValue, $outcome);
		$this->processSolveConditions($tsumego, $result, $previousStatusValue);
		$this->processGoldenRun($result, $previousStatusValue);
		$this->processDailyStreak($result, $review, $trainingXp, $ratingBefore);
		$this->writeUserChanges();

		// Check solve-dependent achievements right away (not only on the next page
		// load) so the user sees the popup immediately after solving.
		$achievementChecker = new AchievementChecker();
		$achievementChecker->checkStandardAchievements();
		foreach ($finishedParts as $part)
			$achievementChecker->checkSetAchievements($part['set_id'], $part['scores']->rating, $part['partition']);
		$achievementChecker->finalize();

		$response = [
			'xp_gained' => $trainingXp !== null ? array_sum($trainingXp) : $result->xpGained,
			'new_rating' => Auth::getUser()['rating'],
			'new_xp' => Auth::getUser()['xp'],
			'new_level' => Auth::getUser()['level'],
			'new_damage' => Auth::getUser()['damage'],
			'status' => $newStatus,
			'potion_triggered' => $result->potionTriggered,
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

	//The steps of a result change the player's row one after another; each change is made on the player
	//at once, so the next steps see it, and they are written together by writeUserChanges(). A count that
	//only goes up (solved, damage) is written as an addition, so two results of the same play that arrive
	//together both count.
	/** @param array<string, mixed> $fieldValues */
	private function setUserFields(array $fieldValues): void
	{
		$user = &Auth::getUser();
		foreach ($fieldValues as $field => $value)
		{
			$user[$field] = $value;
			$this->userFieldValues[$field] = $value;
			unset($this->userFieldIncrements[$field]);
		}
	}

	private function incrementUserField(string $field, int $delta): void
	{
		$user = &Auth::getUser();
		$user[$field] += $delta;
		if (array_key_exists($field, $this->userFieldValues))
			$this->userFieldValues[$field] = $user[$field];
		else
			$this->userFieldIncrements[$field] = ($this->userFieldIncrements[$field] ?? 0) + $delta;
	}

	//One UPDATE of the player's row for the whole result, see setUserFields(). The values go as strings,
	//as in Auth::saveUserFields(), so MySQL rounds a float into an integer column.
	private function writeUserChanges(): void
	{
		if (!$this->userFieldValues && !$this->userFieldIncrements)
			return;
		$assignments = [];
		$parameters = [];
		foreach ($this->userFieldValues as $field => $value)
		{
			$assignments[] = '`' . $field . '` = ?';
			$parameters[] = is_bool($value) ? (int) $value : $value;
		}
		foreach ($this->userFieldIncrements as $field => $delta)
		{
			$assignments[] = '`' . $field . '` = `' . $field . '` + ?';
			$parameters[] = $delta;
		}
		Util::execute('UPDATE user SET ' . implode(', ', $assignments) . ' WHERE id = ?', [...$parameters, Auth::getUserID()]);
		$this->userFieldValues = [];
		$this->userFieldIncrements = [];
	}

	/** @var array<string, mixed> */
	private array $userFieldValues = [];
	/** @var array<string, int> */
	private array $userFieldIncrements = [];

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
		$this->setUserFields([
			'xp' => $user['xp'],
			'level' => $user['level'],
			'daily_xp' => $user['daily_xp'],
			'daily_solved' => $user['daily_solved'],
		]);
		return $xp;
	}

	//Potion: triggers after a fail when damage meets or exceeds max health.
	private function processPotion(): bool
	{
		if (Auth::isInMistakeTrainingMode())
			return false;
		if (!HeroPowers::canPotionTrigger())
			return false;

		$excessDeaths = Auth::getUser()['damage'] - Util::getHealthBasedOnLevel(Auth::getUser()['level']);
		$chance = min($excessDeaths * HeroPowers::$POTION_CHANCE_PER_DEATH, 100);

		if (rand(1, 100) <= $chance)
		{
			$this->setUserFields([
				'damage' => 0,
				'used_potion' => 1,
			]);
			return true;
		}

		AchievementConditionTable::increment(Auth::getUserID(), AchievementConditionTable::CATEGORY_POTION);
		return false;
	}

	//The status a result leaves the problem in, and the XP multiplier of solving it from the one it had.
	private function getNewStatus(string $currentStatus, PlayResult $result): string
	{
		if ($result->solved)
		{
			// the second solve of a problem in review masters it, for less XP
			if ($currentStatus == TsumegoStatusTable::$REVIEW)
			{
				$result->xpMultiplier *= Constants::$SECOND_SOLVE_XP_MULTIPLIER;
				return TsumegoStatusTable::$MASTERED;
			}
			if ($currentStatus == TsumegoStatusTable::$GOLDEN)
			{
				$result->xpMultiplier *= Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER;
				return TsumegoStatusTable::$SOLVED;
			}
			if ($currentStatus == TsumegoStatusTable::$VISITED || $currentStatus == TsumegoStatusTable::$NOT_VISITED)
				return TsumegoStatusTable::$SOLVED;
			// a failed problem stays failed until the next day or a rejuvenation
			return $currentStatus;
		}

		// a fail marks the problem only when it costs the last heart, and never touches a solved one
		$outOfHearts = Auth::getUser()['damage'] > Util::getHealthBasedOnLevel(Auth::getUser()['level']);
		if ($currentStatus == TsumegoStatusTable::$VISITED)
			return $outOfHearts ? TsumegoStatusTable::$LOCKED : $currentStatus;
		if ($currentStatus == TsumegoStatusTable::$REVIEW)
			return $outOfHearts ? TsumegoStatusTable::$FORGOTTEN : $currentStatus;
		if ($currentStatus == TsumegoStatusTable::$GOLDEN)
			return TsumegoStatusTable::$VISITED;
		return $currentStatus;
	}

	private function updateTsumegoStatus(Tsumego $previousTsumego, PlayResult $result, ?TsumegoStatus $previousTsumegoStatus): string
	{
		if (Auth::isInMistakeTrainingMode())
			return $previousTsumegoStatus->status ?? TsumegoStatusTable::$NOT_VISITED;

		$statusTable = TableRegistry::getTableLocator()->get('TsumegoStatus');
		$previousTsumegoStatus ??= $statusTable->newEntity([
			'user_id' => Auth::getUserID(),
			'tsumego_id' => $previousTsumego->id,
			'status' => TsumegoStatusTable::$VISITED]);

		$newStatus = $this->getNewStatus($previousTsumegoStatus->status, $result);

		if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousTsumegoStatus->status))
			$this->incrementUserField('solved', 1);
		$previousTsumegoStatus->status = $newStatus;
		$statusTable->saveOrFail($previousTsumegoStatus);
		return $newStatus;
	}

	private function updateTsumegoAttempt(Tsumego $previousTsumego, PlayResult $result, string $previousTsumegoStatus, float $seconds, ?TsumegoAttempt $lastTsumegoAttempt): void
	{
		if (Auth::isInTimeMode() || Auth::isInMistakeTrainingMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousTsumegoStatus))
			return;

		// only not solved ones are updated (misplays get accumulated), and not across a reset: an attempt failed before
		// the reset of the problem's part belongs to the run the reset ended, the play after it starts an attempt of its own
		$continued = $lastTsumegoAttempt && !$lastTsumegoAttempt->solved
			&& !ProgressDeletionTable::resetSince((int) Auth::getUserID(), $previousTsumego->id, $lastTsumegoAttempt->created->format('Y-m-d H:i:s'))
			? $lastTsumegoAttempt : null;
		$values = [
			Auth::getUser()['rating'],
			$result->xpGained,
			($continued ? $continued->seconds : 0) + $seconds,
			$result->solved ? 1 : 0,
			$previousTsumego->rating,
			($continued ? $continued->misplays : 0) + ($result->solved ? 0 : 1),
			date('Y-m-d H:i:s')];
		// Plain SQL: the ratings and the seconds are floats that MySQL rounds into the integer columns,
		// as it did for the Cake 2 save, where the ORM would cut off their fractions.
		if ($continued)
			Util::execute('UPDATE tsumego_attempt SET user_rating = ?, gain = ?, seconds = ?, solved = ?, tsumego_rating = ?, misplays = ?, created = ? WHERE id = ?',
				[...$values, $continued->id]);
		else
			Util::execute('INSERT INTO tsumego_attempt (user_rating, gain, seconds, solved, tsumego_rating, misplays, created, user_id, tsumego_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				[...$values, Auth::getUserID(), $previousTsumego->id]);
	}

	private static function processRatingChangeStep(float &$userRating, float &$tsumegoRating, bool $isWin): void
	{
		$userRatingDelta = Rating::calculateRatingChange($userRating, $tsumegoRating, $isWin ? 1 : 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$tsumegoRatingDelta = Rating::calculateRatingChange($tsumegoRating, $userRating, $isWin ? 0 : 1, Constants::$TSUMEGO_RATING_CALCULATION_MODIFIER);
		$userRating += $userRatingDelta;
		$tsumegoRating += $tsumegoRatingDelta;
	}

	private function processRatingChange(Tsumego $previousTsumego, PlayResult $result, string $previousTsumegoStatus, int $progressDeletionCount, int $failsInARow): void
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
		if (!$result->solved && $failsInARow >= Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW)
			return;

		$userRating = (float) Auth::getUser()['rating'];
		$originalTsumegoRating = $previousTsumego->rating;
		$tsumegoRating = $originalTsumegoRating;

		// Each AJAX call is a single atomic event: fail or solve, never both.
		// Prior misplays were already processed by their own AJAX calls.
		self::processRatingChangeStep($userRating, $tsumegoRating, $result->solved);

		$this->setUserFields([
			'rating' => $userRating,
			'highest_rating' => max((float) Auth::getUser()['highest_rating'], $userRating)]);

		$previousTsumego->rating = Util::clampOptional(
			$tsumegoRating,
			$previousTsumego->minimum_rating,
			$previousTsumego->maximum_rating);
		TsumegoTable::addToRating($previousTsumego->id, $tsumegoRating - $originalTsumegoRating);
	}

	private function processDamage(PlayResult $result, string $previousStatusValue): void
	{
		if ($result->solved)
			return;
		if (!Auth::isInLevelMode())
			return;
		if (TsumegoUtil::isRecentlySolved($previousStatusValue))
			return;
		$this->incrementUserField('damage', 1);
	}

	private function processXpChange(PlayResult $result, string $previousTsumegoStatus, int|float|string $originalTsumegoRating, int $progressDeletionCount): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result->solved)
			return;

		$multiplier = $result->xpMultiplier;
		if ($previousTsumegoStatus != TsumegoStatusTable::$GOLDEN)
			$multiplier *= TsumegoXPAndRating::getProgressDeletionMultiplier($progressDeletionCount);

		$user = & Auth::getUser();
		$result->xpGained = Rating::ratingToXP($originalTsumegoRating, $multiplier);
		Level::addXPAsResultOfTsumegoSolving($user, $result->xpGained);
		$this->setUserFields([
			'xp' => $user['xp'],
			'level' => $user['level'],
			'daily_xp' => $user['daily_xp'],
			'daily_solved' => $user['daily_solved'],
		]);
	}

	private function processErrorAchievement(string $previousTsumegoStatus, PlayOutcome $outcome): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;

		// The streak counts the problems solved since the last error: only a clean solve
		// extends it, anything else resets it.
		if ($outcome === PlayOutcome::SolvedClean)
			AchievementConditionTable::increment(Auth::getUserID(), AchievementConditionTable::CATEGORY_NO_ERROR_STREAK);
		else
			AchievementConditionTable::put(Auth::getUserID(), AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 0);
	}

	//What a solve counts for besides the XP: the solves of a dan rank, the gem of the day and the sprint run.
	private function processSolveConditions(Tsumego $previousTsumego, PlayResult $result, string $previousTsumegoStatus): void
	{
		if (Auth::isInMistakeTrainingMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result->solved)
			return;

		$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego->rating);
		if (isset(AchievementConditionTable::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]))
			AchievementConditionTable::increment(Auth::getUserID(), AchievementConditionTable::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]);
		DayRecordTable::countGemSolve($solvedTsumegoRank);
		//The sprint run counts the problems solved in a row while a sprint is active (user.sprint_start)
		if (HeroPowers::getSprintRemainingSeconds() > 0)
			AchievementConditionTable::increment(Auth::getUserID(), AchievementConditionTable::CATEGORY_SPRINT);
		else
			AchievementConditionTable::put(Auth::getUserID(), AchievementConditionTable::CATEGORY_SPRINT, 0);
	}

	//What the result does for the daily streak (DailyStreak::afterResult): the XP it gave, and in the
	//mistake training every review the training counts as solved, even a retry that earns nothing.
	//A replay of a problem solved today is no review, and the time mode gives no XP, so neither counts.
	//The XP the day asks is that of $ratingBefore, the rating before this result changed it.
	//The changes go with the other fields of the player, in the one UPDATE of the result.
	/**
	 * @param array{id: int, result: int, climbed: bool, clear_bonus: bool}|null $review
	 * @param array{review: int, graduation: int, clear: int}|null $trainingXp
	 */
	private function processDailyStreak(PlayResult $result, ?array $review, ?array $trainingXp, float $ratingBefore): void
	{
		$solvedReview = Auth::isInMistakeTrainingMode() && $review !== null && $review['result'] !== MistakeTraining::REVIEW_MISSED;
		$xp = (int) $result->xpGained + ($trainingXp !== null ? array_sum($trainingXp) : 0);
		$userId = (int) Auth::getUserID();
		$this->setUserFields(DailyStreak::afterResult(['rating' => $ratingBefore] + Auth::getUser(), $xp, $solvedReview, fn() => MistakeTraining::dueCount($userId)));
	}

	//The Gold Digger run counts the golden tsumego solved in a row. A golden tsumego is a
	//single attempt, so a solve moves the run one further and a fail starts it over.
	private function processGoldenRun(PlayResult $result, string $previousTsumegoStatus): void
	{
		if (Auth::isInMistakeTrainingMode())
			return;
		if ($previousTsumegoStatus != TsumegoStatusTable::$GOLDEN)
			return;
		if ($result->solved)
			AchievementConditionTable::increment(Auth::getUserID(), AchievementConditionTable::CATEGORY_GOLDEN);
		else
			AchievementConditionTable::put(Auth::getUserID(), AchievementConditionTable::CATEGORY_GOLDEN, 0);
	}
}
