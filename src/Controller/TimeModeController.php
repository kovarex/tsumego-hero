<?php

App::uses('TimeModeUtil', 'Utility');
App::uses('AppException', 'Utility');
App::uses('Play', 'Controller/Component');

class TimeModeController extends AppController
{
	public function start(): mixed
	{
		$timeMode = new TimeMode();
		$categoryID = (int) $this->params['url']['categoryID'];
		if (!$categoryID)
			throw new AppException('Time mode category not specified.');
		$rankID = (int) $this->params['url']['rankID'];
		if (!$rankID)
			throw new AppException('Time mode rank not specified.');

		$timeMode->startTimeMode($categoryID, $rankID);
		return $this->redirect("/timeMode/play");
	}

	public function play(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInTimeMode())
		{
			Auth::getUser()['mode'] = Constants::$TIME_MODE;
			Auth::saveUser();
		}

		$timeMode = new TimeMode();

		if (!$timeMode->currentSession)
			return $this->redirect('/timeMode/overview');

		$tsumegoID = $timeMode->prepareNextToSolve();
		if ($timeModeSessionID = $timeMode->checkFinishSession())
			return $this->redirect("/timeMode/result/" . $timeModeSessionID);
		assert($tsumegoID != null);

		$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumegoID]]);
		if (!$setConnection)
			throw new Exception('Time mode session contains tsumego without a set connection.');

		$this->set('timeMode', $timeMode);
		$this->set('nextLink', '/timeMode/skip/');
		$this->set('noSkipNextLink', $timeMode->currentWillBeLast() ? '/timeMode/result/' . $timeMode->currentSession['TimeModeSession']['id'] : '/timeMode/play');
		$play  = new Play(function ($name, $value) { $this->set($name, $value); });
		$play->play($setConnection['SetConnection']['id'], $this->params, $this->data);
		$this->render('/Tsumegos/play');
		return null;
	}

	public function skip(): mixed
	{
		$timeMode = new TimeMode();
		$timeMode->skip();
		return $this->play();
	}

	private function getRanksWithTsumegoCount()
	{

		$ranks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']);
		$rankPartOfQuery = '';
		$count = count($ranks);
		foreach ($ranks as $index => $rank)
			if ($index + 1 < $count)
				$rankPartOfQuery .= 'WHEN rating < ' . Rating::getRankMinimalRating(Rating::getRankFromReadableRank($rank['TimeModeRank']['name']) + 1) . ' THEN \'' . $rank['TimeModeRank']['id'] . '\' ';
			else
				$rankPartOfQuery .= 'ELSE \'' . $rank['TimeModeRank']['id'] . '\'';
		$counts = ClassRegistry::init('Tsumego')->query("
SELECT
    CASE " . $rankPartOfQuery . "
    END AS bucket,
    COUNT(*) AS count
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id=tsumego.id
	JOIN `set` ON set_connection.set_id=`set`.id
WHERE
	`set`.`included_in_time_mode` = 1 AND
	`set`.public = 1
GROUP BY bucket
ORDER BY MIN(rating);");

		$countsByRankID = [];
		foreach ($counts as $count)
			$countsByRankID[$count[0]['bucket']] = $count[0]['count'];

		$result = [];
		foreach ($ranks as $rank)
		{
			$rank = $rank['TimeModeRank'];
			if ($count = $countsByRankID[$rank['id']])
				$rank['tsumego_count'] = $count;
			else
				$rank['tsumego_count'] = 0;
			$result [] = $rank;
		}
		return $result;
	}

	public function overview(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$this->Session->write('title', 'Time Mode - Select');
		$this->Session->write('page', 'time mode');

		$lastTimeModeCategoryID = Auth::getUser()['last_time_mode_category_id'];
		if (!$lastTimeModeCategoryID)
			$lastTimeModeCategoryID = ClassRegistry::init('TimeModeCategory')->find('first', ['order' => 'id DESC']);
		assert($lastTimeModeCategoryID);

		$timeModeSessions = ClassRegistry::init('TimeModeSession')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
		$timeModeRankMap = Util::indexByID(ClassRegistry::init('TimeModeRank')->find('all', []) ?: [], 'TimeModeRank', 'name');

		$timeModeStatuses = ClassRegistry::init('TimeModeSession')->find('all', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_SOLVED]]) ?: [];

		$solvedMap = [];
		foreach ($timeModeStatuses as $timeModeStatus)
		{
			$timeModeCategoryID = $timeModeStatus['TimeModeSession']['time_mode_category_id'];
			$timeModeRankID = $timeModeStatus['TimeModeSession']['time_mode_rank_id'];
			$category = &$solvedMap[$timeModeCategoryID];
			$category[$timeModeRankID] = $timeModeRankMap[$timeModeStatus['TimeModeSession']['time_mode_rank_id']];
			if (!isset($category['best-solved-rank']) || $category['best-solved-rank'] < $timeModeRankID)
				$category['best-solved-rank'] = $timeModeRankID;
		}

		$achievementUpdate = $this->checkTimeModeAchievements();
		if (count($achievementUpdate) > 0)
			$this->updateXP(Auth::getUserID(), $achievementUpdate);

		$this->set('lastTimeModeCategoryID', $lastTimeModeCategoryID);
		$this->set('timeModeCategories', ClassRegistry::init('TimeModeCategory')->find('all', ['order' => 'id']));
		$this->set('timeModeRanks', $this->getRanksWithTsumegoCount());
		$this->set('solvedMap', $solvedMap);
		$this->set('ro', $timeModeSessions);
		$this->set('achievementUpdate', $achievementUpdate);
		return null;
	}

	public function exportSessionToShow($session, $timeModeCategory, $timeModeRank): array
	{
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
		foreach ($timeModeAttempts as $timeModeAttempt)
		{
			if ($timeModeAttempt['TimeModeAttempt']['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$solvedCount++;
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

	public static function deduceUnlock(?array $finishedSession, array $timeModeRanks, array $timeModeCategories): ?array
	{
		if (!$finishedSession)
			return null;

		// the current session wasn't a success, so it logically can't unlock shit
		if ($finishedSession['TimeModeSession']['time_mode_session_status_id'] != TimeModeUtil::$SESSION_STATUS_SOLVED)
			return null;

		// only one successful solve exists for this combination of rank and category, so it must be the one we just did
		if (ClassRegistry::init('TimeModeSession')->find('count', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'time_mode_category_id' => $finishedSession['TimeModeSession']['time_mode_category_id'],
				'time_mode_rank_id' => $finishedSession['TimeModeSession']['time_mode_rank_id'],
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_SOLVED]]) != 1)
					return null;

		$rankIndex = array_find_key($timeModeRanks, function ($timeModeRank) use ($finishedSession) { return $timeModeRank['TimeModeRank']['id'] == $finishedSession['TimeModeSession']['time_mode_rank_id']; });

		if ($rankIndex == 0)
		{
			return null; // there is no higher rank to unlock
		}

		$unlock = [];
		$unlock['rank'] = $timeModeRanks[$rankIndex - 1]['TimeModeRank']['name'];

		$categoryIndex = array_find_key($timeModeCategories, function ($timeModeCategory) use ($finishedSession) { return $timeModeCategory['TimeModeCategory']['id'] == $finishedSession['TimeModeSession']['time_mode_category_id']; });
		$unlock['category'] = $timeModeCategories[$categoryIndex]['TimeModeCategory']['name'];
		return $unlock;
	}

	private function deduceFinishedSession($passedSessionID, TimeMode $timeMode): ?array
	{
		if ($finishedSessionID = $timeMode->checkFinishSession())
			return ClassRegistry::init('TimeModeSession')->findById($finishedSessionID);

		if ($passedSessionID)
			if ($result = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => ['id' => $passedSessionID]]))
				return $result;
			else
				throw new AppException('Time Mode Session not found.');
		return null;
	}

	public function result($timeModeSessionID = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect("/users/login");

		$timeMode = new TimeMode();
		$finishedSession = $this->deduceFinishedSession($timeModeSessionID, $timeMode);

		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TimeModeSession');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Time Mode - Result');
		$this->Session->write('page', 'time mode');

		$timeModeCategories = ClassRegistry::init('TimeModeCategory')->find('all', []);
		$timeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id DESC']);

		$sessionsToShow = [];
		foreach ($timeModeCategories as $timeModeCategory)
			foreach ($timeModeRanks as $timeModeRank)
			{
				$session = ClassRegistry::init('TimeModeSession')->find('first', [
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'time_mode_category_id' => $timeModeCategory['TimeModeCategory']['id'],
						'time_mode_rank_id' => $timeModeRank['TimeModeRank']['id']],
					'order' => 'points DESC']);
				$categoryID = $timeModeCategory['TimeModeCategory']['id'];
				$rankID = $timeModeRank['TimeModeRank']['id'];
				if (isset($finishedSession)
					&& $finishedSession['TimeModeSession']['time_mode_category_id'] == $categoryID
					&& $finishedSession['TimeModeSession']['time_mode_rank_id'] == $rankID)
						$sessionsToShow[$categoryID][$rankID]['current'] = $this->exportSessionToShow($finishedSession, $timeModeCategory, $timeModeRank);
				if (!$session || isset($finishedSession) && $session['TimeModeSession']['id'] == $finishedSession['TimeModeSession']['id'])
					continue;
				$sessionsToShow[$categoryID][$rankID]['best'] = $this->exportSessionToShow($session, $timeModeCategory, $timeModeRank);
			}

		$this->set('sessionsToShow', $sessionsToShow);
		$this->set('finishedSession', $finishedSession);
		$this->set('rankArrowClosed', '/img/greyArrow1.png');
		$this->set('rankArrowOpened', '/img/greyArrow2.png');
		$this->set('unlock', self::deduceUnlock($finishedSession, $timeModeRanks, $timeModeCategories));
		return null;
	}
}
