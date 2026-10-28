<?php

namespace App\Utility;

use App\Model\Entity\TimeModeCategory;
use App\Model\Entity\TimeModeRank;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;
use Challenge;
use ClassRegistry;
use DateTime;
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

		$this->currentSession = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);

		if (!$this->currentSession)
			return;

		// is TimeModeUtil::$PROBLEM_COUNT normally, but can be less when not enough problems found
		$attempts =  ClassRegistry::init('TimeModeAttempt')->find('all', [
			'conditions' => ['time_mode_session_id' => $this->currentSession['TimeModeSession']['id']]]);
		foreach ($attempts as $attempt)
		{
			if ($attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$this->successCount++;
			elseif (
				$attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_FAILED
				|| $attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_STATUS_SKIPPED
				|| $attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT)
					$this->failCount++;
		}
		$this->overallCount = count($attempts);

		if ($this->overallCount == 0)
		{
			$this->cancelTimeMode();
			return;
		}

		$this->currentOrder = ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'time_mode_attempt_status_id !=' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]) + 1;

		// the run of a challenge has the time the challenge gives, a run of the time mode the time of its category
		if ($challengeID = $this->currentSession['TimeModeSession']['challenge_id'])
		{
			$this->challenge = ClassRegistry::init('Challenge')->findById($challengeID)['Challenge'];
			$this->overallSecondsToSolve = (int) $this->challenge['seconds_per_problem'];
			return;
		}
		/** @var TimeModeRank|null $rank */
		$rank = $this->fetchTable('TimeModeRank')->find()->where(['id' => $this->currentSession['TimeModeSession']['time_mode_rank_id']])->first();
		$this->rank = $rank;
		/** @var TimeModeCategory $category */
		$category = $this->fetchTable('TimeModeCategory')->get($this->currentSession['TimeModeSession']['time_mode_category_id']);
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
		$this->createSessionAttempts($currentTimeSession, array_column(array_slice($relevantTsumegos, 0, TimeModeUtil::$PROBLEM_COUNT), 'id'));
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
		$session = [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id' => $challenge['id']];
		ClassRegistry::init('TimeModeSession')->create($session);
		ClassRegistry::init('TimeModeSession')->save($session);
		$session['id'] = ClassRegistry::init('TimeModeSession')->getLastInsertID();
		$this->createSessionAttempts($session, $tsumegoIDs);
	}

	public static function cancelTimeMode(): void
	{
		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
	}

	//Makes way for a new run. A run of the time mode left unfinished is thrown away. The run of a
	//challenge can't be, since the player knows its problems now and would play them again: it
	//ends with the problems left unplayed, which get no points.
	private function leaveCurrentSession(): void
	{
		$sessionModel = ClassRegistry::init('TimeModeSession');
		$sessionModel->deleteAll([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id' => null]);
		$challengeSessions = $sessionModel->find('all', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]) ?: [];
		foreach ($challengeSessions as $challengeSession)
			self::giveUp($challengeSession);
	}

	//Ends a run with whatever it has: every problem not played yet counts as skipped.
	/** @param array{TimeModeSession: array<string, mixed>} $session */
	private static function giveUp(array $session): void
	{
		$attemptModel = ClassRegistry::init('TimeModeAttempt');
		$queuedAttempts = $attemptModel->find('all', ['conditions' => [
			'time_mode_session_id' => $session['TimeModeSession']['id'],
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]) ?: [];
		foreach ($queuedAttempts as $attempt)
		{
			$attempt['TimeModeAttempt']['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_STATUS_SKIPPED;
			$attempt['TimeModeAttempt']['seconds'] = 0;
			$attempt['TimeModeAttempt']['points'] = 0;
			$attemptModel->save($attempt);
		}
		self::score($session);
	}

	private function createNewSession(int $categoryID, int $rankID): array
	{
		$timeModeCategory = $this->fetchTable('TimeModeCategory')->find()->where(['id' => $categoryID])->first();
		if (!$timeModeCategory)
			throw new NotFoundException("Time mode session category with id=" . $categoryID . " not found");

		if (!$this->fetchTable('TimeModeRank')->exists(['id' => $rankID]))
			throw new NotFoundException("Time mode rank category with id=" . $rankID . " not found");

		Auth::saveUserField('mode_id', Constants::$TIME_MODE);
		$currentTimeSession = [];
		$currentTimeSession['user_id'] = Auth::getUserID();
		$currentTimeSession['time_mode_session_status_id'] = TimeModeUtil::$SESSION_STATUS_IN_PROGRESS;
		$currentTimeSession['time_mode_category_id'] = $timeModeCategory->id;
		$currentTimeSession['time_mode_rank_id'] = $rankID;
		ClassRegistry::init('TimeModeSession')->create($currentTimeSession);
		ClassRegistry::init('TimeModeSession')->save($currentTimeSession);
		$currentTimeSession['id'] = ClassRegistry::init('TimeModeSession')->getLastInsertID();
		return $currentTimeSession;
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

	/**
	 * @param array<string, mixed> $currentTimeSession
	 * @param array<int|string> $tsumegoIDs The problems of the run in the order they are played
	 */
	private function createSessionAttempts(array $currentTimeSession, array $tsumegoIDs): void
	{
		foreach (array_values($tsumegoIDs) as $i => $tsumegoID)
		{
			$newTimeAttempt = [];
			$newTimeAttempt['time_mode_session_id'] = $currentTimeSession['id'];
			$newTimeAttempt['order'] = $i + 1;
			$newTimeAttempt['tsumego_id'] = $tsumegoID;
			$newTimeAttempt['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_RESULT_QUEUED;
			ClassRegistry::init('TimeModeAttempt')->create($newTimeAttempt);
			ClassRegistry::init('TimeModeAttempt')->save($newTimeAttempt);
		}
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
		$currentAttempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'tsumego_id' => $previousTsumego['Tsumego']['id'],
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
		if (!$currentAttempt)
			return; // this tsumego is not related to our time mode session, we ignore it
		$currentAttempt['TimeModeAttempt']['time_mode_attempt_status_id'] = self::deduceAttemptStatus($result, $timeout);
		if ($timeout)
			$seconds = $this->overallSecondsToSolve;

		$seconds = min($seconds, $this->overallSecondsToSolve);

		$currentAttempt['TimeModeAttempt']['seconds'] = $seconds;
		$currentAttempt['TimeModeAttempt']['points'] = $result['solved'] ? self::calculatePoints($seconds, $this->overallSecondsToSolve) : 0;
		ClassRegistry::init('TimeModeAttempt')->save($currentAttempt);
		$this->currentOrder++;
	}

	public function skip(int $position): void
	{
		if (!$this->currentSession)
			return;
		$attempt = $this->findAttemptToSkip($position);
		if (!$attempt)
			return;
		$attempt['TimeModeAttempt']['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_STATUS_SKIPPED;
		$seconds = min($this->overallSecondsToSolve, time() - strtotime($attempt['TimeModeAttempt']['started']));
		$attempt['TimeModeAttempt']['seconds'] = $seconds;
		$attempt['TimeModeAttempt']['points'] = 0;
		ClassRegistry::init('TimeModeAttempt')->save($attempt);
		$this->currentOrder++;
	}

	//The problem to give up on: the one at the given position, when it is being played.
	private function findAttemptToSkip(int $position): ?array
	{
		return ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'order' => $position,
				'started NOT' => null,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
			'order' => 'id DESC']);
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
	private function serve(?array $attempt): ?int
	{
		$this->secondsToSolve = $this->overallSecondsToSolve;
		$this->servedOrder = null;
		if (!$attempt)
			return null;

		$attempt = $attempt['TimeModeAttempt'];
		$this->servedOrder = (int) $attempt['order'];

		if (!$attempt['started'])
		{
			$attempt['started'] = date('Y-m-d H:i:s.u');
			ClassRegistry::init('TimeModeAttempt')->save($attempt);
		}
		else
		{
			$start = new DateTime($attempt['started']);
			$now = new DateTime();

			$secondsSinceStarted = $now->getTimestamp() + $now->format('u') / 1e6 - ($start->getTimestamp() + $start->format('u') / 1e6);
			$this->secondsToSolve = max(0, $this->secondsToSolve - $secondsSinceStarted);
			if ($this->secondsToSolve == 0)
			{
				$attempt['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT;
				$attempt['seconds'] = $this->overallSecondsToSolve;
				$attempt['points'] = 0;
				ClassRegistry::init('TimeModeAttempt')->save($attempt);
				$this->currentOrder++;
				return $this->prepareWhateverIsLeft();
			}
		}
		return $attempt['tsumego_id'];
	}

	//The first problem from this position on that has not been recorded yet.
	private function findOpenAttemptFrom(int $position): ?array
	{
		return ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'order >=' => $position,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
			'order' => 'TimeModeAttempt.order ASC']);
	}

	//The first problem of the session that has not been recorded yet.
	private function findOpenAttempt(): ?array
	{
		return ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
			'order' => 'id ASC']);
	}

	//True while a problem has been played but its result has not been recorded yet.
	private function hasAResultOnItsWay(): bool
	{
		return (bool) ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'started NOT' => null,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
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
		$session = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' => [
				'id' => $sessionID,
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);
		if (!$session)
			return false;

		return (bool) ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $sessionID,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
	}

	public function checkFinishSession(): ?int
	{
		if (!$this->currentSession)
			return null;

		if ($this->currentOrder - 1 < $this->overallCount)
			return null;

		self::score($this->currentSession);
		return $this->currentSession['TimeModeSession']['id'];
	}

	//Ends a run whose every problem has its result: sums up the points and decides whether it
	//passed, and gets the player back to the level mode.
	/** @param array{TimeModeSession: array<string, mixed>} $session */
	private static function score(array $session): void
	{
		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions'
		=> ['time_mode_session_id' => $session['TimeModeSession']['id']]]) ?: [];

		$overallPoints = 0.0;
		$correctCount = 0;
		foreach ($attempts as $attempt)
		{
			$overallPoints += $attempt['TimeModeAttempt']['points'];
			if ($attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$correctCount++;
		}

		$sessionSuccessful = Util::getRatio($correctCount, count($attempts)) >= TimeModeUtil::$RATIO_OF_SOLVED_TO_SUCCEED;
		$session['TimeModeSession']['time_mode_session_status_id'] = $sessionSuccessful ? TimeModeUtil::$SESSION_STATUS_SOLVED : TimeModeUtil::$SESSION_STATUS_FAILED;
		$session['TimeModeSession']['points'] = $overallPoints;
		ClassRegistry::init('TimeModeSession')->save($session);
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

	public $currentSession;
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
