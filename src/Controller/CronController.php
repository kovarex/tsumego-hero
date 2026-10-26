<?php

use App\Attribute\HttpGet;
use App\Utility\SchedulePublisher;
use App\Utility\Trash;
use App\Utility\Util;

class CronController extends AppController
{
	/* Supposed to be ran daily to reset hearts and hero powers */
	#[HttpGet]
	public function daily(string $secret)
	{
		if ($secret != CRON_SECRET)
			throw new ForbiddenException('Wrong cron secret.');

		// Publishing takes only the entries that are still pending, so it repeats on
		// every call and catches up the days the cron did not run on.
		SchedulePublisher::publishDue();

		// Already ran today - the rest of the work is done once a day
		if (ClassRegistry::init('DayRecord')->find('count', ['conditions' => ['date' => date('Y-m-d')]]) > 0)
		{
			$this->response->statusCode(200);
			return $this->response;
		}

		self::withReadCommitted(function () {
			$this->dailyTsumegoStatusReset();
			$this->dailyStalingSolvedTsumegoStatuses();
			self::createDayRecord();
			$this->dailyUsersReset();
			$this->dailyPotionConditionReset();
			self::updateSolvedCounts();
			self::dropExpiredAddressChanges();
			Trash::purgeExpired();
		});

		$this->response->statusCode(200);
		return $this->response;
	}

	/**
	 * The daily updates go through whole tables, no index fits their conditions. Under the default
	 * REPEATABLE READ, InnoDB keeps every row such an update reads locked until the update ends, so a
	 * player whose row it already passed waits for the rest of the table. READ COMMITTED lets go of
	 * the rows that do not match right away.
	 * With a binary log in the STATEMENT format, InnoDB refuses to change rows under READ COMMITTED
	 * (error 1665), so on such a server the work keeps the level it has.
	 */
	private static function withReadCommitted(callable $work): void
	{
		$session = Util::query("SELECT @@SESSION.tx_isolation AS isolation,
			@@GLOBAL.log_bin AND @@SESSION.binlog_format = 'STATEMENT' AS statement_log")[0];
		if ($session['statement_log'])
		{
			$work();
			return;
		}

		Util::execute('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
		try
		{
			$work();
		}
		finally
		{
			Util::execute('SET SESSION tx_isolation = ?', [$session['isolation']]);
		}
	}

	/**
	 * Forgets address changes whose links no longer work, so a change that was never
	 * confirmed does not stay on the account. The lifetime is the one the links were
	 * sent with, so a change stays exactly as long as its links work.
	 */
	private static function dropExpiredAddressChanges(): void
	{
		Util::execute('UPDATE user
			SET unconfirmed_email = NULL, unconfirmed_email_token = NULL,
				unconfirmed_email_cancel_token = NULL, unconfirmed_email_created = NULL
			WHERE unconfirmed_email IS NOT NULL
				AND unconfirmed_email_created <= NOW() - INTERVAL ? HOUR',
			[UsersController::$EMAIL_CHANGE_LINK_VALID_HOURS]);
	}

	/**
	 * Only the players who played that day have anything to reset. The condition leaves the other
	 * rows alone, so they are not written, and not kept locked either.
	 */
	private function dailyUsersReset(): void
	{
		Util::execute('UPDATE user
			SET used_refinement = 0, used_sprint = 0, used_rejuvenation = 0, used_potion = 0,
				used_intuition = 0, used_revelation = 0, damage = 0, daily_xp = 0, daily_solved = 0
			WHERE used_refinement <> 0 OR used_sprint <> 0 OR used_rejuvenation <> 0 OR used_potion <> 0
				OR used_intuition <> 0 OR used_revelation <> 0 OR damage <> 0 OR daily_xp <> 0 OR daily_solved <> 0');
	}

	private function dailyPotionConditionReset(): void
	{
		Util::execute('UPDATE achievement_condition SET value = 0 WHERE achievement_condition_category_id = ? AND value <> 0', [AchievementCondition::CATEGORY_POTION]);
	}

	private function dailyTsumegoStatusReset(): void
	{
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='V' where status='F'");
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='W' where status='X'");
	}

	private function dailyStalingSolvedTsumegoStatuses(): void
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

		$topUsers = ClassRegistry::init('User')->find('all', ['conditions' => ['deleted IS NULL'], 'limit' => '8', 'order' => 'daily_xp DESC']) ?: [];
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
		for ($i = 1; $i < 13; $i++)
			$allQuotes[] = sprintf('q%02d', $i);
		shuffle($allQuotes);
		foreach ($allQuotes as $quote)
			if (!isset($usedQuotes[$quote]))
				return $quote;
		throw new Exception("Quote couldn't be generated");
	}

	private function createDayRecord(): void
	{
		$today = date('Y-m-d');
		if (ClassRegistry::init('DayRecord')->find('count', ['conditions' => ['date' => $today]]) > 0)
			return;

		$userOfTheDay = self::deduceUserOfTheDay();
		$currentQuote = self::deduceQuoteToUse();

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
		$dayRecord['DayRecord']['quote'] = $currentQuote;
		$dayRecord['DayRecord']['gems'] = $gemRand1 . '-' . $gemRand2 . '-' . $gemRand3;
		$dayRecord['DayRecord']['emerald_solves'] = 0;
		$dayRecord['DayRecord']['sapphire_solves'] = 0;
		$dayRecord['DayRecord']['ruby_solves'] = 0;
		ClassRegistry::init('DayRecord')->save($dayRecord);

		AchievementCondition::put((int) $userOfTheDay['User']['id'], AchievementCondition::CATEGORY_USER_OF_THE_DAY, 1);
	}

	/**
	 * The solved count on the user row goes up as the player solves, the recount repairs it where it
	 * drifted. The counts are read by a plain SELECT, which locks nothing: an UPDATE joined to the same
	 * counts kept every status it read locked until it ended, so a player solving a problem at that
	 * time waited for all of them. Only the players whose count changed are written.
	 */
	private static function updateSolvedCounts(): void
	{
		$changes = Util::query("SELECT user.id, COALESCE(counts.solved, 0) AS solved
			FROM user
			LEFT JOIN (
				SELECT user_id, COUNT(*) AS solved
				FROM tsumego_status
				WHERE status IN ('S', 'W', 'C', 'X')
				GROUP BY user_id
			) AS counts ON counts.user_id = user.id
			WHERE NOT (user.solved <=> COALESCE(counts.solved, 0))");
		foreach ($changes as $change)
			Util::execute('UPDATE user SET solved = ? WHERE id = ?', [$change['solved'], $change['id']]);
	}
}
