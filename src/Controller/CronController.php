<?php

App::uses('TsumegoUtil', 'Util');

class CronController extends AppController
{
	/* Supposed to be ran daily to reset hearts and hero powers */
	public function daily($secret)
	{
		if ($secret != CRON_SECRET)
		{
			$this->response->statusCode(403);
			$this->response->body('Wrong cron secret.');
			return $this->response;
		}
		$this->dailyTsumegoStatusReset();
		$this->dailyStalingSolvedTsumegoStatuses();
		self::createDayRecord();
		self::publish();
		$this->dailyUsersReset();
		$this->updatePopularTags();

		$this->response->statusCode(200);
		return $this->response;
	}

	private function dailyUsersReset()
	{
		$query = 'UPDATE user SET';
		$query .= ' used_refinement=0';
		$query .= ',used_sprint=0';
		$query .= ',used_rejuvenation=0';
		$query .= ',used_potion=0';
		$query .= ',used_intuition=0';
		$query .= ',used_revelation=0';
		$query .= ',damage=0';
		$query .= ',daily_xp=0';
		$query .= ',daily_solved=0';
		$query .= ',readingTrial=30';
		$query .= ',reuse4=0';
		ClassRegistry::init('User')->query($query);
	}

	private function dailyTsumegoStatusReset()
	{
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='V' where status='F'");
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='W' where status='X'");
	}

	private function dailyStalingSolvedTsumegoStatuses()
	{
		ClassRegistry::init('TsumegoStatus')->query("
UPDATE tsumego_status
SET status='W'
WHERE
	status='S' AND
	tsumego_status.updated < '" . date('Y-m-d H:i:s', strtotime('-7 days')) . "'");
	}

	private static function deduceUserOfTheDay(): ?array
	{
		$lastDayRecords = ClassRegistry::init('DayRecord')->find('all', ['limit' => '7', 'order' => 'date DESC']) ?: [];

		$excludedUsers = [];
		foreach ($lastDayRecords as $lastDayRecord)
			$excludedUsers[$lastDayRecord['DayRecord']['user_id']] = true;

		$topUsers = ClassRegistry::init('User')->find('all', ['limit' => '8', 'order' => 'daily_xp DESC']) ?: [];
		foreach ($topUsers as $user)
			if (!isset($excludedUsers[$user['User']['id']]))
				return $user;
		return null;
	}

	private static function deduceQuoteToUse(): string
	{
		$latestDayRecords = ClassRegistry::init('DayRecord')->find('all', ['conditions' => ['date' => date('Y-m-d', strtotime('-10 days'))]]) ?: [];
		$usedQuotes = [];
		foreach ($latestDayRecords as $latestDayRecord)
			$usedQuotes[$latestDayRecord['DayRecord']['quote']] = true;

		$allQuotes = [];
		for ($i = 1; $i < 46; $i++)
			$allQuotes[] = sprintf('q%02d', $i);
		shuffle($allQuotes);
		foreach ($allQuotes as $quote)
			if (!isset($usedQuotes[$quote]))
				return $quote;
		throw new Exception("Quote couldn't be generated");
	}

	private function createDayRecord()
	{
		$userOfTheDay = self::deduceUserOfTheDay();
		$currentQuote = self::deduceQuoteToUse();
		$today = date('Y-m-d');
		$activity = $this->TsumegoAttempt->find('all', ['limit' => 40000, 'conditions' => ['created' => date('Y-m-d', strtotime('yesterday'))]]) ?: [];
		$visitedProblems = count($activity);

		//how many users today
		$usersNum = [];
		$activities = $this->User->find('all', ['limit' => 400, 'order' => 'created DESC']) ?: [];
		foreach ($activities as $activity)
		{
			$a = new DateTime($activity['User']['created']);
			if ($a->format('Y-m-d') == $today)
				array_push($usersNum, $activity['User']);
		}
		$gemRand1 = rand(0, 2);
		$gemRand2 = rand(0, 2);
		$gemRand3 = rand(0, 2);

		$arch1 = ClassRegistry::init('Achievement')->findById(111);
		if ($gemRand1 == 0)
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on an easy ddk problem.';
		elseif ($gemRand1 == 1)
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on a regular ddk problem.';
		elseif ($gemRand1 == 2)
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult ddk problem.';
		ClassRegistry::init('Achievement')->save($arch1);
		$arch2 = ClassRegistry::init('Achievement')->findById(112);
		if ($gemRand2 == 0)
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on an easy sdk problem.';
		elseif ($gemRand2 == 1)
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on a regular sdk problem.';
		elseif ($gemRand2 == 2)
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult sdk problem.';
		ClassRegistry::init('Achievement')->save($arch2);
		$arch3 = ClassRegistry::init('Achievement')->findById(113);
		if ($gemRand3 == 0)
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on an easy dan problem.';
		elseif ($gemRand3 == 1)
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on a regular dan problem.';
		elseif ($gemRand3 == 2)
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult dan problem.';
		ClassRegistry::init('Achievement')->save($arch3);

		ClassRegistry::init('DayRecord')->create();
		$dayRecord = [];
		$dayRecord['DayRecord']['user_id'] = $userOfTheDay['User']['id'];
		$dayRecord['DayRecord']['date'] = $today;
		$dayRecord['DayRecord']['solved'] = $userOfTheDay['User']['daily_solved'];
		$dayRecord['DayRecord']['quote'] = $currentQuote;
		$dayRecord['DayRecord']['usercount'] = count($usersNum);
		$dayRecord['DayRecord']['visitedproblems'] = $visitedProblems;
		$dayRecord['DayRecord']['gems'] = $gemRand1 . '-' . $gemRand2 . '-' . $gemRand3;
		$dayRecord['DayRecord']['gemCounter1'] = 0;
		$dayRecord['DayRecord']['gemCounter2'] = 0;
		$dayRecord['DayRecord']['gemCounter3'] = 0;
		$dayRecord['DayRecord']['tsumego_count'] = TsumegoUtil::currentTsumegoCount();
		ClassRegistry::init('DayRecord')->save($dayRecord);

		ClassRegistry::init('AchievementCondition')->create();
		$achievementCondition = [];
		$achievementCondition['AchievementCondition']['user_id'] = $userOfTheDay['User']['id'];
		$achievementCondition['AchievementCondition']['set_id'] = 0;
		$achievementCondition['AchievementCondition']['category'] = 'uotd';
		$achievementCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);
	}

