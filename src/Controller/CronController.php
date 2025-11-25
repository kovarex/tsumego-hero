<?php

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
		self::userOfTheDay();
		$this->dailyUsersReset();

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

	private function userOfTheDay()
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
		$dateUser = [];
		$dateUser['DayRecord']['user_id'] = $userOfTheDay['User']['id'];
		$dateUser['DayRecord']['date'] = $today;
		$dateUser['DayRecord']['solved'] = $userOfTheDay['User']['daily_solved'];
		$dateUser['DayRecord']['quote'] = $currentQuote;
		$dateUser['DayRecord']['tsumego'] = self::getTsumegoOfTheDay();
		$dateUser['DayRecord']['userbg'] = 0;
		$dateUser['DayRecord']['newTsumego'] = $this->getNewTsumego();
		$dateUser['DayRecord']['usercount'] = count($usersNum);
		$dateUser['DayRecord']['visitedproblems'] = $visitedProblems;
		$dateUser['DayRecord']['gems'] = $gemRand1 . '-' . $gemRand2 . '-' . $gemRand3;
		$dateUser['DayRecord']['gemCounter1'] = 0;
		$dateUser['DayRecord']['gemCounter2'] = 0;
		$dateUser['DayRecord']['gemCounter3'] = 0;
		ClassRegistry::init('DayRecord')->save($dateUser);

		ClassRegistry::init('AchievementCondition')->create();
		$achievementCondition = [];
		$achievementCondition['AchievementCondition']['user_id'] = $userOfTheDay['User']['id'];
		$achievementCondition['AchievementCondition']['set_id'] = 0;
		$achievementCondition['AchievementCondition']['category'] = 'uotd';
		$achievementCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);
	}

	public static function getTsumegoOfTheDay()
	{
		$ut = ClassRegistry::init('TsumegoRatingAttempt')->find('all', ['limit' => 10000, 'order' => 'created DESC', 'conditions' => ['status' => 'S']]) ?: [];
		$out = ClassRegistry::init('TsumegoAttempt')->find('all', ['limit' => 30000, 'order' => 'created DESC', 'conditions' => ['gain >=' => 40]]) ?: [];
		$date = date('Y-m-d', strtotime('yesterday'));
		$s = ClassRegistry::init('Schedule')->find('all', ['conditions' => ['date' => $date]]) ?: [];
		$ids = [];
		$utCount = count($ut);
		for ($i = 0; $i < $utCount; $i++)
		{
			$date2 = new DateTime($ut[$i]['TsumegoRatingAttempt']['created']);
			$date2 = $date2->format('Y-m-d');
			if ($date === $date2)
				array_push($ids, $ut[$i]['TsumegoRatingAttempt']['tsumego_id']);
		}
		$ids = array_count_values($ids);
		$highest = 0;
		$best = [];
		foreach ($ids as $key => $value)
			if ($value > $highest)
				$highest = $value;
		foreach ($ids as $key => $value)
			if ($value == $highest)
			{
				$x = [];
				$x[$key] = $value;
				array_push($best, $x);
			}
		$ids2 = [];
		$out2 = [];
		$outCount = count($out);
		for ($i = 0; $i < $outCount; $i++)
		{
			$date2 = new DateTime($out[$i]['TsumegoAttempt']['updated']);
			$date2 = $date2->format('Y-m-d');
			if ($date === $date2)
			{
				array_push($ids2, $out[$i]['TsumegoAttempt']['tsumego_id']);
				array_push($out2, $out[$i]);
			}
		}
		$ids2 = array_count_values($ids2);
		$highest = 0;
		$best2 = [];
		foreach ($ids2 as $key => $value)
			if ($value > $highest)
				$highest = $value;
		$done = false;
		$found = 0;
		$decrement = 0;
		$best3 = [];
		$findNum = 20;
		if (count($ids2))
			while (!$done)
			{
				foreach ($ids2 as $key => $value)
					if ($value == $highest - $decrement)
					{
						array_push($best2, $key);
						array_push($best3, $value);
						$found++;
					}
				$decrement++;
				if ($found < $findNum)
					$done = false;
				else
					$done = true;
			}
		$newBest = [];
		for ($j = 0; $j < $findNum; $j++)
		{
			$newBest[$j] = [];
			$newBest[$j]['sum'] = 0;
		}
		$out2Count = count($out2);
		for ($i = 0; $i < $out2Count; $i++)
			for ($j = 0; $j < $findNum; $j++)
				if ($out2[$i]['TsumegoAttempt']['tsumego_id'] == $best2[$j])
				{
					$x = [];
					$x['tid'] = $out2[$i]['TsumegoAttempt']['tsumego_id'];
					$tx = ClassRegistry::init('Tsumego')->findById($x['tid']);
					$scT = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
					$tx['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$x['sid'] = $tx['Tsumego']['set_id'];
					$x['status'] = $out2[$i]['TsumegoAttempt']['solved'];
					$x['seconds'] = $out2[$i]['TsumegoAttempt']['seconds'];

					$newBest[$j][] = $x;
				}
		$newBestCount = count($newBest);
		for ($i = 0; $i < $newBestCount; $i++)
		{
			$sum = 0;
			$newBestICount = count($newBest[$i]);
			for ($j = 0; $j < $newBestICount; $j++)
				if ($newBest[$i][$j]['seconds'] != null)
				{
					if ($newBest[$i][$j]['seconds'] > 300)
						$newBest[$i][$j]['seconds'] = 300;
					$sum += $newBest[$i][$j]['seconds'];
				}
			$sum = $sum * count($newBest[$i]);
			$newBest[$i]['sum'] = $sum;
		}
		$highest = 0;
		$hid = 0;
		$newBestCount = count($newBest);
		for ($i = 0; $i < $newBestCount; $i++)
			if (isset($newBest[$i]['sum'])
				&& $newBest[$i]['sum'] > $highest
				&& $newBest[$i][0]['sid'] != 104
				&& $newBest[$i][0]['sid'] != 105
				&& $newBest[$i][0]['sid'] != 117)
			{
				$yesterday = false;
				$sCount = count($s);
				for ($j = 0; $j < $sCount; $j++)
					if ($newBest[$i][0]['tid'] == $s[$j]['Schedule']['tsumego_id'])
						$yesterday = true;
				if (!$yesterday)
				{
					$highest = $newBest[$i]['sum'];
					$hid = $i;
				}
			}

		return $newBest[$hid][0]['tid'];
	}

	public static function getNewTsumego()
	{
		$date = date('Y-m-d', strtotime('today'));
		$todaysSchedule = ClassRegistry::init('Schedule')->find('all', ['conditions' => ['date' => $date]]) ?: [];
		$id = 0;
		foreach ($todaysSchedule as $item)
		{
			self::publishSingle($item['Schedule']['tsumego_id'], $item['Schedule']['set_id'], $item['Schedule']['date']);
			$item['Schedule']['published'] = 1;
			ClassRegistry::init('Schedule')->save($item);
		}

		return $id;
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
}
