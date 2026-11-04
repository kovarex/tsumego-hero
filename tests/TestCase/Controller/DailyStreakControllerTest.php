<?php

use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\DailyStreak;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//Which solves count for the daily streak (DailyStreak), and the time zone the browser sends
class DailyStreakControllerTest extends IntegrationTestCase
{
	private function solve(ContextPreparator $context, array $extra = []): array
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->post('/tsumegos/result', $extra + ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 1, 'outcome' => PlayOutcome::SolvedClean->value]);
		return json_decode($this->view, true) ?? [];
	}

	private function storedLength(ContextPreparator $context): int
	{
		return (int) Util::query('SELECT streak_length FROM user WHERE id = ?', [$context->user['id']])[0]['streak_length'];
	}

	private static function yesterday(): string
	{
		return new DateTimeImmutable(self::today())->modify('-1 day')->format('Y-m-d');
	}

	private static function today(): string
	{
		return DailyStreak::today(null, new DateTimeImmutable());
	}

	//Today already has $xpLeft less XP than it asks of the player, and asks no reviews
	private static function almostDone(ContextPreparator $context, int $xpLeft = 1): void
	{
		$required = DailyStreak::xpRequired((float) $context->reloadUser()['rating']);
		Util::execute('UPDATE user SET streak_day = ?, streak_day_xp = ?, streak_day_reviews_required = 0 WHERE id = ?', [self::today(), $required - $xpLeft, $context->user['id']]);
	}

	//Today has all the XP it asks, so only the reviews are left
	private static function xpDone(ContextPreparator $context): void
	{
		$required = DailyStreak::xpRequired((float) $context->reloadUser()['rating']);
		Util::execute('UPDATE user SET streak_day = ?, streak_day_xp = ? WHERE id = ?', [self::today(), $required, $context->user['id']]);
	}

	public function testASolveThatCompletesTheXpOfTheDayCountsIt(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 3, 'streak_best' => 3, 'streak_last_day' => self::yesterday()], 'tsumego' => ['set_order' => 1, 'status' => 'V']]);
		self::almostDone($context);
		$response = $this->solve($context);
		$this->assertGreaterThan(0, $response['xp_gained']);
		$this->assertSame(['length' => 4, 'best' => 4, 'freezes' => 0, 'done_today' => true], array_intersect_key($response['daily_streak'], array_flip(['length', 'best', 'freezes', 'done_today'])));
		$this->assertSame(4, $this->storedLength($context));
	}

	//A solve adds its XP to the day, which counts only once it has the XP of three problems of the player's rank
	public function testASolveShortOfTheXpOfTheDayDoesNotCountYet(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 2500], 'tsumego' => ['set_order' => 1, 'status' => 'V', 'rating' => 1000]]);
		$response = $this->solve($context);
		$streak = $response['daily_streak'];
		$this->assertFalse($streak['done_today'], 'One problem is not a day');
		$this->assertGreaterThan(0, (int) $response['xp_gained']);
		$this->assertSame((int) $response['xp_gained'], $streak['xp']);
		$this->assertSame(DailyStreak::xpRequired((float) $context->reloadUser()['rating']), $streak['xp_required'], 'Of the rating after the solve');
		$this->assertSame(0, $streak['reviews_required'], 'Nothing is due in the training');
		$stored = Util::query('SELECT streak_day, streak_day_xp FROM user WHERE id = ?', [$context->user['id']])[0];
		$this->assertSame([self::today(), (int) $response['xp_gained']], [(string) $stored['streak_day'], (int) $stored['streak_day_xp']]);
		$this->assertSame(0, $this->storedLength($context));
	}

	public function testASolveThatGivesNoXpDoesNotCount(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 3, 'streak_best' => 3, 'streak_last_day' => self::yesterday()], 'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		self::almostDone($context);
		$response = $this->solve($context);
		$this->assertSame(0, (int) $response['xp_gained']);
		$this->assertSame(['length' => 3, 'best' => 3, 'freezes' => 0, 'done_today' => false], array_intersect_key($response['daily_streak'], array_flip(['length', 'best', 'freezes', 'done_today'])));
		$this->assertSame(3, $this->storedLength($context));
	}

	public function testAFailDoesNotCount(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'V']]);
		self::almostDone($context);
		$response = $this->solve($context, ['outcome' => PlayOutcome::Failed->value]);
		$this->assertFalse($response['daily_streak']['done_today']);
		$this->assertSame(0, $this->storedLength($context));
	}

	public function testARatingModeSolveCounts(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE], 'tsumego' => ['set_order' => 1, 'status' => 'V']]);
		self::almostDone($context);
		$this->assertTrue($this->solve($context)['daily_streak']['done_today']);
		$this->assertSame(1, $this->storedLength($context));
	}

	//With the XP of the day there, the review that was due completes it
	public function testAReviewInTheMistakeTrainingCounts(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'rating' => 1500,
			'set_order' => 1,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3]]]);
		self::xpDone($context);
		$response = $this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE]);
		$this->assertGreaterThan(0, $response['mistake_training_xp']['review']);
		$this->assertSame([1, 1], [$response['daily_streak']['reviews'], $response['daily_streak']['reviews_required']]);
		$this->assertTrue($response['daily_streak']['done_today']);
		$this->assertSame(1, $this->storedLength($context));
	}

	//A retry after a mistake earns nothing, but the training counts it as solved, so it is a review of the day
	public function testAReviewInTheMistakeTrainingWithoutXpCounts(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'rating' => 1500,
			'set_order' => 1,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3]]]);
		self::xpDone($context);
		$response = $this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE, 'outcome' => PlayOutcome::SolvedAfterMistake->value]);
		$this->assertSame(0, array_sum($response['mistake_training_xp']));
		$this->assertTrue($response['daily_streak']['done_today']);
		$this->assertSame(1, $this->storedLength($context));
	}

	//Played again after today's review solved it, a problem is no review, so it doesn't count
	public function testAReplayInTheMistakeTrainingDoesNotCount(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'rating' => 1500,
			'set_order' => 1,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3]]]);
		self::xpDone($context);
		$this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE]);
		$this->assertSame(1, $this->storedLength($context));
		Util::execute('UPDATE user SET streak_length = 0, streak_best = 0, streak_last_day = NULL WHERE id = ?', [$context->user['id']]);
		$response = $this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE]);
		$this->assertFalse($response['daily_streak']['done_today']);
		$this->assertSame(0, $this->storedLength($context));
	}

	//The time mode gives no XP, so it doesn't keep the streak
	public function testATimeModeSolveDoesNotCount(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED]]]]]);
		self::almostDone($context);
		$this->post('/timeMode/play');
		$response = $this->solve($context);
		$this->assertSame(0, (int) $response['xp_gained']);
		$this->assertFalse($response['daily_streak']['done_today']);
		$this->assertSame(0, $this->storedLength($context));
	}

	//The browser hands its zone over in a cookie, which the next page stores with the other settings
	public function testTheTimeZoneOfTheCookieIsStored(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'traveller']]);
		$this->login('traveller');
		$_COOKIE['time_zone'] = 'Asia/Tokyo';
		$this->get('/sites/impressum');
		$this->assertResponseOk();
		$this->assertSame('Asia/Tokyo', $context->reloadUser()['time_zone']);
		$this->assertStringContainsString('data-time-zone="Asia/Tokyo"', (string) $this->_response->getBody());
	}

	public function testAnInvalidTimeZoneIsIgnored(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'traveller', 'time_zone' => 'Europe/Prague']]);
		$this->login('traveller');
		$_COOKIE['time_zone'] = 'Nowhere/Land';
		$this->get('/sites/impressum');
		$this->assertResponseOk();
		$this->assertSame('Europe/Prague', $context->reloadUser()['time_zone']);
	}
}
