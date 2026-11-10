<?php

use App\Utility\Auth;
use App\Utility\Clock;
use App\Utility\Constants;
use App\Utility\DailyStreak;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//The mistake training turns to a new day together with the daily streak, at DailyStreak::DAY_START_HOUR in
//the player's own time zone, not at midnight of the server, so the reviews the streak counts are of its day.
class TrainingDayTest extends IntegrationTestCase
{
	protected function tearDown(): void
	{
		Clock::freeze(null);
		parent::tearDown();
	}

	//A player in $timeZone with one problem due for review on 2026-11-10, signed in
	private function playerWithReviewOn(string $timeZone, string $dueDay = '2026-11-10'): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['time_zone' => $timeZone],
			'tsumego' => ['rating' => 1500, 'set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => $dueDay . ' 00:00:00', 'mistake_training_rung' => 3]],
		]);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		return $context;
	}

	//The day of the training and of the streak at $utc, and whether the review is due then
	/** @return array{training: string, streak: string, due: bool, dueCount: int} */
	private function dayAt(ContextPreparator $context, string $utc): array
	{
		Clock::freeze(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
		$userId = (int) $context->user['id'];
		return [
			'training' => MistakeTraining::today($userId),
			'streak' => DailyStreak::today($context->user['time_zone'], Clock::now()),
			'due' => MistakeTraining::isDue($userId, (int) $context->tsumegos[0]['id']),
			'dueCount' => MistakeTraining::dueCount($userId),
		];
	}

	public function testInUtcPlusOneTheTrainingKeepsYesterdayUntilThreeLikeTheStreak(): void
	{
		$context = $this->playerWithReviewOn('Europe/Prague');

		// 01:30 in Prague, 00:30 UTC: the server's date has turned, the player's day has not
		$this->assertSame(['training' => '2026-11-09', 'streak' => '2026-11-09', 'due' => false, 'dueCount' => 0], $this->dayAt($context, '2026-11-10 00:30:00'));
		$this->assertSame(['training' => '2026-11-09', 'streak' => '2026-11-09', 'due' => false, 'dueCount' => 0], $this->dayAt($context, '2026-11-10 01:59:00'));
		// 03:01 in Prague: both turn
		$this->assertSame(['training' => '2026-11-10', 'streak' => '2026-11-10', 'due' => true, 'dueCount' => 1], $this->dayAt($context, '2026-11-10 02:01:00'));
	}

	public function testInUtcMinusFiveTheTrainingTurnsAtThreeOfThePlayerToo(): void
	{
		$context = $this->playerWithReviewOn('America/New_York');

		// 20:00 of the 9th in New York, already the 10th in UTC: still the 9th for the player
		$this->assertSame(['training' => '2026-11-09', 'streak' => '2026-11-09', 'due' => false, 'dueCount' => 0], $this->dayAt($context, '2026-11-10 01:00:00'));
		// 01:30 of the 10th in New York: before three, still the 9th
		$this->assertSame(['training' => '2026-11-09', 'streak' => '2026-11-09', 'due' => false, 'dueCount' => 0], $this->dayAt($context, '2026-11-10 06:30:00'));
		// 03:01 in New York
		$this->assertSame(['training' => '2026-11-10', 'streak' => '2026-11-10', 'due' => true, 'dueCount' => 1], $this->dayAt($context, '2026-11-10 08:01:00'));
	}

	//A review at 02:00 in Prague, after midnight of the server, is of the player's day that is still going: the
	//training writes it on that day and the streak counts it for the same one
	public function testAReviewAfterMidnightCountsForTheStreakDayItIsPlayedOn(): void
	{
		$context = $this->playerWithReviewOn('Europe/Prague', '2026-11-09');
		Clock::freeze(new DateTimeImmutable('2026-11-10 01:00:00', new DateTimeZone('UTC')));

		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 1, 'outcome' => PlayOutcome::SolvedClean->value, 'mode' => Constants::$MISTAKE_TRAINING_MODE]);
		$response = json_decode($this->view, true);

		$userId = (int) $context->user['id'];
		$this->assertSame(['2026-11-09'], array_column(Util::query('SELECT day FROM mistake_training_review WHERE user_id = ?', [$userId]), 'day'));
		// read as the database has it: the entity would give the date as an object
		$user = Util::query('SELECT streak_day, streak_day_reviews FROM user WHERE id = ?', [$userId])[0];
		$this->assertSame('2026-11-09', (string) $user['streak_day']);
		$this->assertSame(1, (int) $user['streak_day_reviews']);
		$this->assertSame(1, $response['daily_streak']['reviews']);
	}

	//What the training already stored keeps working: a next review stored for a day at midnight is due on that
	//day of the player, and one made due at a moment of the day is due on the player's day that moment falls on
	public function testStoredReviewsAreDueOnTheirDay(): void
	{
		$context = $this->playerWithReviewOn('UTC');
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$userId = (int) $context->user['id'];
		Util::execute('UPDATE mistake_training_pool SET next_due = ? WHERE user_id = ? AND tsumego_id = ?', ['2026-11-10 14:23:00', $userId, $tsumegoId]);

		Clock::freeze(new DateTimeImmutable('2026-11-10 02:59:00', new DateTimeZone('UTC')));
		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId), 'The 9th is still going');
		Clock::freeze(new DateTimeImmutable('2026-11-10 03:00:00', new DateTimeZone('UTC')));
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'Due all of the 10th, also before 14:23');
	}
}
