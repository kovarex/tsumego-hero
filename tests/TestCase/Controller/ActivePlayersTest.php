<?php

use App\Utility\ActivePlayers;
use App\Utility\ChartRanges;
use App\Utility\UserDay;
use App\Utility\Util;

//The active players of the home page (ActivePlayers): the players who solved a problem each day, the days that are
//over, stored in active_players_day (today, still going on, isn't drawn), and the weeks and months as averages a day
class ActivePlayersTest extends IntegrationTestCase
{
	private static function day(int $daysAgo): string
	{
		return date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
	}

	//alice solves 10 days ago, 3 days ago and today; bob fails 3 days ago and solves 2 days ago; carol only fails
	private function prepare(): ContextPreparator
	{
		$attempt = fn(int $daysAgo, bool $solved, ?int $userID = null) => ['solved' => $solved, 'created' => self::day($daysAgo) . ' 10:00:00'] + ($userID ? ['user_id' => $userID] : []);
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob'], ['name' => 'carol']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]]]]]);
		[$bob, $carol] = array_map(fn($user) => (int) $user['id'], $context->otherUsers);
		$this->attempts($context, [
			$attempt(10, true), $attempt(3, true), $attempt(0, true),
			$attempt(3, false, $bob), $attempt(2, true, $bob),
			$attempt(2, false, $carol), $attempt(0, false, $carol)]);
		ActivePlayers::forget();
		return $context;
	}

	private function attempts(ContextPreparator $context, array $attempts): void
	{
		foreach ($attempts as $attempt)
		{
			$userID = $attempt['user_id'] ?? (int) $context->user['id'];
			Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, ?, 0, 1500, 1000, ?)',
				[$userID, $context->tsumegos[0]['id'], $attempt['solved'] ? 1 : 0, $attempt['created']]);
			UserDay::record($userID, substr($attempt['created'], 0, 10), 1500, $attempt['solved']);
		}
	}

	/** @return array<string, int> */
	private static function stored(): array
	{
		return array_map('intval', array_column(Util::query('SELECT day, players FROM active_players_day ORDER BY day'), 'players', 'day'));
	}

	//The days before today come from the stored days, which the first read stores when the cron has not
	public function testThePlayersOfEachDayBeforeToday(): void
	{
		$this->prepare();

		$expected = array_fill_keys(array_map(fn($daysAgo) => self::day($daysAgo), range(10, 1)), 0);
		$expected[self::day(10)] = 1;
		$expected[self::day(3)] = 1;
		$expected[self::day(2)] = 1;
		$this->assertSame($expected, ActivePlayers::history(date('Y-m-d')), 'a day nobody solved is 0, a fail is no solve');
		$this->assertSame($expected, self::stored(), 'stored, today is not');
		$this->assertSame(1, ActivePlayers::count(date('Y-m-d')), 'today so far');
	}

	//The chart ends with yesterday: today, still going on, would be its lowest point at the right end
	public function testTheLastPointIsYesterday(): void
	{
		$this->prepare();

		$month = ActivePlayers::ranges()['1M'];
		$this->assertSame(['day', null], [$month['period'], $month['partial']]);
		$this->assertSame([self::day(1), 0], end($month['points']), 'yesterday, when nobody solved');
		$this->assertNotContains(date('Y-m-d'), array_column($month['points'], 0), 'alice solved today, and today is not drawn');
		$this->assertCount(10, $month['points'], 'every day from the first solve to yesterday, the days without one at 0');
	}

	//A stored day is read, never counted again: a solve written into it later does not change it
	public function testTheChartReadsTheStoredDays(): void
	{
		$context = $this->prepare();
		$today = date('Y-m-d');
		ActivePlayers::storeDays($today);
		Util::execute('UPDATE active_players_day SET players = 42 WHERE day = ?', [self::day(5)]);
		$this->attempts($context, [['solved' => true, 'created' => self::day(1) . ' 10:00:00', 'user_id' => (int) $context->otherUsers[1]['id']]]);

		$history = ActivePlayers::history($today);

		$this->assertSame(42, $history[self::day(5)], 'the stored count');
		$this->assertSame(0, $history[self::day(1)], 'not counted again');
	}

	//The cron stores the days after the last one stored up to yesterday, so it catches up the days it missed, and a
	//second run stores nothing
	public function testStoringCatchesUpAndASecondRunChangesNothing(): void
	{
		$this->prepare();
		$today = date('Y-m-d');

		$this->assertSame(10, ActivePlayers::storeDays($today), 'every day from the first solve to yesterday');
		$stored = self::stored();
		$this->assertSame(0, ActivePlayers::storeDays($today));
		$this->assertSame($stored, self::stored());

		Util::execute('DELETE FROM active_players_day WHERE day >= ?', [self::day(3)]);
		$this->assertSame(3, ActivePlayers::storeDays($today), 'the three days missed');
		$this->assertSame($stored, self::stored());
	}

	//A solve today doesn't change the chart, which has the days that are over
	public function testASolveTodayIsNotInTheChart(): void
	{
		$context = $this->prepare();
		$before = ActivePlayers::ranges();

		$this->attempts($context, [['solved' => true, 'created' => date('Y-m-d') . ' 11:00:00', 'user_id' => (int) $context->otherUsers[0]['id']]]);
		ActivePlayers::forget();

		$this->assertSame(2, ActivePlayers::count(date('Y-m-d')), 'two players solved today');
		$this->assertSame($before, ActivePlayers::ranges());
	}

	//A week or a month is the average players a day of its days, a day without a solve counting as 0, and the day
	//still going on stays out of it
	public function testAWeekIsTheAveragePlayersADay(): void
	{
		$days = [];
		for ($daysAgo = 400; $daysAgo >= 1; $daysAgo--)
			$days[self::day($daysAgo)] = $daysAgo % 2 ? 4 : 0;
		$today = date('Y-m-d');
		$days[$today] = 100;

		$ranges = ChartRanges::build($days, true, $today);

		$this->assertSame(['week', null], [$ranges['1Y']['period'], $ranges['1Y']['partial']]);
		foreach (array_slice($ranges['1Y']['points'], 1, -1) as [$monday, $average])
			$this->assertContains($average, [1.7, 2.0, 2.3], 'a full week of 4, 0, 4, ... is 2 a day, give or take a day: ' . $monday);
		$this->assertLessThanOrEqual(4, max(array_column($ranges['1Y']['points'], 1)), 'today, 100, is not in a week');
		$this->assertSame([$today, 100], end($ranges['1M']['points']), 'but it is the last day of a month in days');
		$this->assertSame($today, $ranges['1M']['partial']);
	}
}
