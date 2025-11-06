<?php

App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');

class PlayResultProcessorComponent extends Component {
	public $components = ['Session', 'TimeMode'];

	public function checkPreviousPlay(AppController $appController, ?array &$previousTsumego, $timeModeComponent): void {
		if (!$previousTsumego) {
			return;
		}
		$result = $this->checkPreviousPlayAndGetResult($appController, $previousTsumego);
		$this->updateTsumegoStatus($previousTsumego, $result);

		if (!isset($result['solved'])) {
			return;
		}
		$this->updateTsumegoAttempt($previousTsumego, $result);
		$this->processEloChange($appController, $previousTsumego, $result);
		$this->processDamage($result);
		$timeModeComponent->processPlayResult($previousTsumego, $result);
		$this->processXpChange($appController, $previousTsumego, $result);
		$this->processUnsortedStuff($appController, $previousTsumego, $result);
	}

	public function checkPreviousPlayAndGetResult($appController, &$previousTsumego): array {
		$result = [];
		if ($misplay = $this->checkMisplay()) {
			$result['solved'] = false;
			$result['misplay'] = $misplay;
		}
		if ($this->checkCorrectPlay($appController, $previousTsumego)) {
			$result['solved'] = true;
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

		if (isset($result['solved'])) {
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
		}

		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		$tsumegoStatusModel->save($previousTsumegoStatus);
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result): void {
		if (Auth::isInTimeMode()) {
			return;
		}
		$lastTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find(
			'first',
			['conditions'
				=> ['user_id' => Auth::getUserID(),
					'tsumego_id' => $previousTsumego['Tsumego']['id'],
					'mode' => Auth::getMode()],
				'order' => 'id DESC'],
		);

		// only not solved ones are updated (misplays get accumulated)
		if (!$lastTsumegoAttempt || $lastTsumegoAttempt['TsumegoAttempt']['solved']) {
			$tsumegoAttempt = [];
			$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
			$tsumegoAttempt['TsumegoAttempt']['seconds'] = Util::getCookie('seconds', 0);
			$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
			$tsumegoAttempt['TsumegoAttempt']['mode'] = Auth::getMode();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['rating'];
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = 0;
		} else {
			$tsumegoAttempt = $lastTsumegoAttempt;
		}

		$tsumegoAttempt['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = Util::getCookie('score', 0);
		$tsumegoAttempt['TsumegoAttempt']['seconds'] += (int) Util::getCookie('seconds', 0);
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['rating'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] += $result['misplay'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private function processEloChange(AppController $appController, array $previousTsumego, array $result): void {
		if (!Auth::isInRatingMode()) {
			return;
		}
		$userRating = (float) Auth::getUser()['rating'];
		$tsumegoRating = (float) $previousTsumego['Tsumego']['rating'];
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
		$newUserElo = $appController->getNewElo($eloDifference, $eloBigger, $activityValue, $previousTsumego['Tsumego']['id'], $result['solved'] ? 'w' : 'l');
		$newEloRating = $userRating + $newUserElo['user'];
		Auth::getUser()['rating'] = $newEloRating;

		Auth::saveUser();

		$previousTsumego['Tsumego']['rating'] += $newUserElo['tsumego'];
		$previousTsumego['Tsumego']['activity_value']++;
		$previousTsumego['Tsumego']['difficulty'] = $appController->convertEloToXp($previousTsumego['Tsumego']['rating']);
		if ($previousTsumego['Tsumego']['rating'] > 100) {
			ClassRegistry::init('Tsumego')->save($previousTsumego);
		}
	}

	private function processDamage(array $result): void {
		if (!$result['misplay']) {
			return;
		}
		if (!Auth::isInLevelMode()) {
			return;
		}
		Auth::getUser()['damage'] += $result['misplay'];
		Auth::saveUser();
	}

	private function processXpChange(AppController $appController, array $previousTsumego, array $result): void {
		if (!$result['solved']) {
			return;
		}
		Auth::getUser()['xp'] += $previousTsumego['difficulty'];
		if (Auth::getUser()['xp'] >= Auth::getUser()['nextlvl']) {
			Auth::getUser()['xp'] -= Auth::getUser()['nextlvl'];
			Auth::getUser()['level'] += 1;
			Auth::getUser()['nextlvl'] += $appController->getXPJump(Auth::getUser()['level']);
			Auth::getUser()['health'] = $appController->getHealth(Auth::getUser()['level']);
		}
	}

	private function processUnsortedStuff(AppController $appController, array $previousTsumego, array $result): void {
		if (!$result['solved']) {
			return;
		}

		$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['rating']);
		AppController::saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
		AppController::updateGems($solvedTsumegoRank);
		if ($_COOKIE['sprint'] == 1) {
			AppController::updateSprintCondition(true);
		} else {
			AppController::updateSprintCondition();
		}
		if ($_COOKIE['type'] == 'g') {
			AppController::updateGoldenCondition(true);
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

		Util::clearCookie('sequence');
		Util::clearCookie('type');
	}

	/* @return The number of misplays */
	private function checkMisplay(): int {
		if (empty($_COOKIE['misplay'])) {
			return 0;
		}
		return (int) Util::clearCookie('misplay');
	}

	private function isSuspicious($scoreCheck, $previousTsumegoID): bool {
		$decryptedScore = explode('-', Util::wierdDecrypt($scoreCheck));
		if (count($decryptedScore) != 2) {
			return true;
		}
		if ($decryptedScore[0] != $previousTsumegoID) {
			return true;
		}
		return false;
	}

	/* @return if this was checked as correct play */
	private function checkCorrectPlay($appController, &$previousTsumego): bool {
		$scoreCheck = Util::clearCookie('scoreCheck');
		if (empty($scoreCheck)) {
			return false;
		}
		if ($this->isSuspicious($scoreCheck, $previousTsumego['Tsumego']['id'])) {
			Auth::addSuspicion();
			return false;
		}
		return true;
	}
}
