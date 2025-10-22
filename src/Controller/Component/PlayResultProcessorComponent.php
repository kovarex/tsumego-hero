<?php
App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');

class PlayResultProcessorComponent extends Component {

	public $components = ['Session'];

	/**
	 * @param AppController $appController App controller
	 * @param array $loggedInUser Logged in user
	 * @param array $previousTsumego Previous tsumego
	 * @return void
	 */
	public function checkPreviousPlay($appController, &$loggedInUser, &$previousTsumego): void {
		if (!$previousTsumego) {
			return;
		}
		$result = $this->checkPreviousPlayAndGetResult($appController, $loggedInUser, $previousTsumego);
		$this->updateTsumegoStatus($loggedInUser, $previousTsumego, $result);
		$this->processEloChange($appController, $loggedInUser, $previousTsumego, $result);
	}

	public function checkPreviousPlayAndGetResult($appController, &$loggedInUser, &$previousTsumego): string {
		if ($this->checkMisplay()) {
			return 'l';
		}
		if ($this->checkCorrectPlay($appController, $loggedInUser, $previousTsumego)) {
			return 'w';
		}

		return '';
	}

	/**
	 * @param array $loggedInUser Logged in user
	 * @param array $previousTsumego Previous tsumego
	 * @param string $result Result
	 * @return void
	 */
	private function updateTsumegoStatus($loggedInUser, $previousTsumego, $result): void {
		$tsumegoStatusModel = ClassRegistry::init('TsumegoStatus');
		$previousTsumegoStatus = $tsumegoStatusModel->find('first', [
			'order' => 'created DESC',
			'conditions' => [
				'tsumego_id' => (int)$previousTsumego['Tsumego']['id'],
				'user_id' => (int)$loggedInUser['User']['id'],
			],
		]);
		if ($previousTsumegoStatus == null) {
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = $loggedInUser['User']['id'];
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
		}
		$_COOKIE['previousTsumegoBuffer'] = $previousTsumegoStatus['TsumegoStatus']['status'];

		if ($result == 'w') {
			if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'W') { // half xp state
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'C'; // double solved
			} else {
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'S'; // solved once
			}
		} else if ($result == 'l') {
			if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'F') { // failed already
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'X'; // double failed
			} elseif ($previousTsumegoStatus['TsumegoStatus']['status'] == 'V') { // if it was just visited so far (so we don't overwrite solved
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'F'; // set to failed
			}
		}

		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		$tsumegoStatusModel->save($previousTsumegoStatus);
	}

	/**
	 * @param AppController $appController App controller
	 * @param array $loggedInUser Logged in user
	 * @param array $previousTsumego Previous tsumego
	 * @param string $result Result
	 * @return void
	 */
	private function processEloChange($appController, &$loggedInUser, $previousTsumego, $result): void {
		if ($result == '') {
			return;
		}

		$userRating = (float)$loggedInUser['User']['elo_rating_mode'];
		$tsumegoRating = (float)$previousTsumego['Tsumego']['elo_rating_mode'];
		$eloDifference = abs($userRating - $tsumegoRating);
		if ($userRating > $tsumegoRating) {
			$eloBigger = 'u';
		} else {
			$eloBigger = 't';
		}
		if (!empty($_COOKIE['av'])) {
			$activityValue = $_COOKIE['av'];
			Util::clearCookie('av');
		} else {
			$activityValue = 1;
		}
		$newUserElo = $appController->getNewElo($eloDifference, $eloBigger, $activityValue, $previousTsumego['Tsumego']['id'], $result);
		$newEloRating = $userRating + $newUserElo['user'];
		$loggedInUser['User']['elo_rating_mode'] = $newEloRating;

		ClassRegistry::init('User')->save($loggedInUser);

		$previousTsumego['Tsumego']['elo_rating_mode'] += $newUserElo['tsumego'];
		$previousTsumego['Tsumego']['activity_value']++;
		$previousTsumego['Tsumego']['difficulty'] = $appController->convertEloToXp($previousTsumego['Tsumego']['elo_rating_mode']);
		if ($previousTsumego['Tsumego']['elo_rating_mode'] > 100) {
			ClassRegistry::init('Tsumego')->save($previousTsumego);
		}
	}

	private function checkMisplay(): bool {
		if (empty($_COOKIE['misplay'])) {
			return false;
		}
		Util::clearCookie('misplay');

		return true;
	}

	private function checkCorrectPlay($appController, &$loggedInUser, &$previousTsumego): bool {
		if (empty($_COOKIE['mode'])) {
			return false;
		}
		if (empty($_COOKIE['score'])) {
			return false;
		}

		if (($_COOKIE['mode'] == '1' || $_COOKIE['mode'] == '2') && $_COOKIE['score'] != '0') {
			$suspiciousBehavior = false;
			$exploit = null;
			$_COOKIE['score'] = $appController->decrypt($_COOKIE['score']);
			$scoreArr = explode('-', $_COOKIE['score']);
			$isNum = $previousTsumego['Tsumego']['num'] == $scoreArr[0];
			$isSet = true;
			$isNumSc = false;
			$isSetSc = false;

			$preSc = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => (int)$previousTsumego['Tsumego']['id']]]);
			if (!$preSc) {
				$preSc = [];
			}
			$preScCount = count($preSc);
			for ($i = 0;$i < $preScCount;$i++) {
				if ($preSc[$i]['SetConnection']['set_id'] == $previousTsumego['Tsumego']['set_id']) {
					$isSetSc = true;
				}
				if ($preSc[$i]['SetConnection']['num'] == $previousTsumego['Tsumego']['num']) {
					$isNumSc = true;
				}
			}
			$isNum = $isNumSc;
			$isSet = $isSetSc;
			$_COOKIE['score'] = $scoreArr[1];
			$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['elo_rating_mode']);

			if ($isNum && $isSet) {
				if (!$this->Session->check('noLogin')) {
					$ub = [];
					$ub['UserBoard']['user_id'] = $loggedInUser['User']['id'];
					$ub['UserBoard']['b1'] = (int)$_COOKIE['previousTsumegoID'];
					$appController->UserBoard->create();
					$appController->UserBoard->save($ub);
					if ($_COOKIE['score'] >= 3000) {
						$_COOKIE['score'] = 0;
						$suspiciousBehavior = true;
					}
					if ($loggedInUser['User']['reuse3'] > 12000) {
						$loggedInUser['User']['reuse4'] = 1;
					}
				}
				if (!$suspiciousBehavior) {
					$xpOld = $loggedInUser['User']['xp'] + (int)($_COOKIE['score']);
					$loggedInUser['User']['reuse2']++;
					$loggedInUser['User']['reuse3'] += (int)($_COOKIE['score']);
					if ($xpOld >= $loggedInUser['User']['nextlvl']) {
						$xpOnNewLvl = -1 * ($loggedInUser['User']['nextlvl'] - $xpOld);
						$loggedInUser['User']['xp'] = $xpOnNewLvl;
						$loggedInUser['User']['level'] += 1;
						$loggedInUser['User']['nextlvl'] += $appController->getXPJump($loggedInUser['User']['level']);
						$loggedInUser['User']['health'] = $appController->getHealth($loggedInUser['User']['level']);
					} else {
						$loggedInUser['User']['xp'] = $xpOld;
						$loggedInUser['User']['ip'] = $_SERVER['REMOTE_ADDR'];
					}
					if ($loggedInUser['User']['id'] != 33) { // noUser
						if (!isset($_COOKIE['seconds'])) {
							$cookieSeconds = 0;
						} else {
							$cookieSeconds = $_COOKIE['seconds'];
						}

						$tsumegoAttempt = [];
						$tsumegoAttempt['TsumegoAttempt']['user_id'] = $loggedInUser['User']['id'];
						$tsumegoAttempt['TsumegoAttempt']['elo'] = $loggedInUser['User']['elo_rating_mode'];
						$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
						$tsumegoAttempt['TsumegoAttempt']['gain'] = $_COOKIE['score'];
						$tsumegoAttempt['TsumegoAttempt']['seconds'] = $cookieSeconds;
						$tsumegoAttempt['TsumegoAttempt']['solved'] = '1';
						$tsumegoAttempt['TsumegoAttempt']['mode'] = 1;
						$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['elo_rating_mode'];
						ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
						$correctSolveAttempt = true;

						$appController->saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
						$appController->updateGems($solvedTsumegoRank);
						if ($_COOKIE['sprint'] == 1) {
							$appController->updateSprintCondition(true);
						} else {
							$appController->updateSprintCondition();
						}
						if ($_COOKIE['type'] == 'g') {
							$appController->updateGoldenCondition(true);
						}
						$aCondition = $appController->AchievementCondition->find('first', [
							'order' => 'value DESC',
							'conditions' => [
								'user_id' => $loggedInUser['User']['id'],
								'category' => 'err',
							],
						]);
						if ($aCondition == null) {
							$aCondition = [];
						}
						$aCondition['AchievementCondition']['category'] = 'err';
						$aCondition['AchievementCondition']['user_id'] = $loggedInUser['User']['id'];
						$aCondition['AchievementCondition']['value']++;
						$appController->AchievementCondition->save($aCondition);
					}
					if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0') {
						$ranks = $appController->Rank->find('all', ['conditions' => ['session' => $loggedInUser['User']['ActiveRank']]]);
						if (!$ranks) {
							$ranks = [];
						}
						$currentNum = $ranks[0]['Rank']['currentNum'];
						$ranksCount = count($ranks);
						for ($i = 0; $i < $ranksCount; $i++) {
							if ($ranks[$i]['Rank']['num'] == $currentNum - 1) {
								if ($_COOKIE['rank'] != 'solved' && $_COOKIE['rank'] != 'failed' && $_COOKIE['rank'] != 'skipped' && $_COOKIE['rank'] != 'timeout') {
									$_COOKIE['rank'] = 'failed';
								}
								$ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
								$ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds'] / 10;
								$appController->Rank->save($ranks[$i]);
							}
						}
					}
				}
			} else {
				$loggedInUser['User']['penalty'] += 1;
			}

			Util::clearCookie('score');
			Util::clearCookie('sequence');
			Util::clearCookie('type');
		}

		return true;
	}

}
