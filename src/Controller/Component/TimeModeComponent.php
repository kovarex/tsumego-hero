<?php

App::uses('TimeModeUtil', 'Utility');
class TimeModeComponent extends Component {
	public function startTimeMode(int $timeMode): void {
		if (!Auth::isLoggedIn()) {
			return;
		}

		Auth::getUser()['mode'] = Constants::$TIME_MODE;
		Auth::getUser()['activeRank'] = Util::generateRandomString(self::sessionCodeLength($timeMode));
		Auth::saveUser();
	}

	private static function sessionCodeLength(int $timeMode): int {
		if ($timeMode == TimeModeUtil::$SLOW_SPEED) {
			return 15;
		}
		if ($timeMode == TimeModeUtil::$FAST_SPEED) {
			return 16;
		}
		if ($timeMode == TimeModeUtil::$BLITZ) {
			return 17;
		}
		die("Unknown time mode ");
	}

	public static function cancelTimeMode(): void {
		if (strlen(Auth::getUser()['activeRank']) < 15) {
			return;
		}
		$timeModeAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => ['session' => Auth::getUser()['activeRank']]]) ?: [];
		if (count($timeModeAttempts) == TimeModeUtil::$PROBLEM_COUNT) {
			return;
		}
		foreach ($timeModeAttempts as $timeModeAttempt) {
			ClassRegistry::init('TimeModeAttempt')->delete($timeModeAttempt['TimeModeAttempt']['id']);
		}

		Auth::getUser()['activeRank'] = 0;
		Auth::saveUser();
	}

	public function getRatingBounds($currentTimeSession)
	{
		$result = [];

		// I'm assuming, that the entries in the time_mode_rank tables have primary id ordered in the same order as the ranks, so the next entry
		// is the higher rank, and the previous is the lower (checked in testTimeModeRankContentsIntegrity)
		// This allows me to figure out what range should I cover by the current rank, which is
		// <max_of_smaller_rank or 0 if it doesn't exit, max_of_current_rank if next rank exists or infinity]
		// this should put every tsumego in some of the rank intervals regardless of the ranks configuration
		if ($smallerRankRow = ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id'] - 1]])) {
			$result['min'] = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($smallerRankRow['TimeModeAttempt']['name']) + 1);
		}
		else
			$result['min'] = 0;

		if (ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id'] + 1]])) {
			$currentRankRow = ClassRegistry::init('TimeModeRank')->find('first',['conditions' =>['id' => $currentTimeSession['time_mode_rank_id']]]);
			$result['max'] = Rating::getRankMinimalRating(Rating::GetRankFromReadableRank($currentRankRow['TimeModeAttempt']['name']));
		}
		else
			$result['min'] = 100000;

		return $result;
	}

	// @return if not null, new tsumego id to show (don't ask me why)
	public function update($setsWithPremium, $params): ?int {
		if (!Auth::isLoggedIn()) {
			return null;
		}

		$currentTimeSession = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);
		if (!$currentTimeSession) {
			return null;
		}
		$currentTimeSession = $currentTimeSession['TimeModeSession'];
		$currentTimeAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => ['time_mode_session_id' => $currentTimeSession['id']]]) ?: [];
		$ratingBounds = $this->getRatingBounds($currentTimeSession);

		$this->rankTs = ClassRegistry::init('TsumegoRank')->find('all', [
			'conditions' => [
				'rating >=' => $ratingBounds['min'],
				'rating <' => $ratingBounds['max'],
				]]);

		if (count($currentTimeAttempts) == 0) {
			$readableRank = $params['url']['TimeModeAttempt'];

			$timeModeSettings = ClassRegistry::init('TimeModeSetting')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
			foreach ($timeModeSettings as $timeModeSetting) {
				if (!Auth::hasPremium() && !in_array($timeModeSetting['set_id'], $setsWithPremium)) {
					continue;
				}

				$timeSc = TsumegoUtil::collectTsumegosFromSet($timeModeSetting['TimeModeSetting']['set_id']);
				$timeScCount = count($timeSc);
				for ($g = 0; $g < $timeScCount; $g++) {
					if ($timeSc[$g]['Tsumego']['rating'] >= $tsumegoMinimalRating && $timeSc[$g]['Tsumego']['rating'] < $tsumegoMaximalRating) {
						if (!in_array($timeSc[$g]['Tsumego']['set_id'], $setsWithPremium) || Auth::hasPremium()) {
							array_push($this->rankTs, $timeSc[$g]);
						}
					}
				}
			}
			shuffle($this->rankTs);
			for ($i = 0; $i < $this->stopParameter; $i++) {
				$rm = [];
				$rm['TimeModeAttempt']['session'] = Auth::getUser()['activeRank'];
				$rm['TimeModeAttempt']['user_id'] = Auth::getUserID();
				$rm['TimeModeAttempt']['tsumego_id'] = $this->rankTs[$i]['Tsumego']['id'];
				if ($rm['TimeModeAttempt']['tsumego_id'] == null) {
					$rm['TimeModeAttempt']['tsumego_id'] = 5127;
				}
				$rm['TimeModeAttempt']['TimeModeAttempt'] = $readableRank;
				$rm['TimeModeAttempt']['num'] = $i + 1;
				$rm['TimeModeAttempt']['currentNum'] = 1;
				ClassRegistry::init('TimeModeAttempt')->create();
				ClassRegistry::init('TimeModeAttempt')->save($rm);
			}
			$this->currentRankNum = 1;
			$this->firstRanks = 1;
		} else {
			$ranksCount = count($this->timeModeAttempts);
			for ($i = 0; $i < $ranksCount; $i++) {
				$this->timeModeAttempts[$i]['TimeModeAttempt']['currentNum']++;
				ClassRegistry::init('TimeModeAttempt')->save($this->timeModeAttempts[$i]);
			}
			$currentNum = $this->timeModeAttempts[0]['TimeModeAttempt']['currentNum'];
			$tsid = null;
			$tsid2 = null;
			$ranksCount = count($this->timeModeAttempts);
			for ($i = 0; $i < $ranksCount; $i++) {
				if ($this->timeModeAttempts[$i]['TimeModeAttempt']['num'] == $currentNum) {
					$tsid = $this->timeModeAttempts[$i]['TimeModeAttempt']['tsumego_id'];
					if ($currentNum < 10) {
						$tsid2 = $this->timeModeAttempts[$i + 1]['TimeModeAttempt']['tsumego_id'];
					} else {
						$tsid2 = $this->timeModeAttempts[$i]['TimeModeAttempt']['tsumego_id'];
					}
				}
			}
			$this->currentRank = ClassRegistry::init('Tsumego')->findById($tsid);
			$this->currentRank2 = ClassRegistry::init('Tsumego')->findById($tsid2);
			if ($currentNum == $this->stopParameter + 1) {
				$this->r10 = 1;
			}
			$this->currentRankNum = $currentNum;

			if ($this->rankTs) {
				$this->currentRank2 = $this->rankTs[1]['Tsumego']['id'];
				return $this->rankTs[0]['Tsumego']['id'];
			}
			return $this->currentRank['Tsumego']['id'];
		}
		return null;
	}

	public $timeModeAttempts = [];
	public $rankTs = [];
	public $stopParameter = 0;
	public $currentRank;
	public $currentRank2 = null;
	public $currentRankNum;
	public $firstRanks;
	public $tsumegoMinimalRating0;
}