	public static function publish()
	{
		$date = date('Y-m-d', strtotime('today'));
		$todaysSchedule = ClassRegistry::init('Schedule')->find('all', ['conditions' => ['date' => $date]]) ?: [];
		foreach ($todaysSchedule as $item)
		{
			self::publishSingle($item['Schedule']['tsumego_id'], $item['Schedule']['set_id'], $item['Schedule']['date']);
			$item['Schedule']['published'] = 1;
			ClassRegistry::init('Schedule')->save($item);
		}
	}

	protected static function publishSingle($tsumegoID = null, $to = null, $date = null): void
	{
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			return;
		$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumegoID]]);
		if (!$setConnection)
			return;
		$setConnection['SetConnection']['set_id'] = $to;
		ClassRegistry::init('SetConnection')->save($setConnection);

		// delete tsumego stats
		$tsumego['Tsumego']['created'] = $date . ' 22:00:00';
		$tsumego['Tsumego']['solved'] = 0;
		$tsumego['Tsumego']['failed'] = 0;
		$tsumego['Tsumego']['userWin'] = 0;
		$tsumego['Tsumego']['userLoss'] = 0;
		ClassRegistry::init('Tsumego')->save($tsumego);

		// delete any status made on the tsumego when it was in the sandbox
		ClassRegistry::init('TsumegoStatus')->deleteAll(['tsumego_id' => $tsumego['Tsumego']['id']]);

		$x = [];
		$x['PublishDate']['date'] = $date . ' 22:00:00';
		$x['PublishDate']['tsumego_id'] = $tsumegoID;
		ClassRegistry::init('PublishDate')->create();
		ClassRegistry::init('PublishDate')->save($x);
	}

	private static function updatePopularTags()
	{
		ClassRegistry::init('Tag')->query("
UPDATE tag
JOIN (
    SELECT tag_id
    FROM tag_connection
    GROUP BY tag_id
    ORDER BY COUNT(*) DESC
    LIMIT " . Tag::$POPULAR_COUNT . "
) AS top_tags ON tag.id = top_tags.tag_id
SET tag.popular = 1;");
	}
}
