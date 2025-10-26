<?php

App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');

class PlayResultProcessorComponent extends Component {
	public $components = ['Session'];

	public function checkPreviousPlay(AppController $appController, array &$previousTsumego): void {
		if (!$previousTsumego) {
			return;
		}
		$result = $this->checkPreviousPlayAndGetResult($appController, $previousTsumego);
		if (!isset($result['solved']))
			return; // wrong cookies presented, we do nothing

		$this->updateTsumegoStatus($previousTsumego, $result);
		$this->updateTsumegoAttempt($previousTsumego, $result);
		$this->processEloChange($appController, $previousTsumego, $result);
	}

	public function checkPreviousPlayAndGetResult($appController, &$previousTsumego): array {
		$result = [];
		if ($misplay = $this->checkMisplay()) {
			$result['solved'] = false;
			$result['misplay'] = $misplay;
		}
		if ($score = $this->checkCorrectPlay($appController, $previousTsumego)) {
			$result['solved'] = true;
			$result['score'] = $score;
		}

		return $result;
	}

	private function updateTsumegoStatus(array $previousTsumego, array $result): void {
		$tsumegoStatusModel = ClassRegistry::init('TsumegoStatus');
		$previousTsumegoStatus = $tsumegoStatusModel->find('first', [
			'order' => 'created DESC',
			'conditions' => [
				'tsumego_id' => (int) $previousTsumego['Tsumego']['id'],
				'user_id' => (int) Auth::getUserID(),
			],
		]);
		if ($previousTsumegoStatus == null) {
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
		}
		$_COOKIE['previousTsumegoBuffer'] = $previousTsumegoStatus['TsumegoStatus']['status'];

		if ($result['solved']) {
			if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'W') { // half xp state
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'C'; // double solved
			} else {
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'S'; // solved once
			}
		} else {
			if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'F') { // failed already
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'X'; // double failed
			} elseif ($previousTsumegoStatus['TsumegoStatus']['status'] == 'V') { // if it was just visited so far (so we don't overwrite solved
				$previousTsumegoStatus['TsumegoStatus']['status'] = 'F'; // set to failed
			}
		}

		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		$tsumegoStatusModel->save($previousTsumegoStatus);
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result): void
	{
		$tsumegoAttempt = [];
		$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
		$tsumegoAttempt['TsumegoAttempt']['elo'] = Auth::getUser()['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = Util::getCookie('score', 0);
		$tsumegoAttempt['TsumegoAttempt']['seconds'] = Util::getCookie('seconds', 0);
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['mode'] = Auth::getMode();
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] = $result['misplay'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private function processEloChange(AppController $appController, array $previousTsumego, array $result): void {
		$userRating = (float) Auth::getUser()['elo_rating_mode'];
		$tsumegoRating = (float) $previousTsumego['Tsumego']['elo_rating_mode'];
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
		Auth::getUser()['elo_rating_mode'] = $newEloRating;

		Auth::saveUser();

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

	private function checkCorrectPlay($appController, &$previousTsumego): bool {
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

			$preSc = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => (int) $previousTsumego['Tsumego']['id']]]);
			if (!$preSc) {
				$preSc = [];
			}
			$preScCount = count($preSc);
			for ($i = 0; $i < $preScCount; $i++) {
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
					$ub['UserBoard']['user_id'] = Auth::getUserID();
					$ub['UserBoard']['b1'] = (int) $_COOKIE['previousTsumegoID'];
					$appController->UserBoard->create();
					$appController->UserBoard->save($ub);
					if ($_COOKIE['score'] >= 3000) {
						$_COOKIE['score'] = 0;
						$suspiciousBehavior = true;
					}
					if (Auth::getUser()['reuse3'] > 12000) {
						Auth::getUser()['reuse4'] = 1;
					}
				}
				if (!$suspiciousBehavior) {
					$xpOld = Auth::getUser()['xp'] + (int) ($_COOKIE['score']);
					Auth::getUser()['reuse2']++;
					Auth::getUser()['reuse3'] += (int) ($_COOKIE['score']);
					if ($xpOld >= Auth::getUser()['nextlvl']) {
						$xpOnNewLvl = -1 * (Auth::getUser()['nextlvl'] - $xpOld);
						Auth::getUser()['xp'] = $xpOnNewLvl;
						Auth::getUser()['level'] += 1;
						Auth::getUser()['nextlvl'] += $appController->getXPJump(Auth::getUser()['level']);
						Auth::getUser()['health'] = $appController->getHealth(Auth::getUser()['level']);
					} else {
						Auth::getUser()['xp'] = $xpOld;
						Auth::getUser()['ip'] = $_SERVER['REMOTE_ADDR'];
					}

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
							'user_id' => Auth::getUserID(),
							'category' => 'err',
						],
					]);
					if ($aCondition == null) {
						$aCondition = [];
					}
					$aCondition['AchievementCondition']['category'] = 'err';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value']++;
					$appController->AchievementCondition->save($aCondition);
					if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0') {
						$ranks = $appController->Rank->find('all', ['conditions' => ['session' => Auth::getUser()['ActiveRank']]]);
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
				Auth::getUser()['penalty'] += 1;
			}

			Util::clearCookie('score');
			Util::clearCookie('sequence');
			Util::clearCookie('type');
		}

		return true;
	}

}
