<?php

use App\Attribute\HttpGet;
use App\Model\Table\AchievementConditionTable;
use App\Utility\SchedulePublisher;
use App\Utility\Trash;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

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
		if ($this->fetchTable('DayRecord')->find()->where(['date' => date('Y-m-d')])->count() > 0)
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

	//The daily updates go through whole tables, no index fits their conditions. Under the default
	//REPEATABLE READ, InnoDB keeps every row such an update reads locked until the update ends, so a
	//player whose row it already passed waits for the rest of the table. READ COMMITTED lets go of
	//the rows that do not match right away.
	//With a binary log in the STATEMENT format, InnoDB refuses to change rows under READ COMMITTED
	//(error 1665), so on such a server the work keeps the level it has.
	private static function withReadCommitted(callable $work): void
	{
		if (Util::query("SELECT @@GLOBAL.log_bin AND @@SESSION.binlog_format = 'STATEMENT' AS statement_log")[0]['statement_log'])
		{
			$work();
			return;
		}

		$isolation = Util::isolationLevel();
		Util::execute('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
		try
		{
			$work();
		}
		finally
		{
			// the server names the level with a dash, as in REPEATABLE-READ
			Util::execute('SET SESSION TRANSACTION ISOLATION LEVEL ' . str_replace('-', ' ', $isolation));
		}
	}

	//Forgets address changes whose links no longer work, so a change that was never
	//confirmed does not stay on the account. The lifetime is the one the links were
	//sent with, so a change stays exactly as long as its links work.
	private static function dropExpiredAddressChanges(): void
	{
		Util::execute('UPDATE user
			SET unconfirmed_email = NULL, unconfirmed_email_token = NULL,
				unconfirmed_email_cancel_token = NULL, unconfirmed_email_created = NULL
			WHERE unconfirmed_email IS NOT NULL
				AND unconfirmed_email_created <= NOW() - INTERVAL ? HOUR',
			[UsersController::$EMAIL_CHANGE_LINK_VALID_HOURS]);
	}

	//Only the players who played that day have anything to reset. The condition leaves the other
	//rows alone, so they are not written, and not kept locked either.
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
		Util::execute('UPDATE achievement_condition SET value = 0 WHERE achievement_condition_category_id = ? AND value <> 0', [AchievementConditionTable::CATEGORY_POTION]);
	}

	private function dailyTsumegoStatusReset(): void
	{
		Util::execute("UPDATE tsumego_status SET status='V' where status='F'");
		Util::execute("UPDATE tsumego_status SET status='W' where status='X'");
	}

	private function dailyStalingSolvedTsumegoStatuses(): void
	{
		Util::execute("
UPDATE tsumego_status
SET status='W'
WHERE
	status='S' AND
	tsumego_status.updated < '" . date('Y-m-d H:i:s', strtotime('-7 days')) . "'");
	}

	private static function deduceUserOfTheDay(): ?int
	{
		$lastDayRecords = TableRegistry::getTableLocator()->get('DayRecord')->find()->orderByDesc('date')->limit(7)->all();

		$excludedUsers = [];
		foreach ($lastDayRecords as $lastDayRecord)
			$excludedUsers[$lastDayRecord->user_id] = true;

		$topUsers = TableRegistry::getTableLocator()->get('User')->find()
			->select(['id'])
			->where(['deleted IS' => null])
			->orderByDesc('daily_xp')
			->limit(8)
			->all();
		foreach ($topUsers as $user)
			if (!isset($excludedUsers[$user->id]))
				return $user->id;
		return null;
	}

	private static function deduceQuoteToUse(): string
	{
		$latestDayRecords = TableRegistry::getTableLocator()->get('DayRecord')->find()->where(['date' => date('Y-m-d', strtotime('-10 days'))])->all();
		$usedQuotes = [];
		foreach ($latestDayRecords as $latestDayRecord)
			$usedQuotes[$latestDayRecord->quote] = true;

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
		$dayRecordTable = $this->fetchTable('DayRecord');
		if ($dayRecordTable->find()->where(['date' => $today])->count() > 0)
			return;

		$userOfTheDay = self::deduceUserOfTheDay();
		$currentQuote = self::deduceQuoteToUse();

		$gemRand1 = rand(0, 2);
		$gemRand2 = rand(0, 2);
		$gemRand3 = rand(0, 2);

		$achievementTable = $this->fetchTable('Achievement');
		$arch1 = $achievementTable->get(111);
		if ($gemRand1 == 0)
			$arch1->description = 'Has a chance to trigger once a day on an easy ddk problem.';
		elseif ($gemRand1 == 1)
			$arch1->description = 'Has a chance to trigger once a day on a regular ddk problem.';
		elseif ($gemRand1 == 2)
			$arch1->description = 'Has a chance to trigger once a day on a difficult ddk problem.';
		$achievementTable->saveOrFail($arch1);
		$arch2 = $achievementTable->get(112);
		if ($gemRand2 == 0)
			$arch2->description = 'Has a chance to trigger once a day on an easy sdk problem.';
		elseif ($gemRand2 == 1)
			$arch2->description = 'Has a chance to trigger once a day on a regular sdk problem.';
		elseif ($gemRand2 == 2)
			$arch2->description = 'Has a chance to trigger once a day on a difficult sdk problem.';
		$achievementTable->saveOrFail($arch2);
		$arch3 = $achievementTable->get(113);
		if ($gemRand3 == 0)
			$arch3->description = 'Has a chance to trigger once a day on an easy dan problem.';
		elseif ($gemRand3 == 1)
			$arch3->description = 'Has a chance to trigger once a day on a regular dan problem.';
		elseif ($gemRand3 == 2)
			$arch3->description = 'Has a chance to trigger once a day on a difficult dan problem.';
		$achievementTable->saveOrFail($arch3);

		$dayRecordTable->saveOrFail($dayRecordTable->newEntity([
			'user_id' => $userOfTheDay,
			'date' => $today,
			'quote' => $currentQuote,
			'gems' => $gemRand1 . '-' . $gemRand2 . '-' . $gemRand3,
			'emerald_solves' => 0,
			'sapphire_solves' => 0,
			'ruby_solves' => 0,
		]));

		AchievementConditionTable::put($userOfTheDay, AchievementConditionTable::CATEGORY_USER_OF_THE_DAY, 1);
	}

	//The solved count on the user row goes up as the player solves, the recount repairs it where it
	//drifted. The counts are read by a plain SELECT, which locks nothing: an UPDATE joined to the same
	//counts kept every status it read locked until it ended, so a player solving a problem at that
	//time waited for all of them. Only the players whose count changed are written.
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
