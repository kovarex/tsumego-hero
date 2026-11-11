<?php

use App\Utility\Auth;
use App\Utility\PlayOutcome;
use App\Utility\UserDay;
use App\Utility\Util;

//The days each player played (UserDay, user_day): kept with each attempt the site writes, with the day's highest
//rating and whether a problem was solved that day
class UserDayTest extends IntegrationTestCase
{
	private function play(ContextPreparator $context, PlayOutcome $outcome): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 0, 'outcome' => $outcome->value]);
		$this->assertResponseOk();
	}

	/** @return array<string, array{0: ?int, 1: int}> [rating, solved] by day */
	private static function days(int $userID): array
	{
		$days = [];
		foreach (Util::query('SELECT day, rating, solved FROM user_day WHERE user_id = ? ORDER BY day', [$userID]) as $row)
			$days[$row['day']] = [$row['rating'] === null ? null : (int) $row['rating'], (int) $row['solved']];
		return $days;
	}

	private static function attemptRating(int $userID): int
	{
		return (int) Util::query('SELECT user_rating FROM tsumego_attempt WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userID])[0]['user_rating'];
	}

	public function testASolvedAttemptKeepsItsRatingAndTheDayAsSolved(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => 1]);
		$userID = (int) $context->user['id'];

		$this->play($context, PlayOutcome::SolvedClean);

		$this->assertSame([date('Y-m-d') => [self::attemptRating($userID), 1]], self::days($userID));
	}

	public function testAFailedAttemptKeepsTheDayAsNotSolved(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => 1]);
		$userID = (int) $context->user['id'];

		$this->play($context, PlayOutcome::Failed);

		$this->assertSame([date('Y-m-d') => [self::attemptRating($userID), 0]], self::days($userID));
	}

	//A fail and then the solve of the same attempt, the same day: the day is solved
	public function testASolveAfterAFailTheSameDayMakesTheDaySolved(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => 1]);
		$userID = (int) $context->user['id'];

		$this->play($context, PlayOutcome::Failed);
		$this->assertSame(0, self::days($userID)[date('Y-m-d')][1]);
		$this->play($context, PlayOutcome::SolvedAfterMistake);

		$this->assertCount(1, Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the fail was continued');
		$this->assertSame(1, self::days($userID)[date('Y-m-d')][1]);
	}

	//A continued attempt moves to today; the day it was on keeps what it had
	public function testAContinuedAttemptCountsTodayAndLeavesItsOldDay(): void
	{
		$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
		$context = new ContextPreparator([
			'user' => ['rating' => 1500],
			'tsumego' => ['attempt' => ['solved' => false, 'user_rating' => 1400, 'created' => $threeDaysAgo . ' 10:00:00'], 'set_order' => 1]]);
		$userID = (int) $context->user['id'];
		$this->assertSame([$threeDaysAgo => [1400, 0]], self::days($userID));

		$this->play($context, PlayOutcome::SolvedAfterMistake);

		$this->assertCount(1, Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the attempt was continued');
		$this->assertSame([$threeDaysAgo => [1400, 0], date('Y-m-d') => [self::attemptRating($userID), 1]], self::days($userID));
	}

	//A day keeps its highest rating and its solve, however the attempts of the day go
	public function testADayKeepsItsHighestRatingAndItsSolve(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userID = (int) $context->user['id'];

		UserDay::record($userID, '2026-03-04', 1500.4, true);
		UserDay::record($userID, '2026-03-04', 1450, false);
		$this->assertSame(['2026-03-04' => [1500, 1]], self::days($userID), 'a lower rating and a fail later keep the higher and the solve');
		UserDay::record($userID, '2026-03-04', 1520.6, false);
		$this->assertSame(['2026-03-04' => [1521, 1]], self::days($userID), 'a higher one replaces it, rounded as user_rating is');
	}

	//An attempt without a rating still makes the day one the player played, its rating left to the attempts with one
	public function testAnAttemptWithoutARatingKeepsTheDay(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userID = (int) $context->user['id'];

		UserDay::record($userID, '2026-03-04', null, false);
		$this->assertSame(['2026-03-04' => [null, 0]], self::days($userID));
		UserDay::record($userID, '2026-03-04', 1500, false);
		UserDay::record($userID, '2026-03-04', null, true);
		$this->assertSame(['2026-03-04' => [1500, 1]], self::days($userID), 'a rating fills it, a later one without keeps it');
	}
}
