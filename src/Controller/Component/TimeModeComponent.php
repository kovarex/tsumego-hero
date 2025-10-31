<?php

App::uses('TimeModeUtil', 'Utility');
class TimeModeComponent extends Component {
	public function startTimeMode(int $categoryID, int $rankID): void {
		if (!Auth::isLoggedIn()) {
			return;
		}

		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
		if ($currentTimeSession = $this->createNewSession()) {
 			$this->createSessionAttempts($currentTimeSession);
		}
	}

	public static function cancelTimeMode(): void {
		ClassRegistry::init('TimeModeSession')->deleteAll(['user_id' => Auth::getUserID(), 'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);
	}

	private function createNewSession(int $categoryID, int $rankID): array|null {
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
		$currentTimeSession['time_mode_rank_id'] = $timeModeCategory['TimeModeCategory']['id'];
		$currentTimeSession = ClassRegistry::init('TimeModeSession')->create();
		ClassRegistry::init('TimeModeSession')->save($currentTimeSession);
		return $currentTimeSession;
	}

	private function getRatingBounds($currentTimeSession)
	{
		$result = [];

		// I'm assuming, that the entries in the time_mode_rank tables have primary id ordered in the same order as the ranks, so the next entry
		// is the higher rank, and the previous is the lower (checked in testTimeModeRankContentsIntegrity)
		// This allows me to figure out what range should I cover by the current rank, which is
		// <max_of_smaller_rank or 0 if it doesn't exit, max_of_current_rank if next rank exists or infinity]
		// this should put every tsumego in some of the rank intervals regardless of the ranks configuration
		if ($smallerRankRow = ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id'] - 1]])) {
			$result['min'] = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($smallerRankRow['TimeModeRank']['name']) + 1);
		}
		else
			$result['min'] = 0;

		if (ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id'] + 1]])) {
			$currentRankRow = ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id']]]);
			$result['max'] = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($currentRankRow['TimeModeRank']['name']));
		}
		else
			$result['min'] = 100000;

		return $result;
	}

	private function getRelevantTsumegos($currentTimeSession): array {
		$ratingBounds = $this->getRatingBounds($currentTimeSession);

		$tsumegoOptions = ['conditions' => [
			'rating >=' => $ratingBounds['min'],
			'rating <' => $ratingBounds['max'],
			'set.included_in_time_mode' => true,
			],
		'contain' => ['set.id']];
		if (!Auth::hasPremium()) {
			$tsumegoOptions['conditions'] [] = ['set.premium' => false];
			$tsumegoOptions['contain'] []= 'set.premium';
		}

		return ClassRegistry::init('Tsumego')->find('all', $tsumegoOptions) ?: [];
	}

	private function createSessionAttempts($currentTimeSession): void {
		$relevantTsumegos = $this->getRelevantTsumegos($currentTimeSession);
		shuffle($relevantTsumegos);
		$relevantTsumegosCount = count($relevantTsumegos);
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT && $i < $relevantTsumegosCount; $i++) {
			$newTimeAttempt = [];
			$newTimeAttempt['time_mode_session_id'] = $currentTimeSession['id'];
			$newTimeAttempt['order'] = $i + 1;
			$newTimeAttempt['tsumego_id'] = $relevantTsumegos[$i];
			ClassRegistry::init('TimeModeSession')->create($newTimeAttempt);
			ClassRegistry::init('TimeModeSession')->save($newTimeAttempt);
		}
	}

	// @return if not null, new tsumego id to show in the time mode
	public function update($setsWithPremium, $params): ?int {
		if (!Auth::isInTimeMode()) {
			return null;
		}

		if ($currentSession = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' =>[
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS])) {

			$this->currentOrder = ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $currentSession['TimeModeSession']['id'],
				'time_mode_status_id is not' => 'null']]) + 1;

			// is TimeModeUtil::$PROBLEM_COUNT normally, but can be less when not enough problems found
			$this->overallCount = ClassRegistry::init('TimeModeAttempt')->find('count', [
			'conditions' => [
				'time_mode_session_id' => $currentSession['TimeModeSession']['id']]]);

			$this->secondsToSolve = ClassRegistry::init('TimeModeCategory')->find('first', [
				'conditions' => ['id' => $currentSession['TimeModeSession']['time_mode_category_id']]])['TimeModeCategory']['seconds'];
			$this->rank = ClassRegistry::init('TimeModeRank')->findById($currentSession['TimeModeSession']['time_mode_rank_id']);

			// first one which doesn't have a status yet
			return ClassRegistry::init('TimeModeAttempt')->find('first', [
				'conditions' => [
					'time_mode_session_id' => $currentSession['id'],
					'time_mode_status_id' => 'null'],
				'order' => 'time_mode_attempt_id']);
		}
		return null;
	}

	public $rank;
	public $secondsToSolve;
	public $overallCount;
	public $currentOrder;
}
