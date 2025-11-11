<?php

App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');
App::uses('Decoder', 'Utility');

class PlayResultProcessorComponent extends Component {
	public $components = ['Session', 'TimeMode'];

	public function checkPreviousPlay($timeModeComponent): void {
		$previousTsumegoID = Util::clearNumericCookie('previousTsumegoID');
		if (!$previousTsumegoID) {
			return;
		}

		$previousTsumego = ClassRegistry::init('Tsumego')->findById($previousTsumegoID);
		if (!$previousTsumego) {
			return;
		}

		$result = $this->checkPreviousPlayAndGetResult($previousTsumego);
		$this->updateTsumegoStatus($previousTsumego, $result);

		if (!isset($result['solved'])) {
			return;
		}
		$this->updateTsumegoAttempt($previousTsumego, $result);
		$this->processEloChange($previousTsumego, $result);
		$this->processDamage($result);
		$timeModeComponent->processPlayResult($previousTsumego, $result);
		$this->processXpChange($previousTsumego, $result);
		$this->processErrorAchievement($result);
		$this->processUnsortedStuff($previousTsumego, $result);
	}

	public function checkPreviousPlayAndGetResult(&$previousTsumego): array {
		$result = [];
		if ($misplays = $this->checkMisplay()) {
			$result['solved'] = false;
			$result['misplays'] = $misplays;
		}
		if (Decoder::decodeSuccess($previousTsumego['Tsumego']['id'])) {
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
				'order' => 'id DESC']
		);

		// only not solved ones are updated (misplays get accumulated)
		if (!$lastTsumegoAttempt || $lastTsumegoAttempt['TsumegoAttempt']['solved']) {
			$tsumegoAttempt = [];
			$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
			$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
			$tsumegoAttempt['TsumegoAttempt']['mode'] = Auth::getMode();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['rating'];
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = 0;
		} else {
			$tsumegoAttempt = $lastTsumegoAttempt;
		}

		$tsumegoAttempt['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = Util::getCookie('score', 0);
		$tsumegoAttempt['TsumegoAttempt']['seconds'] += Decoder::decodeSeconds($previousTsumego);
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['rating'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] += $result['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private function processEloChange(array $previousTsumego, array $result): void {
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
		$newUserElo = AppController::getNewElo($eloDifference, $eloBigger, $activityValue, $previousTsumego['Tsumego']['id'], $result['solved'] ? 'w' : 'l');
		$newEloRating = $userRating + $newUserElo['user'];
		Auth::getUser()['rating'] = $newEloRating;

		Auth::saveUser();

		$previousTsumego['Tsumego']['rating'] += $newUserElo['tsumego'];
		$previousTsumego['Tsumego']['activity_value']++;
		$previousTsumego['Tsumego']['difficulty'] = AppController::convertEloToXp($previousTsumego['Tsumego']['rating']);
		if ($previousTsumego['Tsumego']['rating'] > 100) {
			ClassRegistry::init('Tsumego')->save($previousTsumego);
		}
	}

	private function processDamage(array $result): void {
		if (!$result['misplays']) {
			return;
		}
		if (!Auth::isInLevelMode()) {
			return;
		}
		Auth::getUser()['damage'] += $result['misplays'];
		Auth::saveUser();
	}

	private function processXpChange(array $previousTsumego, array $result): void {
		if (!$result['solved']) {
			return;
		}
		Auth::getUser()['xp'] += $previousTsumego['difficulty'];
		if (Auth::getUser()['xp'] >= Auth::getUser()['nextlvl']) {
			Auth::getUser()['xp'] -= Auth::getUser()['nextlvl'];
			Auth::getUser()['level'] += 1;
			Auth::getUser()['nextlvl'] += AppController::getXPJump(Auth::getUser()['level']);
			Auth::getUser()['health'] = AppController::getHealth(Auth::getUser()['level']);
		}
	}

	private function processErrorAchievement(array $result): void {
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err',
			],
		]);
		if (!$achievementCondition) {
			$achievementCondition = [];
			ClassRegistry::init('AchievementCondition')->create();
		}
		$achievementCondition['AchievementCondition']['category'] = 'err';
		$achievementCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		if ($result['solved']) {
			$achievementCondition['AchievementCondition']['value']++;
		} else {
			$achievementCondition['AchievementCondition']['value'] = 0;
		}
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);
	}

	private function processUnsortedStuff(array $previousTsumego, array $result): void {
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

		Util::clearCookie('sequence');
		Util::clearCookie('type');
	}

	/* @return The number of misplays and consumes the misplays cookie in the process */
	private function checkMisplay(): int {
		return (int) Util::clearCookie('misplays');
	}
}
