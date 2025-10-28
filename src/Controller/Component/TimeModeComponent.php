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

	// @return if not null, new tsumego id to show (don't ask me why)
	public function update($setsWithPremium, $params): int|null
	{
		if (!Auth::isLoggedIn()) {
			return null;
		}

		if (strlen(Auth::getUser()['activeRank']) < 15) {
			return null;
		}

		$this->stopParameter = 10;
		if (strlen(Auth::getUser()['activeRank']) == 15) {
			$this->stopParameter2 = 0;
		} elseif (strlen(Auth::getUser()['activeRank']) == 16) {
			$this->stopParameter2 = 1;
		} elseif (strlen(Auth::getUser()['activeRank']) == 17) {
			$this->stopParameter2 = 2;
		}
		$this->timeModeAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => ['session' => Auth::getUser()['activeRank']]]) ?: [];
		if (count($this->timeModeAttempts) == 0) {
			$readableRank = $params['url']['TimeModeAttempt'];

			$rank = Rating::getRankFromReadableRank($readableRank ?: "15k");
			$r1 = Rating::getRankMinimalRating($rank);
			$r2 = Rating::getRankMinimalRating($rank + 1);
			if ($rank >= Rating::getRankFromReadableRank('5d')) {
				$r2 = 10000;
			}
			if ($rank <= Rating::getRankFromReadableRank('15k')) {
				$r1 = 0;
			}

			$timeModeSettings = ClassRegistry::init('TimeModeSetting')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
			foreach ($timeModeSettings as $timeModeSetting) {
				$timeSc = TsumegoUtil::collectTsumegosFromSet($timeModeSetting['TimeModeSetting']['set_id']);
				$timeScCount = count($timeSc);
				for ($g = 0; $g < $timeScCount; $g++) {
					if ($timeSc[$g]['Tsumego']['elo_rating_mode'] >= $r1 && $timeSc[$g]['Tsumego']['elo_rating_mode'] < $r2) {
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
	public $stopParameter2 = 0;
	public $currentRank;
	public $currentRank2 = null;
	public $currentRankNum;
	public $firstRanks;
	public $r10;
}
