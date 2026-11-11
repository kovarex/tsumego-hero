<?php

use App\Utility\ActivePlayers;
use App\Utility\ChartRanges;
use App\Utility\UserDay;
use App\Utility\Util;

//The active players of the home page (ActivePlayers): the players who solved a problem each day, the days before
//today kept for the day in the cache, today counted live, and the weeks and months as averages a day
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

	public function testThePlayersOfEachDayBeforeToday(): void
	{
		$this->prepare();

		$expected = array_fill_keys(array_map(fn($daysAgo) => self::day($daysAgo), range(10, 1)), 0);
		$expected[self::day(10)] = 1;
		$expected[self::day(3)] = 1;
		$expected[self::day(2)] = 1;
		$this->assertSame($expected, ActivePlayers::history(date('Y-m-d')), 'a day nobody solved is 0, a fail is no solve');
		$this->assertSame(1, ActivePlayers::count(date('Y-m-d')), 'today so far');
	}

	//Today is the last point of the ranges in days, marked as partial
	public function testTodayIsAPointSoFar(): void
	{
		$this->prepare();

		$month = ActivePlayers::ranges()['1M'];
		$this->assertSame(['day', date('Y-m-d')], [$month['period'], $month['partial']]);
		$this->assertSame([date('Y-m-d'), 1], end($month['points']));
		$this->assertCount(11, $month['points'], 'every day from the first solve, the days without one at 0');
	}

	//The days before today are counted once a day: a solve of yesterday written after the count shows the next day,
	//or once the cache is dropped; today is always counted again
	public function testTheDaysBeforeTodayAreKeptForTheDay(): void
	{
		$context = $this->prepare();
		$today = date('Y-m-d');
		$before = ActivePlayers::history($today);

		$this->attempts($context, [['solved' => true, 'created' => self::day(1) . ' 10:00:00', 'user_id' => (int) $context->otherUsers[1]['id']]]);
		$this->attempts($context, [['solved' => true, 'created' => $today . ' 11:00:00', 'user_id' => (int) $context->otherUsers[1]['id']]]);

		$this->assertSame($before, ActivePlayers::history($today), 'kept');
		$this->assertSame(2, ActivePlayers::count($today), 'today is live');
		$this->assertSame(1, ActivePlayers::history(date('Y-m-d', strtotime('+1 day')))[self::day(1)], 'counted again on the next day');
		ActivePlayers::forget();
		$this->assertSame(1, ActivePlayers::history($today)[self::day(1)], 'or once it is dropped');
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
