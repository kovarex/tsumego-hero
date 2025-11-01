<?php

App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');

class TimeModeComponent extends Component {
	public function startTimeMode(int $categoryID, int $rankID): mixed {
		if (!Auth::isLoggedIn()) {
			return Result::fail('Not logged in');
		}

		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);

		$relevantTsumegos = $this->getRelevantTsumegos($rankID);
		if (empty($relevantTsumegos)) {
			return Result::fail('No relevant tsumegos.');
		}
		$currentTimeSession = $this->createNewSession($categoryID, $rankID)) {
			$this->createSessionAttempts($currentTimeSession, $relevantTsumegos);
		}
		else
			return Result::fail('Couldn't create time mode session'')
	}

	public static function cancelTimeMode(): void {
		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
	}

	private function createNewSession(int $categoryID, int $rankID): ?array {
		$timeModeCategory = ClassRegistry::init('TimeModeCategory')->findById($categoryID);
		if (!$timeModeCategory) {
			return null;
		}

		$timeModeRank = ClassRegistry::init('TimeModeRank')->findById($rankID);
		if (!$timeModeRank) {
			return null;
		}

		Auth::getUser()['mode'] = Constants::$TIME_MODE;
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

	public static function getRatingBounds(?int $timeModeRankID): RatingBounds {
		$result = new RatingBounds();

		// I'm assuming, that the entries in the time_mode_rank tables have primary id ordered in the same order as the ranks, so the next entry
		// is the higher rank, and the previous is the lower (checked in testTimeModeRankContentsIntegrity)
		// This allows me to figure out what range should I cover by the current rank, which is
		// <max_of_smaller_rank or 0 if it doesn't exit, max_of_current_rank if next rank exists or infinity]
		// this should put every tsumego in some of the rank intervals regardless of the ranks configuration
		if ($smallerRankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID - 1]])) {
			$result->min = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($smallerRankRow['TimeModeRank']['name']) + 1);
		}

		if (ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID + 1]])) {
			$currentRankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['id' => $timeModeRankID]]);
			$result->max = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($currentRankRow['TimeModeRank']['name']) + 1);
		}

		return $result;
	}

	private function getRelevantTsumegos(int $timeModeRankID): array {
		$ratingBounds = $this->getRatingBounds($timeModeRankID);

		/* use this once the framework join is figured out
		$tsumegoOptions = ['conditions' => ['Set.included_in_time_mode =' => true],
			'contain' => ['Set', 'SetConnection'],
			'fields' => ['Tsumego.id', 'Set.id', 'Set.included_in_time_mode']];
		$tsumegosOptions['conditions'] []= $ratingBounds.getConditions();
		if (!Auth::hasPremium()) {
			$tsumegoOptions['conditions'] [] = ['Set.premium' => false];
		}
		return ClassRegistry::init('Tsumego')->find('all', $tsumegoOptions) ?: [];*/

		/* temporary custom join until the framework join is figured out */
		$query = "SELECT tsumego.id as id FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id JOIN `set` ON set.id = set_connection.set_id WHERE `set`.included_in_time_mode = TRUE";
		if ($ratingBounds->min) {
			$query .= " AND rating >= " . $ratingBounds->min;
		}
		if ($ratingBounds->max) {
			$query .= " AND rating < " . $ratingBounds->max;
		}
		return ClassRegistry::init('Tsumego')->query($query);
	}

	private function createSessionAttempts(array $currentTimeSession, $relevantTsumegos): void {
		shuffle($relevantTsumegos);
		$relevantTsumegosCount = count($relevantTsumegos);
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT && $i < $relevantTsumegosCount; $i++) {
			$newTimeAttempt = [];
			$newTimeAttempt['time_mode_session_id'] = $currentTimeSession['id'];
			$newTimeAttempt['order'] = $i + 1;
			$newTimeAttempt['tsumego_id'] = $relevantTsumegos[$i]['tsumego']['id'];
			$newTimeAttempt['time_mode_attempt_status_id'] = TimeModeUtil::$ATTEMPT_RESULT_QUEUED;
			ClassRegistry::init('TimeModeAttempt')->create($newTimeAttempt);
			ClassRegistry::init('TimeModeAttempt')->save($newTimeAttempt);
		}
	}

	// @return if not null, new tsumego id to show in the time mode
	public function update($setsWithPremium, $params): ?int {
		if (!Auth::isInTimeMode()) {
			return null;
		}

		if ($currentSession = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])) {

			// is TimeModeUtil::$PROBLEM_COUNT normally, but can be less when not enough problems found
			$this->overallCount = ClassRegistry::init('TimeModeAttempt')->find('count', [
				'conditions' => [
					'time_mode_session_id' => $currentSession['TimeModeSession']['id']]]);
			if ($this->overallCount == 0) {
				$this->cancelTimeMode();
				return null;
			}

			$this->currentOrder = ClassRegistry::init('TimeModeAttempt')->find('count', [
				'conditions' => [
					'time_mode_session_id' => $currentSession['TimeModeSession']['id'],
					'time_mode_attempt_status_id !=' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]) + 1;

			$this->secondsToSolve = ClassRegistry::init('TimeModeCategory')->find('first', [
				'conditions' => ['id' => $currentSession['TimeModeSession']['time_mode_category_id']]])['TimeModeCategory']['seconds'];
			$this->rank = ClassRegistry::init('TimeModeRank')->findById($currentSession['TimeModeSession']['time_mode_rank_id']);

			// first one which doesn't have a status yet
			return ClassRegistry::init('TimeModeAttempt')->find('first', [
				'conditions' => [
					'time_mode_session_id' => $currentSession['id'],
					'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
				'order' => 'time_mode_attempt_status_id'])['id'];
		}
		return null;
	}

	public function checkFinishSession() {
		if (!Auth::isInTimeMode()) {
			return false;
		}

		if ($this->currentOrder > $this->overallCount) {
			return true;
		}

		/* Do some logic */
		return false;
	}

	public $rank;
	public $secondsToSolve;
	public $overallCount;
	public $currentOrder;
}
