<?php

App::uses('TimeModeUtil', 'Utility');
App::uses('AppException', 'Utility');

class TimeModeController extends AppController {
	public $components = ['TimeMode'];

	public function start(): mixed {
		$categoryID = (int) $this->params['url']['categoryID'];
		if (!$categoryID) {
			throw new AppException('Time mode category not specified');
		}
		$rankID = (int) $this->params['url']['rankID'];
		if (!$rankID) {
			throw new AppException('Time mode rank not specified');
		}

		$this->TimeMode->startTimeMode($categoryID, $rankID);
		return $this->redirect("/tsumegos/play");
	}

	public function overview(): mixed {
		if (!Auth::isLoggedIn()) {
			return $this->redirect("/");
		}

		$this->loadModel('Tsumego');
		$this->loadModel('User');
		$this->loadModel('TimeModeSession');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Time Mode - Select');
		$this->Session->write('page', 'time mode');

		$lastTimeModeCategoryID = Auth::getUser()['last_time_mode_category_id'];
		if (!$lastTimeModeCategoryID) {
			$lastTimeModeCategoryID = ClassRegistry::init('TimeModeCategory')->find('first', ['order' => 'id DESC']);
		}
		if (!$lastTimeModeCategoryID) {
			throw new AppException('No time category present!');
		}

		$timeModeSessions = ClassRegistry::init('TimeModeSession')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
		$timeModeRankMap = Util::indexByID(ClassRegistry::init('TimeModeRank')->find('all', []) ?: [], 'TimeModeRank', 'name');

		$timeModeStatuses = ClassRegistry::init('TimeModeSession')->find('all', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_SOLVED]]) ?: [];

		$solvedMap = [];
		foreach ($timeModeStatuses as $timeModeStatus) {
			$timeModeCategoryID = $timeModeStatus['TimeModeSession']['time_mode_category_id'];
			$timeModeRankID = $timeModeStatus['TimeModeSession']['time_mode_rank_id'];
			$category = &$solvedMap[$timeModeCategoryID];
			$category[$timeModeRankID] = $timeModeRankMap[$timeModeStatus['TimeModeSession']['time_mode_rank_id']];
			if (!isset($category['best-unlocked-rank']) || $category['best-unlocked-rank'] < $timeModeRankID) {
				$category['best-unlocked-rank'] = $timeModeRankID;
			}
		}

		$achievementUpdate = $this->checkTimeModeAchievements();
		if (count($achievementUpdate) > 0) {
			$this->updateXP(Auth::getUserID(), $achievementUpdate);
		}

		$this->set('lastTimeModeCategoryID', $lastTimeModeCategoryID);
		$this->set('timeModeCategories', ClassRegistry::init('TimeModeCategory')->find('all', ['order' => 'id']));
		$this->set('timeModeRanks', ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']));
		$this->set('solvedMap', $solvedMap);
		$this->set('rxxCount', json_decode(file_get_contents('json/time_mode_overview.json'), true));
		$this->set('ro', $timeModeSessions);
		$this->set('achievementUpdate', $achievementUpdate);
		return null;
	}

	public function exportSessionToShow($session, $timeModeCategory, $timeModeRank): array {
		$result = [];
		$result['id'] = $session['TimeModeSession']['id'];
		$result['status'] = $session['TimeModeSession']['time_mode_session_status_id'] == TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed';
		$result['category'] = $timeModeCategory['TimeModeCategory']['name'];
		$result['points'] = $session['TimeModeSession']['points'];
		$result['rank'] = $timeModeRank['TimeModeRank']['name'];
		$date = new DateTime($session['TimeModeSession']['created']);
		$result['created'] = $date->format('H:i d.m.Y');
		$timeModeAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => ['time_mode_session_id' => $session['TimeModeSession']['id']]]) ?: [];
		$result['attempts'] = [];
		$solvedCount = 0;
		foreach ($timeModeAttempts as $timeModeAttempt) {
			if ($timeModeAttempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED) {
				$solvedCount++;
			}
			$attempt = [];
			$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $timeModeAttempt['TimeModeAttempt']['tsumego_id']]]);
			$set = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
			$attempt['tsumego_id'] = $timeModeAttempt['TimeModeAttempt']['tsumego_id'];
			$attempt['set'] = $set['Set']['title'] . ' ' . $set['Set']['title2'];
			$attempt['set_order'] = $setConnection['SetConnection']['num'];
			$seconds = $timeModeAttempt['TimeModeAttempt']['seconds'];
			$minutes = floor($seconds / 60);
			$seconds -= $minutes * 60;
			$attempt['seconds'] = $minutes . ' : ' . number_format($seconds, 2);
			$attempt['points'] = $timeModeAttempt['TimeModeAttempt']['points'];
			$attempt['order'] = $timeModeAttempt['TimeModeAttempt']['order'];
			$attempt['status'] = TimeModeUtil::attemptStatusName($timeModeAttempt['TimeModeAttempt']['time_mode_attempt_status_id']);
			$result['attempts'] [] = $attempt;
		}
		$result['solvedCount'] = $solvedCount;
		return $result;
	}

	public function result($timeModeSessionID = null): mixed {
		if (!Auth::isLoggedIn()) {
			return $this->redirect("users/login");
		}

		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TimeModeSession');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Time Mode - Result');
		$this->Session->write('page', 'time mode');

		if ($timeModeSessionID) {
			$finishedSession = $this->Session->read('TimeModeSession')->find('first', ['conditions' => ['id' => $timeModeSessionID]]);
			if (!$finishedSession) {
				throw new AppException('Time Mode Session not found');
			}
		} else {
			$finishedSession = null;
		}

		$timeModeCategories = ClassRegistry::init('TimeModeCategory')->find('all', []);
		$timeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id DESC']);

		$sessionsToShow = [];
		foreach ($timeModeCategories as $timeModeCategory) {
			foreach ($timeModeRanks as $timeModeRank) {
				$session = ClassRegistry::init('TimeModeSession')->find('first', [
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'time_mode_category_id' => $timeModeCategory['TimeModeCategory']['id'],
						'time_mode_rank_id' => $timeModeRank['TimeModeRank']['id']],
					'order' => 'points DESC']);
				if (isset($finishedSession)
					&& $finishedSession['TimeModeSession']['time_mode_category_id'] == $timeModeCategory['TimeModeCategory']['id']
					&& $finishedSession['TimeModeSession']['time_mode_rank_id'] == $timeModeRanks['id']) {
					$sessionsToShow[$timeModeCategory['TimeModeCategory']['id']][$timeModeRank['TimeModeRank']['id']]['current'] = $this->exportSessionToShow($finishedSession, $timeModeCategory, $timeModeRank);
				}
				if (!$session) {
					continue;
				}
				$sessionsToShow[$timeModeCategory['TimeModeCategory']['id']][$timeModeRank['TimeModeRank']['id']]['best'] = $this->exportSessionToShow($session, $timeModeCategory, $timeModeRank);
			}
		}

		$this->set('sessionsToShow', $sessionsToShow);
		$this->set('finishedSession', $finishedSession);
		$this->set('rankArrowClosed', '/img/greyArrow1.png');
		$this->set('rankArrowOpened', '/img/greyArrow2.png');
		return null;
	}
}
