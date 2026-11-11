<?php

use App\Utility\Auth;
use App\Utility\PlayOutcome;
use App\Utility\UserRatingDay;
use App\Utility\Util;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

//The highest rating of each day of a player (UserRatingDay, user_rating_day): kept with each attempt the site writes,
//filled for the days before by bin/cake backfill_rating_days
class UserRatingDayTest extends IntegrationTestCase
{
	use ConsoleIntegrationTestTrait;

	private function play(ContextPreparator $context, PlayOutcome $outcome): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 0, 'outcome' => $outcome->value]);
		$this->assertResponseOk();
	}

	/** @return array<string, int> the rating by day */
	private static function days(int $userID): array
	{
		return array_map('intval', array_column(Util::query('SELECT day, rating FROM user_rating_day WHERE user_id = ? ORDER BY day', [$userID]), 'rating', 'day'));
	}

	/** @return array<string, int> the highest user_rating of each day of the attempts */
	private static function attemptDays(int $userID): array
	{
		return array_map('intval', array_column(Util::query('SELECT DATE(created) AS day, MAX(user_rating) AS rating FROM tsumego_attempt WHERE user_id = ? GROUP BY DATE(created) ORDER BY day', [$userID]), 'rating', 'day'));
	}

	public function testANewAttemptKeepsTheRatingItWasWrittenWith(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => 1]);

		$this->play($context, PlayOutcome::SolvedClean);

		$attempt = Util::query('SELECT user_rating, DATE(created) AS day FROM tsumego_attempt WHERE user_id = ?', [$context->user['id']])[0];
		$this->assertSame([$attempt['day'] => (int) $attempt['user_rating']], self::days((int) $context->user['id']));
		$this->assertSame(date('Y-m-d'), $attempt['day']);
	}

	//A continued attempt moves to today; the day it was on keeps the rating the player had then
	public function testAContinuedAttemptKeepsTheRatingOfTodayAndLeavesItsOldDay(): void
	{
		$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
		$context = new ContextPreparator([
			'user' => ['rating' => 1500],
			'tsumego' => ['attempt' => ['solved' => false, 'user_rating' => 1400, 'created' => $threeDaysAgo . ' 10:00:00'], 'set_order' => 1]]);
		$userID = (int) $context->user['id'];
		$this->assertSame([$threeDaysAgo => 1400], self::days($userID));

		$this->play($context, PlayOutcome::Failed);

		$this->assertCount(1, Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the attempt was continued');
		$today = Util::query('SELECT user_rating FROM tsumego_attempt WHERE user_id = ?', [$userID])[0]['user_rating'];
		$this->assertSame([$threeDaysAgo => 1400, date('Y-m-d') => (int) $today], self::days($userID));
	}

	//A day keeps its highest rating, however the attempts of the day go
	public function testADayKeepsItsHighestRating(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userID = (int) $context->user['id'];

		UserRatingDay::record($userID, '2026-03-04', 1500.4);
		UserRatingDay::record($userID, '2026-03-04', 1450);
		$this->assertSame(['2026-03-04' => 1500], self::days($userID), 'a lower rating later keeps the higher');
		UserRatingDay::record($userID, '2026-03-04', 1520.6);
		$this->assertSame(['2026-03-04' => 1521], self::days($userID), 'a higher one replaces it, rounded as user_rating is');
	}

	public function testAnAttemptWithoutARatingKeepsNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		UserRatingDay::record((int) $context->user['id'], '2026-03-04', null);

		$this->assertSame([], self::days((int) $context->user['id']));
	}

	//The command fills the days of the attempts written before the table was: the highest user_rating of each day of
	//each player, the same as grouping the attempts, and a second run changes nothing
	public function testTheBackfillMatchesTheAttemptsAndCanRunAgain(): void
	{
		$attempt = fn(int $daysAgo, int $rating, string $time = '10:00:00') => ['solved' => true, 'user_rating' => $rating, 'created' => date('Y-m-d', strtotime('-' . $daysAgo . ' days')) . ' ' . $time];
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => [
				$attempt(400, 1200), $attempt(400, 1260, '23:59:59'), $attempt(400, 1230, '00:00:00'),
				$attempt(30, 1500), $attempt(2, 1480), $attempt(2, 1490, '12:00:00')]]]]);
		$userID = (int) $context->user['id'];
		$bobID = (int) $context->otherUsers[0]['id'];
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, 1, 0, ?, 1000, ?)',
			[$bobID, $context->tsumegos[0]['id'], 900, date('Y-m-d', strtotime('-5 days')) . ' 08:00:00']);
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, 0, 0, NULL, 1000, ?)',
			[$bobID, $context->tsumegos[0]['id'], date('Y-m-d', strtotime('-6 days')) . ' 08:00:00']);
		Util::execute('DELETE FROM user_rating_day');
		// a day already kept higher than its attempts stays as it is
		UserRatingDay::record($userID, date('Y-m-d', strtotime('-2 days')), 1600);

		$this->exec('backfill_rating_days');
		$this->assertExitSuccess();
		$this->assertOutputContains('backfill_rating_days: 4 days of 2 players');
		$this->assertErrorEmpty();

		$expected = self::attemptDays($userID);
		$expected[date('Y-m-d', strtotime('-2 days'))] = 1600;
		$this->assertSame($expected, self::days($userID));
		$this->assertSame([date('Y-m-d', strtotime('-5 days')) => 900], self::days($bobID), 'an attempt without a rating fills no day');

		$this->exec('backfill_rating_days');
		$this->assertExitSuccess();
		$this->assertSame($expected, self::days($userID), 'a second run changes nothing');
		$this->assertSame([date('Y-m-d', strtotime('-5 days')) => 900], self::days($bobID));
	}

	//The batches of players go through all of them
	public function testTheBackfillGoesThroughEveryBatch(): void
	{
		$attempt = fn(int $userIndex) => ['solved' => true, 'user_rating' => 1000 + $userIndex, 'created' => date('Y-m-d') . ' 10:00:00'];
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob'], ['name' => 'carol']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => [$attempt(0)]]]]);
		foreach ($context->otherUsers as $index => $user)
			Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, 1, 0, ?, 1000, NOW())',
				[$user['id'], $context->tsumegos[0]['id'], 1001 + $index]);
		Util::execute('DELETE FROM user_rating_day');

		$this->assertSame(['players' => 3, 'days' => 3], UserRatingDay::backfill(1));
		$this->assertSame(3, (int) Util::query('SELECT COUNT(*) AS days FROM user_rating_day')[0]['days']);
	}
}
