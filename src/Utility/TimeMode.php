<?php

namespace App\Utility;

use App\Model\Entity\TimeModeAttempt;
use App\Model\Entity\TimeModeCategory;
use App\Model\Entity\TimeModeRank;
use App\Model\Entity\TimeModeSession;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;
use Challenge;
use ClassRegistry;
use DateTimeImmutable;
use InternalErrorException;
use NotFoundException;
use Set;

class TimeMode
{
	use LocatorAwareTrait;

	public function __construct()
	{
		if (!Auth::isInTimeMode())
			return;

		/** @var TimeModeSession|null $currentSession */
		$currentSession = $this->fetchTable('TimeModeSession')->find()->where([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS])->first();
		$this->currentSession = $currentSession;

		if (!$this->currentSession)
			return;

		// is TimeModeUtil::$PROBLEM_COUNT normally, but can be less when not enough problems found
		/** @var array<TimeModeAttempt> $attempts */
		$attempts = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $this->currentSession->id])->toArray();
		foreach ($attempts as $attempt)
		{
			if ($attempt->time_mode_attempt_status_id == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$this->successCount++;
			elseif (
				$attempt->time_mode_attempt_status_id == TimeModeUtil::$ATTEMPT_RESULT_FAILED
				|| $attempt->time_mode_attempt_status_id == TimeModeUtil::$ATTEMPT_STATUS_SKIPPED
				|| $attempt->time_mode_attempt_status_id == TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT)
					$this->failCount++;
		}
		$this->overallCount = count($attempts);

		if ($this->overallCount == 0)
		{
			$this->cancelTimeMode();
			return;
		}

		$this->currentOrder = $this->fetchTable('TimeModeAttempt')->find()->where([
			'time_mode_session_id' => $this->currentSession->id,
			'time_mode_attempt_status_id !=' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->count() + 1;

		// the run of a challenge has the time the challenge gives, a run of the time mode the time of its category
		if ($challengeID = $this->currentSession->challenge_id)
		{
			$this->challenge = ClassRegistry::init('Challenge')->findById($challengeID)['Challenge'];
			$this->overallSecondsToSolve = (int) $this->challenge['seconds_per_problem'];
			return;
		}
		/** @var TimeModeRank|null $rank */
		$rank = $this->fetchTable('TimeModeRank')->find()->where(['id' => $this->currentSession->time_mode_rank_id])->first();
		$this->rank = $rank;
		/** @var TimeModeCategory $category */
		$category = $this->fetchTable('TimeModeCategory')->get($this->currentSession->time_mode_category_id);
		$this->overallSecondsToSolve = $category->seconds;
	}

	public function startTimeMode(int $categoryID, int $rankID): void
	{
		$relevantTsumegos = $this->getRelevantTsumegos($rankID);
		if (empty($relevantTsumegos))
			throw new InternalErrorException('No relevant tsumegos.');
		$this->leaveCurrentSession();
		shuffle($relevantTsumegos);
		$currentTimeSession = $this->createNewSession($categoryID, $rankID);
		$this->createSessionAttempts($currentTimeSession->id, array_column(array_slice($relevantTsumegos, 0, TimeModeUtil::$PROBLEM_COUNT), 'id'));
	}

	//Starts the run of the current player in a challenge: the problems of the challenge in its
	//order, each with the time the challenge gives. A player has one run in a challenge.
	/** @param array<string, mixed> $challenge */
	public function startChallenge(array $challenge): void
	{
		$tsumegoIDs = Challenge::problemIDs((int) $challenge['id']);
		if (empty($tsumegoIDs))
			throw new InternalErrorException('The challenge has no problems.');
		$this->leaveCurrentSession();

		Auth::saveUserField('mode_id', Constants::$TIME_MODE);
		$sessionTable = $this->fetchTable('TimeModeSession');
		/** @var TimeModeSession $session */
		$session = $sessionTable->saveOrFail($sessionTable->newEntity([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id' => $challenge['id']]));
		$this->createSessionAttempts($session->id, $tsumegoIDs);
	}

	public static function cancelTimeMode(): void
	{
		TableRegistry::getTableLocator()->get('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
	}

	//Makes way for a new run. A run of the time mode left unfinished is thrown away. The run of a
	//challenge can't be, since the player knows its problems now and would play them again: it
	//ends with the problems left unplayed, which get no points.
	private function leaveCurrentSession(): void
	{
		$sessionTable = $this->fetchTable('TimeModeSession');
		$sessionTable->deleteAll([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id IS' => null]);
		/** @var array<TimeModeSession> $challengeSessions */
		$challengeSessions = $sessionTable->find()->where([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS])->toArray();
		foreach ($challengeSessions as $challengeSession)
			self::giveUp($challengeSession);
	}

	//Ends a run with whatever it has: every problem not played yet counts as skipped.
	private static function giveUp(TimeModeSession $session): void
	{
		$attemptTable = TableRegistry::getTableLocator()->get('TimeModeAttempt');
		/** @var array<TimeModeAttempt> $queuedAttempts */
		$queuedAttempts = $attemptTable->find()->where([
			'time_mode_session_id' => $session->id,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->toArray();
		foreach ($queuedAttempts as $attempt)
		{
			$attempt->time_mode_attempt_status_id = TimeModeUtil::$ATTEMPT_STATUS_SKIPPED;
			$attempt->seconds = '0';
			$attempt->points = '0';
			$attemptTable->saveOrFail($attempt);
		}
		self::score($session);
	}

	private function createNewSession(int $categoryID, int $rankID): TimeModeSession
	{
		$timeModeCategory = $this->fetchTable('TimeModeCategory')->find()->where(['id' => $categoryID])->first();
		if (!$timeModeCategory)
			throw new NotFoundException("Time mode session category with id=" . $categoryID . " not found");

		if (!$this->fetchTable('TimeModeRank')->exists(['id' => $rankID]))
			throw new NotFoundException("Time mode rank category with id=" . $rankID . " not found");

		Auth::saveUserField('mode_id', Constants::$TIME_MODE);
		$sessionTable = $this->fetchTable('TimeModeSession');
		/** @var TimeModeSession $session */
		$session = $sessionTable->saveOrFail($sessionTable->newEntity([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'time_mode_category_id' => $timeModeCategory->id,
			'time_mode_rank_id' => $rankID]));
		return $session;
	}

	public static function getRatingBounds(?int $timeModeRankID): RatingBounds
	{
		$timeModeRankTable = TableRegistry::getTableLocator()->get('TimeModeRank');

		// I'm assuming, that the entries in the time_mode_rank tables have primary id ordered in the same order as the ranks, so the next entry
		// is the higher rank, and the previous is the lower (checked in testTimeModeRankContentsIntegrity)
		// This allows me to figure out what range should I cover by the current rank, which is
		// <max_of_smaller_rank or 0 if it doesn't exit, max_of_current_rank if next rank exists or infinity]
		// this should put every tsumego in some of the rank intervals regardless of the ranks configuration
		$result = new RatingBounds();
		/** @var TimeModeRank|null $smallerRank */
		$smallerRank = $timeModeRankTable->find()->where(['id <' => $timeModeRankID])->orderByDesc('id')->first();
		if ($smallerRank)
			$result->min = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($smallerRank->name) + 1);

		if ($timeModeRankTable->exists(['id >' => $timeModeRankID]))
		{
			/** @var TimeModeRank $currentRank */
			$currentRank = $timeModeRankTable->get($timeModeRankID);
			$result->max = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($currentRank->name) + 1);
		}

		return $result;
	}

	private function getRelevantTsumegos(int $timeModeRankID): array
	{
		return self::getTsumegosInRatingBounds(self::getRatingBounds($timeModeRankID));
	}

	//The problems the time mode draws from, of the ranks from one to another (both included).
	/** @return array<array{id: int}> */
	public static function getTsumegosOfRanks(int $minimumTimeModeRankID, int $maximumTimeModeRankID): array
	{
		return self::getTsumegosInRatingBounds(new RatingBounds(
			self::getRatingBounds($minimumTimeModeRankID)->min,
			self::getRatingBounds($maximumTimeModeRankID)->max));
	}

	/** @return array<array{id: int}> */
	private static function getTsumegosInRatingBounds(RatingBounds $ratingBounds): array
	{
		$query = "SELECT DISTINCT tsumego.id as id FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id JOIN `set` ON set.id = set_connection.set_id WHERE tsumego.deleted IS NULL AND `set`.included_in_time_mode = TRUE AND " . Set::officialCondition();
		if ($ratingBounds->min)
			$query .= " AND rating >= " . $ratingBounds->min;
		if ($ratingBounds->max)
			$query .= " AND rating < " . $ratingBounds->max;
		return Util::query($query);
	}

	/** @param array<int|string> $tsumegoIDs The problems of the run in the order they are played */
	private function createSessionAttempts(int $timeModeSessionID, array $tsumegoIDs): void
	{
		$attemptTable = $this->fetchTable('TimeModeAttempt');
		foreach (array_values($tsumegoIDs) as $i => $tsumegoID)
			$attemptTable->saveOrFail($attemptTable->newEntity([
				'time_mode_session_id' => $timeModeSessionID,
				'order' => $i + 1,
				'tsumego_id' => $tsumegoID,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]));
	}

	private static function deduceAttemptStatus(array $result, bool $timeout): int
	{
		if ($timeout)
			return TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT;
		if (!$result['solved'])
			return TimeModeUtil::$ATTEMPT_RESULT_FAILED;
		return TimeModeUtil::$ATTEMPT_RESULT_SOLVED;
	}

	public function processPlayResult(array $previousTsumego, array $result, float $seconds = 0, bool $timeout = false): void
	{
		if (!$this->currentSession)
			return;
		$attemptTable = $this->fetchTable('TimeModeAttempt');
		/** @var TimeModeAttempt|null $currentAttempt */
		$currentAttempt = $attemptTable->find()->where([
			'time_mode_session_id' => $this->currentSession->id,
			'tsumego_id' => $previousTsumego['Tsumego']['id'],
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->first();
		if (!$currentAttempt)
			return; // this tsumego is not related to our time mode session, we ignore it
		$currentAttempt->time_mode_attempt_status_id = self::deduceAttemptStatus($result, $timeout);
		if ($timeout)
			$seconds = $this->overallSecondsToSolve;

		$seconds = min($seconds, $this->overallSecondsToSolve);

		$currentAttempt->seconds = (string) $seconds;
		$currentAttempt->points = (string) ($result['solved'] ? self::calculatePoints($seconds, $this->overallSecondsToSolve) : 0);
		$attemptTable->saveOrFail($currentAttempt);
		$this->currentOrder++;
	}

	public function skip(int $position): void
	{
		if (!$this->currentSession)
			return;
		$attempt = $this->findAttemptToSkip($position);
		if (!$attempt)
			return;
		$attempt->time_mode_attempt_status_id = TimeModeUtil::$ATTEMPT_STATUS_SKIPPED;
		$attempt->seconds = (string) min($this->overallSecondsToSolve, time() - $attempt->started->getTimestamp());
		$attempt->points = '0';
		$this->fetchTable('TimeModeAttempt')->saveOrFail($attempt);
		$this->currentOrder++;
	}

	//The problem to give up on: the one at the given position, when it is being played.
	private function findAttemptToSkip(int $position): ?TimeModeAttempt
	{
		/** @var TimeModeAttempt|null $attempt */
		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where([
			'time_mode_session_id' => $this->currentSession->id,
			'order' => $position,
			'started IS NOT' => null,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->orderByDesc('id')->first();
		return $attempt;
	}

	//The problem to present to the player: the one at this position when it is still open,
	//and otherwise the first one that follows it. A problem can stay unrecorded behind the
	//player, so when nothing from this position on is open, that is what they get to play.
	//Returns the tsumego id to show, or null when there is nothing to play
	public function prepareToSolve(int $position): ?int
	{
		if (!$this->currentSession)
			return null;

		// Past the last problem: the session is over, but its score cannot be computed
		// while a result is still on its way.
		if ($position > $this->overallCount && $this->hasAResultOnItsWay())
			return null;

		return $this->serve($this->findOpenAttemptFrom($position) ?: $this->findOpenAttempt());
	}

	//The problem to present when no position was asked for: the first one that is still open.
	//Returns the tsumego id to show, or null when there is nothing to play
	public function prepareWhateverIsLeft(): ?int
	{
		if (!$this->currentSession)
			return null;

		return $this->serve($this->findOpenAttempt());
	}

	//Starts the clock on the problem to play, or gives up on it when its time ran out.
	private function serve(?TimeModeAttempt $attempt): ?int
	{
		$this->secondsToSolve = $this->overallSecondsToSolve;
		$this->servedOrder = null;
		if (!$attempt)
			return null;

		$this->servedOrder = $attempt->order;

		if (!$attempt->started)
		{
			// in whole seconds, as date('Y-m-d H:i:s.u') stored it before (date() has no microseconds)
			$attempt->started = new DateTimeImmutable(date('Y-m-d H:i:s'));
			$this->fetchTable('TimeModeAttempt')->saveOrFail($attempt);
		}
		else
		{
			$start = $attempt->started;
			$now = new DateTimeImmutable();

			$secondsSinceStarted = $now->getTimestamp() + $now->format('u') / 1e6 - ($start->getTimestamp() + $start->format('u') / 1e6);
			$this->secondsToSolve = max(0, $this->secondsToSolve - $secondsSinceStarted);
			if ($this->secondsToSolve == 0)
			{
				$attempt->time_mode_attempt_status_id = TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT;
				$attempt->seconds = (string) $this->overallSecondsToSolve;
				$attempt->points = '0';
				$this->fetchTable('TimeModeAttempt')->saveOrFail($attempt);
				$this->currentOrder++;
				return $this->prepareWhateverIsLeft();
			}
		}
		return $attempt->tsumego_id;
	}

	//The first problem from this position on that has not been recorded yet.
	private function findOpenAttemptFrom(int $position): ?TimeModeAttempt
	{
		/** @var TimeModeAttempt|null $attempt */
		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where([
			'time_mode_session_id' => $this->currentSession->id,
			'order >=' => $position,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->orderBy('order')->first();
		return $attempt;
	}

	//The first problem of the session that has not been recorded yet.
	private function findOpenAttempt(): ?TimeModeAttempt
	{
		/** @var TimeModeAttempt|null $attempt */
		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where([
			'time_mode_session_id' => $this->currentSession->id,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED])->orderBy('id')->first();
		return $attempt;
	}

	//True while a problem has been played but its result has not been recorded yet.
	private function hasAResultOnItsWay(): bool
	{
		return $this->fetchTable('TimeModeAttempt')->exists([
			'time_mode_session_id' => $this->currentSession->id,
			'started IS NOT' => null,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]);
	}

	public function currentWillBeLast(): bool
	{
		return $this->servedOrder !== null && $this->servedOrder == $this->overallCount;
	}

	//The position to show: the problem being presented, or when none is being
	//presented the first one that has not been recorded yet.
	public function currentPosition(): ?int
	{
		return $this->servedOrder ?? $this->currentOrder;
	}

	//True while the session is still being played, so it has no score to show yet.
	//The score is computed from the recorded attempts, and the recording of a result
	//lags behind the play, so a session can be over and still have attempts queued.
	public function hasNoResultYet(int $sessionID): bool
	{
		if (!$this->fetchTable('TimeModeSession')->exists([
			'id' => $sessionID,
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]))
				return false;

		return $this->fetchTable('TimeModeAttempt')->exists([
			'time_mode_session_id' => $sessionID,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]);
	}

	public function checkFinishSession(): ?int
	{
		if (!$this->currentSession)
			return null;

		if ($this->currentOrder - 1 < $this->overallCount)
			return null;

		self::score($this->currentSession);
		return $this->currentSession->id;
	}

	//Ends a run whose every problem has its result: sums up the points and decides whether it
	//passed, and gets the player back to the level mode.
	private static function score(TimeModeSession $session): void
	{
		/** @var array<TimeModeAttempt> $attempts */
		$attempts = TableRegistry::getTableLocator()->get('TimeModeAttempt')->find()->where(['time_mode_session_id' => $session->id])->toArray();

		$overallPoints = 0.0;
		$correctCount = 0;
		foreach ($attempts as $attempt)
		{
			$overallPoints += (float) $attempt->points;
			if ($attempt->time_mode_attempt_status_id == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$correctCount++;
		}

		$sessionSuccessful = Util::getRatio($correctCount, count($attempts)) >= TimeModeUtil::$RATIO_OF_SOLVED_TO_SUCCEED;
		$session->time_mode_session_status_id = $sessionSuccessful ? TimeModeUtil::$SESSION_STATUS_SOLVED : TimeModeUtil::$SESSION_STATUS_FAILED;
		$session->points = (string) $overallPoints;
		TableRegistry::getTableLocator()->get('TimeModeSession')->saveOrFail($session);
		Auth::saveUserField('mode_id', Constants::$LEVEL_MODE);
	}

	public static function calculatePoints(float $timeUsed, float $max): float
	{
		$timeRatio = 1 - ($timeUsed / $max);
		return min(100 * (TimeModeUtil::$POINTS_RATIO_FOR_FINISHING + (1 - TimeModeUtil::$POINTS_RATIO_FOR_FINISHING) * $timeRatio), 100.0);
	}

	public function exportTimeModeInfo(): string
	{
		if (!$this->currentSession)
			return 'null';
		return 'new TimeModeState({'
			. 'title: ' . json_encode($this->getTitle()) . ','
			. 'failCount: ' . $this->failCount . ','
			. 'successCount: ' . $this->successCount . ','
			. 'overallCount: ' . $this->overallCount . '})';
	}

	public function getCurrentRank(): string
	{
		if (!$this->rank)
			return '';
		return $this->rank->name;
	}

	//What the run is, for the bar of the account: the time mode of a rank, or a challenge.
	public function getTitle(): string
	{
		if ($this->challenge)
			return 'Challenge';
		return 'Time mode ' . $this->getCurrentRank();
	}

	public ?TimeModeSession $currentSession = null;
	public ?TimeModeRank $rank = null;
	/** @var array<string, mixed>|null The challenge the current run is of */
	public ?array $challenge = null;
	public int $secondsToSolve = 0; // remaining time
	public $overallSecondsToSolve; // the time to solve the problem
	public int $successCount = 0;
	public int $failCount = 0;
	public int $overallCount = 0;
	public ?int $currentOrder = null;
	public ?int $servedOrder = null;
}
