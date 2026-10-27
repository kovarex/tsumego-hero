<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Rating;
use App\Utility\TimeMode;
use App\Utility\TimeModeUtil;
use App\Utility\Util;

class TimeModeController extends AppController
{
	#[HttpGet]
	public function start(): mixed
	{
		$this->Authorization->authorize('User', 'startTimeMode');

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

	#[HttpGet]
	#[HttpPost]
	public function play(?string $position = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInTimeMode())
			Auth::saveUserField('mode_id', Constants::$TIME_MODE);

		$timeMode = new TimeMode();

		if (!$timeMode->currentSession)
			return $this->redirect('/timeMode/overview');

		$tsumegoID = $position === null
			? $timeMode->prepareWhateverIsLeft()
			: $timeMode->prepareToSolve((int) $position);
		if ($timeModeSessionID = $timeMode->checkFinishSession())
			return $this->redirect("/timeMode/result/" . $timeModeSessionID);

		// Nothing left to play, but the session cannot be scored yet because a result is
		// still on its way.
		if ($tsumegoID == null)
			return $this->renderWaitingForTheResult();

		$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoID);
		if (!$setConnection)
			throw new Exception('Time mode session contains tsumego without a set connection.');

		$this->set('timeMode', $timeMode);
		$this->set('nextLink', '/timeMode/skip/' . $timeMode->servedOrder);
		$this->set('noSkipNextLink', $timeMode->currentWillBeLast()
			? '/timeMode/result/' . $timeMode->currentSession['TimeModeSession']['id']
			: '/timeMode/play/' . ($timeMode->servedOrder + 1));
		$play  = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->play($setConnection['SetConnection']['id'], $this->request, $this->request->data);
		$this->render('/Tsumegos/play');
		return null;
	}

	#[HttpGet]
	public function skip(?string $position = null): mixed
	{
		if (!$position)
			return $this->redirect('/timeMode/play');

		$timeMode = new TimeMode();
		$timeMode->skip((int) $position);

		return $this->redirect('/timeMode/play/' . ((int) $position + 1));
	}

	private function getRanksWithTsumegoCount(): ?array
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
    COUNT(DISTINCT tsumego.id) AS count
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id=tsumego.id
	JOIN `set` ON set_connection.set_id=`set`.id
WHERE
	`set`.`included_in_time_mode` = 1 AND " . Set::officialCondition());

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
    COUNT(DISTINCT tsumego.id) AS count
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id=tsumego.id
	JOIN `set` ON set_connection.set_id=`set`.id
