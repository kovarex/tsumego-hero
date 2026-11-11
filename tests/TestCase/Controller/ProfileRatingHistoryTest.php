<?php

use App\Utility\UserProfile;
use App\Utility\Util;

//The rating chart of the profile in ranges (UserProfile::ratingHistory, /users/ratingHistory/{id}?range=1M|6M|1Y|All):
//the highest rating of each day, or of each week or month once a range would draw more than RATING_POINTS days, from
//the kept day ratings (user_rating_day), which the fixture writes with each attempt as the site does.
class ProfileRatingHistoryTest extends IntegrationTestCase
{
	private static function day(int $daysAgo): string
	{
		return date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
	}

	//The Monday of the week of the day
	private static function week(string $day): string
	{
		return date('Y-m-d', strtotime($day . ' -' . (date('N', strtotime($day)) - 1) . ' days'));
	}

	//The rating of the attempts, by how many days ago they were: two the same day, the higher one counts
	private const array RATINGS = [[1800, 1200], [300, 1300], [100, 1400], [10, 1500], [10, 1520], [3, 1490]];

	private function prepare(array $ratings = self::RATINGS): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => array_map(fn($rating) => [
				'solved' => true,
				'user_rating' => $rating[1],
				'created' => self::day($rating[0]) . ' 10:00:00'], $ratings)]]]);
	}

	//The expected points: the highest rating of each period from the attempts at most $days ago
	private static function points(int $days, callable $periodStart): array
	{
		$points = [];
		foreach (self::RATINGS as [$daysAgo, $rating])
			if ($daysAgo <= $days)
				$points[$periodStart(self::day($daysAgo))] = max($points[$periodStart(self::day($daysAgo))] ?? 0, $rating);
		ksort($points);
		return array_map(fn($start) => [$start, $points[$start]], array_keys($points));
	}

	public function testTheRangesGoBackAMonthHalfAYearAYearAndToTheFirstAttempt(): void
	{
		$userID = (int) $this->prepare()->user['id'];
		$day = fn(string $day) => $day;

		$this->assertSame(['range' => '1M', 'period' => 'day', 'points' => [[self::day(10), 1520], [self::day(3), 1490]]], UserProfile::ratingHistory($userID, '1M'));
		$this->assertSame(['range' => '6M', 'period' => 'day', 'points' => self::points(100, $day)], UserProfile::ratingHistory($userID, '6M'));
		$this->assertSame(['range' => '1Y', 'period' => 'week', 'points' => self::points(300, fn($day) => self::week($day))], UserProfile::ratingHistory($userID, '1Y'), 'a year in weeks');
		$this->assertSame(['range' => 'All', 'period' => 'month', 'points' => self::points(1800, fn($day) => substr($day, 0, 7) . '-01')], UserProfile::ratingHistory($userID, 'All'), 'five years in months');
	}

	//All of it is as fine as its length allows: a player of a few weeks gets a point a day
	public function testAllOfAShortHistoryIsInDays(): void
	{
		$userID = (int) $this->prepare([[20, 1450], [5, 1470]])->user['id'];

		$this->assertSame(['range' => 'All', 'period' => 'day', 'points' => [[self::day(20), 1450], [self::day(5), 1470]]], UserProfile::ratingHistory($userID, 'All'));
	}

	public function testAnUnknownRangeIsHalfAYear(): void
	{
		$userID = (int) $this->prepare()->user['id'];

		$this->assertSame(UserProfile::ratingHistory($userID, '6M'), UserProfile::ratingHistory($userID, '2W'));
		$this->assertSame(UserProfile::ratingHistory($userID, '6M'), UserProfile::ratingHistory($userID));
	}

	//The ranges read the kept day ratings (user_rating_day), not the attempts: before the backfill a day played
	//before the table was is not on the chart, and All starts with the first kept day
	public function testTheRangesReadTheKeptDays(): void
	{
		$userID = (int) $this->prepare()->user['id'];
		Util::execute('DELETE FROM user_rating_day WHERE day < ?', [self::day(50)]);
		Util::execute('UPDATE user_rating_day SET rating = 1700 WHERE day = ?', [self::day(3)]);

		$this->assertSame([[self::day(10), 1520], [self::day(3), 1700]], UserProfile::ratingHistory($userID, '1M')['points'], 'the kept rating, not the attempts\'');
		$all = UserProfile::ratingHistory($userID, 'All');
		$this->assertSame(['day', [[self::day(10), 1520], [self::day(3), 1700]]], [$all['period'], $all['points']], 'all of it is the ten days kept, a point a day');
	}

	public function testAPlayerWithoutAttemptsHasNoPoints(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		$this->assertSame(['range' => 'All', 'period' => 'day', 'points' => []], UserProfile::ratingHistory((int) $context->user['id'], 'All'));
	}

	public function testTheRangeAsJson(): void
	{
		$context = $this->prepare();
		$userID = (int) $context->user['id'];
		$this->logout();

		$this->get('/users/ratingHistory/' . $userID . '?range=1Y');
		$this->assertResponseOk();
		$this->assertSame(UserProfile::ratingHistory($userID, '1Y'), json_decode($this->view, true));

		$this->get('/users/ratingHistory/' . $userID . '?range=All');
		$this->assertSame('All', json_decode($this->view, true)['range']);
		$this->get('/users/ratingHistory/' . $userID . '?range=nonsense');
		$this->assertSame('6M', json_decode($this->view, true)['range']);
		$this->get('/users/ratingHistory/' . $userID . '?range[]=1Y');
		$this->assertSame('6M', json_decode($this->view, true)['range']);
		$this->get('/users/ratingHistory/' . $userID);
		$this->assertSame('6M', json_decode($this->view, true)['range'], 'half a year by default');
	}

	public function testAnUnknownPlayerIsNotFound(): void
	{
		new ContextPreparator([]);

		$this->get('/users/ratingHistory/999999');
		$this->assertResponseCode(404);
	}

	//The profile prints half a year, so the chart needs no request to show
	public function testTheProfilePrintsHalfAYear(): void
	{
		$context = $this->prepare();
		$userID = (int) $context->user['id'];

		$this->get('/users/view/' . $userID);

		$this->assertStringContainsString(h(json_encode(['playerId' => $userID, 'ranges' => ['1M', '6M', '1Y', 'All'], 'history' => UserProfile::ratingHistory($userID)])), $this->view);
	}
}
