<?php

App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');

class TimeMode
{
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
		$this->overallCount = ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id']]]);

		if ($this->overallCount == 0)
		{
			$this->cancelTimeMode();
			return;
		}

		$this->currentOrder = ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'time_mode_attempt_status_id !=' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]) + 1;

		$this->rank = ClassRegistry::init('TimeModeRank')->findById($this->currentSession['TimeModeSession']['time_mode_rank_id']);
		$this->overallSecondsToSolve = ClassRegistry::init('TimeModeCategory')->find('first', [
			'conditions' => ['id' => $this->currentSession['TimeModeSession']['time_mode_category_id']]])['TimeModeCategory']['seconds'];
	}

	public function startTimeMode(int $categoryID, int $rankID): void
	{
		if (!Auth::isLoggedIn())
			throw new AppException('Not logged in.');

		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);

		$relevantTsumegos = $this->getRelevantTsumegos($rankID);
		if (empty($relevantTsumegos))
			throw new AppException('No relevant tsumegos.');
		$currentTimeSession = $this->createNewSession($categoryID, $rankID);
		$this->createSessionAttempts($currentTimeSession, $relevantTsumegos);
	}

	public static function cancelTimeMode(): void
	{
		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
	}

	private function createNewSession(int $categoryID, int $rankID): array
	{
		$timeModeCategory = ClassRegistry::init('TimeModeCategory')->findById($categoryID);
		if (!$timeModeCategory)
			throw new AppException("Time mode session category with id=" . $categoryID . " not found");

		$timeModeRank = ClassRegistry::init('TimeModeRank')->findById($rankID);
		if (!$timeModeRank)
			throw new AppException("Time mode rank category with id=" . $rankID . " not found");

		Auth::getUser()['mode'] = Constants::$TIME_MODE;
		Auth::saveUser();
		$currentTimeSession = [];
		$currentTimeSession['user_id'] = Auth::getUserID();
		$currentTimeSession['time_mode_session_status_id'] = TimeModeUtil::$SESSION_STATUS_IN_PROGRESS;
		$currentTimeSession['time_mode_category_id'] = $timeModeCategory['TimeModeCategory']['id'];
		$currentTimeSession['time_mode_rank_id'] = $rankID;
		ClassRegistry::init('TimeModeSession')->create($currentTimeSession);
		ClassRegistry::init('TimeModeSession')->save($currentTimeSession);
		$currentTimeSession['id'] = ClassRegistry::init('TimeModeSession')->getLastInsertID();
		return $currentTimeSession;
	}

	public static function getRatingBounds(?int $timeModeRankID): RatingBounds
	{
		$result = new RatingBounds();

		// I'm assuming, that the entries in the time_mode_rank tables have primary id ordered in the same order as the ranks, so the next entry
		// is the higher rank, and the previous is the lower (checked in testTimeModeRankContentsIntegrity)
		// This allows me to figure out what range should I cover by the current rank, which is
		// <max_of_smaller_rank or 0 if it doesn't exit, max_of_current_rank if next rank exists or infinity]
		// this should put every tsumego in some of the rank intervals regardless of the ranks configuration
		if ($smallerRankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID - 1]]))
			$result->min = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($smallerRankRow['TimeModeRank']['name']) + 1);

		if (ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID + 1]]))
		{
			$currentRankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID]]);
			$result->max = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($currentRankRow['TimeModeRank']['name']) + 1);
		}

		return $result;
	}

	private function getRelevantTsumegos(int $timeModeRankID): array
	{
		$ratingBounds = $this->getRatingBounds($timeModeRankID);
		$query = "SELECT tsumego.id as id FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id JOIN `set` ON set.id = set_connection.set_id WHERE `set`.included_in_time_mode = TRUE AND `set`.public = 1";
		if ($ratingBounds->min)
			$query .= " AND rating >= " . $ratingBounds->min;
		if ($ratingBounds->max)
			$query .= " AND rating < " . $ratingBounds->max;
		return ClassRegistry::init('Tsumego')->query($query);
	}

	private function createSessionAttempts(array $currentTimeSession, $relevantTsumegos): void
	{
		shuffle($relevantTsumegos);
		$relevantTsumegosCount = count($relevantTsumegos);
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT && $i < $relevantTsumegosCount; $i++)
		{
			$newTimeAttempt = [];
			$newTimeAttempt['time_mode_session_id'] = $currentTimeSession['id'];
			$newTimeAttempt['order'] = $i + 1;
			$newTimeAttempt['tsumego_id'] = $relevantTsumegos[$i]['tsumego']['id'];
			$newTimeAttempt['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_RESULT_QUEUED;
			ClassRegistry::init('TimeModeAttempt')->create($newTimeAttempt);
			ClassRegistry::init('TimeModeAttempt')->save($newTimeAttempt);
		}
	}

	private static function deduceAttemptStatus($result, $timeout)
	{
		if ($timeout)
			return TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT;
		if (!$result['solved'])
			return TimeModeUtil::$ATTEMPT_RESULT_FAILED;
		return TimeModeUtil::$ATTEMPT_RESULT_SOLVED;
	}

	public function processPlayResult($previousTsumego, $result): void
	{
		if (!$this->currentSession)
			return;
		$currentAttempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'tsumego_id' => $previousTsumego['Tsumego']['id'],
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
		if (!$currentAttempt)
			throw new Exception("The tsumego is not in the current time mode session.");
		$timeout = Util::clearCookie('timeout');
		$currentAttempt['TimeModeAttempt']['time_mode_attempt_status_id'] = self::deduceAttemptStatus($result, $timeout);
		$seconds = $timeout ? $this->overallSecondsToSolve : Decoder::decodeSeconds($previousTsumego);
		if (is_null($seconds))
			throw new Exception("Seconds not provided.");
		$timeModeCategory = ClassRegistry::init('TimeModeCategory')->findById($this->currentSession['TimeModeSession']['time_mode_category_id']);

		$currentAttempt['TimeModeAttempt']['seconds'] = $seconds;
		$currentAttempt['TimeModeAttempt']['points'] = $result['solved'] ? self::calculatePoints($seconds, $timeModeCategory['TimeModeCategory']['seconds']) : 0;
		ClassRegistry::init('TimeModeAttempt')->save($currentAttempt);
		$this->currentOrder++;
	}

	public function skip(): void
	{
		if (!$this->currentSession)
			return;
		$currentAttempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'started NOT' => null,
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
		if (!$currentAttempt)
			return;
		$currentAttempt['TimeModeAttempt']['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_STATUS_SKIPPED;
		$seconds = min($this->overallSecondsToSolve, time() - strtotime($currentAttempt['TimeModeAttempt']['started']));
		$currentAttempt['TimeModeAttempt']['seconds'] = $seconds;
		$currentAttempt['TimeModeAttempt']['points'] = 0;
		ClassRegistry::init('TimeModeAttempt')->save($currentAttempt);
		$this->currentOrder++;
	}

	// @return if not null, new tsumego id to show in the time mode
	public function prepareNextToSolve(): ?int
	{
		if (!$this->currentSession)
			return null;

		$this->secondsToSolve = $this->overallSecondsToSolve;

		// first one which doesn't have a status yet
		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => [
				'time_mode_session_id' => $this->currentSession['TimeModeSession']['id'],
				'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
			'order' => 'id ASC']);
		if (!$attempt)
			return null;
		$attempt = $attempt['TimeModeAttempt'];

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
				ClassRegistry::init('TimeModeAttempt')->save($attempt);
				return $this->prepareNextToSolve();
			}
		}
		return $attempt['tsumego_id'];
	}

	public function currentWillBeLast(): bool
	{
		return $this->currentOrder + 1 > $this->overallCount;
	}

	public function checkFinishSession(): ?int
	{
		if (!$this->currentSession)
			return null;

		if ($this->currentOrder - 1 < $this->overallCount)
			return null;

		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions'
		=> ['time_mode_session_id' => $this->currentSession['TimeModeSession']['id']]]) ?: [];
		assert(count($attempts) == $this->overallCount);

		$overallPoints = 0.0;
		$correctCount = 0;
		foreach ($attempts as $attempt)
		{
			$overallPoints += $attempt['TimeModeAttempt']['points'];
			if ($attempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$correctCount++;
		}

		$sessionSuccessful = Util::getRatio($correctCount, count($attempts)) >= TimeModeUtil::$RATIO_OF_SOLVED_TO_SUCCEED;
		$this->currentSession['TimeModeSession']['time_mode_session_status_id'] = $sessionSuccessful ? TimeModeUtil::$SESSION_STATUS_SOLVED : TimeModeUtil::$SESSION_STATUS_FAILED;
		$this->currentSession['TimeModeSession']['points'] = $overallPoints;
		ClassRegistry::init('TimeModeSession')->save($this->currentSession);
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::saveUser();

		return $this->currentSession['TimeModeSession']['id'];
	}

	public static function calculatePoints(float $timeUsed, float $max): float
	{
		$timeRatio = 1 - ($timeUsed / $max);
		return min(100 * (TimeModeUtil::$POINTS_RATIO_FOR_FINISHING + (1 - TimeModeUtil::$POINTS_RATIO_FOR_FINISHING) * $timeRatio), 100.0);
	}

	public $currentSession;
	public $rank;
	public $secondsToSolve; // remaining time
	public $overallSecondsToSolve; // the time to solve the problem
	public $overallCount;
	public $currentOrder;
}
