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
		return new DateTimeImmutable(DailyStreak::today(null, new DateTimeImmutable()))->modify('-1 day')->format('Y-m-d');
	}

	public function testASolveThatGivesXpCountsTheDay(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 3, 'streak_best' => 3, 'streak_last_day' => self::yesterday()], 'tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$response = $this->solve($context);
		$this->assertGreaterThan(0, $response['xp_gained']);
		$this->assertSame(['length' => 4, 'best' => 4, 'freezes' => 0, 'done_today' => true], $response['daily_streak']);
		$this->assertSame(4, $this->storedLength($context));
	}

	public function testASolveThatGivesNoXpDoesNotCount(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 3, 'streak_best' => 3, 'streak_last_day' => self::yesterday()], 'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$response = $this->solve($context);
		$this->assertSame(0, (int) $response['xp_gained']);
		$this->assertSame(['length' => 3, 'best' => 3, 'freezes' => 0, 'done_today' => false], $response['daily_streak']);
		$this->assertSame(3, $this->storedLength($context));
	}

	public function testAFailDoesNotCount(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$response = $this->solve($context, ['outcome' => PlayOutcome::Failed->value]);
		$this->assertFalse($response['daily_streak']['done_today']);
		$this->assertSame(0, $this->storedLength($context));
	}

	public function testARatingModeSolveCounts(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE], 'tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$this->assertTrue($this->solve($context)['daily_streak']['done_today']);
		$this->assertSame(1, $this->storedLength($context));
	}

	public function testAReviewInTheMistakeTrainingCounts(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'rating' => 1500,
			'set_order' => 1,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3]]]);
		$response = $this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE]);
		$this->assertGreaterThan(0, $response['mistake_training_xp']['review']);
		$this->assertTrue($response['daily_streak']['done_today']);
		$this->assertSame(1, $this->storedLength($context));
	}

	//A retry after a mistake earns nothing, but the training counts it as solved, so it keeps the streak
	public function testAReviewInTheMistakeTrainingWithoutXpCounts(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'rating' => 1500,
			'set_order' => 1,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3]]]);
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
		$this->solve($context, ['mode' => Constants::$MISTAKE_TRAINING_MODE]);
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
