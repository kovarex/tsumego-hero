<?php

use App\Utility\UserProfile;
use App\Utility\Util;

//The rating chart of the profile in ranges 1M, 6M, 1Y and All (UserProfile::ratingRanges), all printed with the page:
//the highest rating of each day, or of each week or month once a range would draw more than ChartRanges::POINTS days, from
//the kept day ratings (user_day), which the fixture writes with each attempt as the site does.
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

		$this->assertSame([
			'1M' => ['period' => 'day', 'points' => [[self::day(10), 1520], [self::day(3), 1490]], 'partial' => null],
			'6M' => ['period' => 'day', 'points' => self::points(100, $day), 'partial' => null],
			'1Y' => ['period' => 'week', 'points' => self::points(300, fn($day) => self::week($day)), 'partial' => null],
			'All' => ['period' => 'month', 'points' => self::points(1800, fn($day) => substr($day, 0, 7) . '-01'), 'partial' => null]], UserProfile::ratingRanges($userID),
			'a year in weeks, five years in months');
	}

	//All of it is as fine as its length allows: a player of a few weeks gets a point a day
	public function testAllOfAShortHistoryIsInDays(): void
	{
		$userID = (int) $this->prepare([[20, 1450], [5, 1470]])->user['id'];

		$this->assertSame(['period' => 'day', 'points' => [[self::day(20), 1450], [self::day(5), 1470]], 'partial' => null], UserProfile::ratingRanges($userID)['All']);
	}

	//The ranges read the kept days (user_day), not the attempts: a day without a rating is left out, and All starts with
	//the first kept day with one
	public function testTheRangesReadTheKeptDays(): void
	{
		$userID = (int) $this->prepare()->user['id'];
		Util::execute('DELETE FROM user_day WHERE day < ?', [self::day(50)]);
		Util::execute('UPDATE user_day SET rating = 1700 WHERE day = ?', [self::day(3)]);
		Util::execute('INSERT INTO user_day (user_id, day, rating, solved) VALUES (?, ?, NULL, 1)', [$userID, self::day(40)]);

		$ranges = UserProfile::ratingRanges($userID);
		$this->assertSame([[self::day(10), 1520], [self::day(3), 1700]], $ranges['1M']['points'], 'the kept rating, not the attempts\'');
		$this->assertSame(['period' => 'day', 'points' => [[self::day(10), 1520], [self::day(3), 1700]], 'partial' => null], $ranges['All'], 'all of it is the ten days kept, a point a day');
	}

	public function testAPlayerWithoutAttemptsHasNoPoints(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		$this->assertSame([
			'1M' => ['period' => 'day', 'points' => [], 'partial' => null],
			'6M' => ['period' => 'day', 'points' => [], 'partial' => null],
			'1Y' => ['period' => 'week', 'points' => [], 'partial' => null],
			'All' => ['period' => 'day', 'points' => [], 'partial' => null]], UserProfile::ratingRanges((int) $context->user['id']));
	}

	//The profile prints every range, so the buttons switch without a request
	public function testTheProfilePrintsEveryRange(): void
	{
		$context = $this->prepare();
		$userID = (int) $context->user['id'];
		$this->logout();

		$this->get('/users/view/' . $userID);

		$this->assertResponseOk();
		$this->assertStringContainsString(h(json_encode(['title' => 'Overall rating', 'label' => 'Rating', 'color' => '#74d14c', 'ranges' => UserProfile::ratingRanges($userID), 'initial' => '6M', 'rankAxis' => true, 'startAtZero' => false])), $this->view);
	}
}