WHERE
	`set`.`included_in_time_mode` = 1 AND " . Set::officialCondition() . "
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

	#[HttpGet]
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
				'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'challenge_id' => null]]) ?: [];

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
			'time_mode_session_status_id !=' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id' => null]]);
		$this->set('hasFinishedSesssion', $finishedSessionCount > 0);
		// a new run ends the run of a challenge, so the player is told before they start one
		$challengeRun = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id NOT' => null]]);
		$this->set('challengeInProgressID', $challengeRun ? (int) $challengeRun['TimeModeSession']['challenge_id'] : null);
		return null;
	}

	//The session in the form the result page shows it, with its attempts loaded by getAttempts().
	private static function exportSessionToShow(array $session, array $attempts, array $timeModeCategory, array $timeModeRank): array
	{
		$result = [];
		$result['id'] = $session['id'];
		$result['status'] = $session['time_mode_session_status_id'] == TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed';
		$result['category'] = $timeModeCategory['TimeModeCategory']['name'];
		$result['points'] = $session['points'];
		$result['rank'] = $timeModeRank['TimeModeRank']['name'];
		$result['created'] = $session['created'];
		$result['attempts'] = [];
		$solvedCount = 0;
		foreach ($attempts as $timeModeAttempt)
		{
			if ($timeModeAttempt['time_mode_attempt_status_id'] == TimeModeUtil::$ATTEMPT_RESULT_SOLVED)
				$solvedCount++;
			$attempt = [];
			$attempt['tsumego_id'] = $timeModeAttempt['tsumego_id'];
			$attempt['set'] = $timeModeAttempt['set_title'] . ' ' . $timeModeAttempt['set_title2'];
			$attempt['set_order'] = $timeModeAttempt['set_order'];
			$seconds = $timeModeAttempt['seconds'];
			$minutes = floor($seconds / 60);
			$seconds -= $minutes * 60;
			$attempt['seconds'] = $minutes . ' : ' . number_format($seconds, 2);
			$attempt['points'] = $timeModeAttempt['points'];
			$attempt['order'] = $timeModeAttempt['order'];
			$attempt['status'] = TimeModeUtil::attemptStatusName($timeModeAttempt['time_mode_attempt_status_id']);
			$result['attempts'] [] = $attempt;
		}
		$result['solvedCount'] = $solvedCount;
		return $result;
	}

	//The attempts of the given sessions indexed by session, each with the set it is shown in.
	//A problem in several sets is shown in the one SetConnection::findDisplaySetConnection() picks.
	private static function getAttempts(array $sessionIDs): array
	{
		if (empty($sessionIDs))
			return [];
		$attempts = Util::query("
SELECT
	time_mode_attempt.time_mode_session_id,
	time_mode_attempt.tsumego_id,
	time_mode_attempt.`order`,
	time_mode_attempt.seconds,
	time_mode_attempt.points,
	time_mode_attempt.time_mode_attempt_status_id,
	set_connection.num AS set_order,
	`set`.title AS set_title,
	`set`.title2 AS set_title2
FROM time_mode_attempt
LEFT JOIN set_connection ON set_connection.id = (
	SELECT display_connection.id FROM set_connection display_connection
	JOIN `set` display_set ON display_set.id = display_connection.set_id
	WHERE display_connection.tsumego_id = time_mode_attempt.tsumego_id
	ORDER BY " . SetConnection::displayOrderSql('display_set', 'display_connection') . "
	LIMIT 1)
LEFT JOIN `set` ON `set`.id = set_connection.set_id
WHERE time_mode_attempt.time_mode_session_id IN (" . implode(',', array_fill(0, count($sessionIDs), '?')) . ")
ORDER BY time_mode_attempt.`order`, time_mode_attempt.id",
			array_values($sessionIDs));

		$result = [];
		foreach ($attempts as $attempt)
			$result[$attempt['time_mode_session_id']] [] = $attempt;
		return $result;
	}

	//All the finished sessions of the current user indexed by category and rank, the newest first.
	//Only the counts of the attempts are loaded, the attempts themselves are shown when the session is opened.
	private static function getHistory(): array
	{
		$sessions = Util::query("
SELECT
	time_mode_session.id,
	time_mode_session.points,
	time_mode_session.created,
	time_mode_session.time_mode_session_status_id,
	time_mode_session.time_mode_category_id,
	time_mode_session.time_mode_rank_id,
	(SELECT COUNT(*) FROM time_mode_attempt
		WHERE time_mode_attempt.time_mode_session_id = time_mode_session.id) AS attempt_count,
	(SELECT COUNT(*) FROM time_mode_attempt
		WHERE time_mode_attempt.time_mode_session_id = time_mode_session.id
		AND time_mode_attempt.time_mode_attempt_status_id = ?) AS solved_count
FROM time_mode_session
WHERE
	time_mode_session.user_id = ? AND
	time_mode_session.time_mode_session_status_id != ? AND
	time_mode_session.challenge_id IS NULL
ORDER BY time_mode_session.created DESC, time_mode_session.id DESC",
			[TimeModeUtil::$ATTEMPT_RESULT_SOLVED, Auth::getUserID(), TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]);

		$result = [];
		foreach ($sessions as $session)
			$result[$session['time_mode_category_id']][$session['time_mode_rank_id']] [] = [
				'id' => $session['id'],
				'status' => $session['time_mode_session_status_id'] == TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed',
				'points' => $session['points'],
				'created' => $session['created'],
				'time_mode_session_status_id' => $session['time_mode_session_status_id'],
				'solvedCount' => $session['solved_count'],
				'attemptCount' => $session['attempt_count']];
		return $result;
	}

	private static function deduceUnlock(?array $finishedSession, array $timeModeRanks, array $timeModeCategories): ?array
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

	//A session that is still being played has no score yet, so there is nothing to show
	private function renderWaitingForTheResult(): mixed
	{
		$this->set('_title', 'Time Mode - Result');
		$this->set('_page', 'time mode');
		$this->render('waiting');
		return null;
	}

	private function deduceFinishedSession(int|string|null $passedSessionID, TimeMode $timeMode): ?array
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

	#[HttpGet]
	public function result(?string $timeModeSessionID = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect("/users/login");

		$timeMode = new TimeMode();
		if ($timeModeSessionID && $timeMode->hasNoResultYet((int) $timeModeSessionID))
			return $this->renderWaitingForTheResult();

		$finishedSession = $this->deduceFinishedSession($timeModeSessionID, $timeMode);
		// the results of a challenge are the table of all its players
		if ($finishedSession && $finishedSession['TimeModeSession']['challenge_id'])
			return $this->redirect('/challenges/' . $finishedSession['TimeModeSession']['challenge_id']);

		$this->set('_title', 'Time Mode - Result');
		$this->set('_page', 'time mode');

		$timeModeCategories = ClassRegistry::init('TimeModeCategory')->find('all', []);
		$timeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id DESC']);
		$history = self::getHistory();
		$finishedSessionID = $finishedSession ? $finishedSession['TimeModeSession']['id'] : null;

		// the history is the newest first, so the newest of the runs with the most points is the best one
		$bestSessions = [];
		foreach ($history as $categoryID => $historyOfCategory)
			foreach ($historyOfCategory as $rankID => $runs)
				foreach ($runs as $run)
					if (!isset($bestSessions[$categoryID][$rankID]) || $run['points'] > $bestSessions[$categoryID][$rankID]['points'])
						$bestSessions[$categoryID][$rankID] = $run;

		$shownSessionIDs = $finishedSessionID ? [$finishedSessionID] : [];
		foreach ($bestSessions as $bestOfCategory)
			foreach ($bestOfCategory as $bestSession)
				$shownSessionIDs[] = $bestSession['id'];
		$attempts = self::getAttempts(array_unique($shownSessionIDs));

		$sessionsToShow = [];
		foreach ($timeModeCategories as $timeModeCategory)
			foreach ($timeModeRanks as $timeModeRank)
			{
				$categoryID = $timeModeCategory['TimeModeCategory']['id'];
				$rankID = $timeModeRank['TimeModeRank']['id'];
				if (isset($finishedSession)
					&& $finishedSession['TimeModeSession']['time_mode_category_id'] == $categoryID
					&& $finishedSession['TimeModeSession']['time_mode_rank_id'] == $rankID)
						$sessionsToShow[$categoryID][$rankID]['current'] = self::exportSessionToShow(
							$finishedSession['TimeModeSession'], $attempts[$finishedSessionID] ?? [], $timeModeCategory, $timeModeRank);
				// a single run is already shown as the result or the best one
				if (count($history[$categoryID][$rankID] ?? []) > 1)
					$sessionsToShow[$categoryID][$rankID]['history'] = $history[$categoryID][$rankID];
				$bestSession = $bestSessions[$categoryID][$rankID] ?? null;
				if (!$bestSession || $bestSession['id'] == $finishedSessionID)
					continue;
				$sessionsToShow[$categoryID][$rankID]['best'] = self::exportSessionToShow(
					$bestSession, $attempts[$bestSession['id']] ?? [], $timeModeCategory, $timeModeRank);
			}

		$this->set('sessionsToShow', $sessionsToShow);
		$this->set('rankArrowClosed', '/img/greyArrow1.png');
		$this->set('rankArrowOpened', '/img/greyArrow2.png');
		// a run opened from the history unlocked whatever it unlocked when it was finished
		$this->set('unlock', isset($this->params['url']['history'])
			? null
			: self::deduceUnlock($finishedSession, $timeModeRanks, $timeModeCategories));
		$this->set('achievementUpdates', new AchievementChecker()->checkTimeModeAchievements()->finalize()->updated);

		return null;
	}
}
