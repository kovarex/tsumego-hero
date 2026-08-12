<?php

App::uses('TimeModeUtil', 'Utility');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('Play', 'Controller/Component');

class TimeModeController extends AppController
{
	public function start(): mixed
	{
		$timeMode = new TimeMode();
		$categoryID = (int) $this->params['url']['categoryID'];
		if (!$categoryID)
			throw new BadRequestException('Time mode category not specified.');
		$rankID = (int) $this->params['url']['rankID'];
		if (!$rankID)
			throw new BadRequestException('Time mode rank not specified.');

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
		$play  = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
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
		if ($count == 0)
			return null;
		if ($count == 1)
		{
			$result = [];
			$rank = $ranks[0]['TimeModeRank'];

			$count = Util::query("
SELECT
    COUNT(*) AS count
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id=tsumego.id
	JOIN `set` ON set_connection.set_id=`set`.id
WHERE
	`set`.`included_in_time_mode` = 1 AND
	`set`.public = 1");

			$rank['tsumego_count'] = $count[0]['count'];
			$result[] = $rank;
			return $result;
		}
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
		$this->set('_title', 'Time Mode - Select');
		$this->set('_page', 'time mode');

		$lastTimeModeCategoryID = Auth::getUser()['last_time_mode_category_id'];
		if (!$lastTimeModeCategoryID)
			$lastTimeModeCategoryID = ClassRegistry::init('TimeModeCategory')->find('first', ['order' => 'id DESC'])['TimeModeCategory']['id'];
		assert($lastTimeModeCategoryID);

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

		$this->set('lastTimeModeCategoryID', $lastTimeModeCategoryID);
		$this->set('timeModeCategories', ClassRegistry::init('TimeModeCategory')->find('all', ['order' => 'id']));
		$this->set('timeModeRanks', $this->getRanksWithTsumegoCount());
		$this->set('solvedMap', $solvedMap);
		$finishedSessionCount = ClassRegistry::init('TimeModeSession')->find('count', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id !=' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);
		$this->set('hasFinishedSesssion', $finishedSessionCount > 0);
		return null;
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

		$rankIndex = array_find_key($timeModeRanks, function ($timeModeRank) use ($finishedSession) {
			return $timeModeRank['TimeModeRank']['id'] == $finishedSession['TimeModeSession']['time_mode_rank_id'];
		});

		if ($rankIndex == 0)
		{
			return null; // there is no higher rank to unlock
		}

		$unlock = [];
		$unlock['rank'] = $timeModeRanks[$rankIndex - 1]['TimeModeRank']['name'];

		$categoryIndex = array_find_key($timeModeCategories, function ($timeModeCategory) use ($finishedSession) {
			return $timeModeCategory['TimeModeCategory']['id'] == $finishedSession['TimeModeSession']['time_mode_category_id'];
		});
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
				throw new NotFoundException('Time Mode Session not found.');
		return null;
	}

	public function result($timeModeSessionID = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect("/users/login");

		$timeMode = new TimeMode();
		$finishedSession = $this->deduceFinishedSession($timeModeSessionID, $timeMode);

		$this->set('_title', 'Time Mode - Result');
		$this->set('_page', 'time mode');

		$categories = ClassRegistry::init('TimeModeCategory')->find('all', []);
		$ranks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id DESC']);

		$userId = Auth::getUserID();
		$finishedId = $finishedSession ? (int) $finishedSession['TimeModeSession']['id'] : 0;

		// Best session per category×rank (metadata only)
		$bestRows = Util::query(
			'SELECT tms.* '
			. 'FROM time_mode_session tms '
			. 'JOIN ('
			. '  SELECT time_mode_category_id, time_mode_rank_id, COALESCE(MAX(points), 0) AS max_points '
			. '  FROM time_mode_session WHERE user_id = ? GROUP BY time_mode_category_id, time_mode_rank_id'
			. ') best ON tms.time_mode_category_id = best.time_mode_category_id '
			. '    AND tms.time_mode_rank_id = best.time_mode_rank_id '
			. '    AND COALESCE(tms.points, 0) = best.max_points '
			. 'WHERE tms.user_id = ?',
			[$userId, $userId]
		);

		$bestByKey = [];
		$displayIds = [];
		foreach ($bestRows as $row)
		{
			$key = $row['time_mode_category_id'] . '-' . $row['time_mode_rank_id'];
			$bestByKey[$key] = $row;
			$displayIds[] = (int) $row['id'];
		}
		if ($finishedId && !in_array($finishedId, $displayIds, true))
			$displayIds[] = $finishedId;

		// Attempts + set info for displayed sessions
		$attemptsBySession = [];
		if ($displayIds)
		{
			$placeholders = implode(',', array_fill(0, count($displayIds), '?'));
			$attemptRows = Util::query(
				'SELECT tma.*, sc.num AS set_num, s.title AS set_title, s.title2 AS set_title2 '
				. 'FROM time_mode_attempt tma '
				. 'JOIN set_connection sc ON sc.tsumego_id = tma.tsumego_id '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE tma.time_mode_session_id IN (' . $placeholders . ') '
				. 'ORDER BY tma.time_mode_session_id, tma.`order`',
				$displayIds
			);
			foreach ($attemptRows as $row)
				$attemptsBySession[(int) $row['time_mode_session_id']][] = $row;
		}

		$this->set('categories', $categories);
		$this->set('ranks', $ranks);
		$this->set('bestByKey', $bestByKey);
		$this->set('finishedSession', $finishedSession);
		$this->set('attemptsBySession', $attemptsBySession);
		$this->set('unlock', self::deduceUnlock($finishedSession, $ranks, $categories));
		$this->set('achievementUpdates', new AchievementChecker()->checkTimeModeAchievements()->finalize()->updated);

		return null;
	}
}
