<?php

use App\Utility\Constants;
use App\Utility\Util;

class RumControllerTest extends IntegrationTestCase
{
	/** @param array<string, mixed> $body */
	private function sendBeacon(array $body): void
	{
		$this->configRequest(['headers' => ['Content-Type' => 'application/json']]);
		$this->post('/rum/record', (string) json_encode($body));
	}

	/** @return list<array<string, mixed>> */
	private static function samples(): array
	{
		return Util::query('SELECT metric, milliseconds, server_milliseconds, mode FROM rum_sample ORDER BY id');
	}

	public function testTheSamplesOfABeaconAreKept()
	{
		new ContextPreparator();
		$this->sendBeacon(['samples' => [
			['metric' => 'problem_next', 'ms' => 312.4, 'server_ms' => 85.6, 'mode' => Constants::$LEVEL_MODE],
			['metric' => 'result_post', 'ms' => 140, 'server_ms' => null, 'mode' => null],
		]]);

		$this->assertResponseCode(204);
		$this->assertSame([
			['metric' => 'problem_next', 'milliseconds' => 312, 'server_milliseconds' => 86, 'mode' => Constants::$LEVEL_MODE],
			['metric' => 'result_post', 'milliseconds' => 140, 'server_milliseconds' => null, 'mode' => null],
		], self::samples());
	}

	//An unknown metric, a time that is no number, below zero or over the maximum, are left out, the rest is kept
	public function testInvalidSamplesAreLeftOut()
	{
		new ContextPreparator();
		$this->sendBeacon(['samples' => [
			['metric' => 'something', 'ms' => 100],
			['metric' => 'problem_open', 'ms' => 'fast'],
			['metric' => 'problem_open', 'ms' => -1],
			['metric' => 'problem_open', 'ms' => 600001],
			['metric' => 'problem_open', 'ms' => 250, 'server_ms' => -5, 'mode' => 'level'],
			'not a sample',
		]]);

		$this->assertResponseCode(204);
		$this->assertSame([['metric' => 'problem_open', 'milliseconds' => 250, 'server_milliseconds' => null, 'mode' => null]], self::samples());
	}

	public function testABeaconKeepsAtMostItsMaximumOfSamples()
	{
		new ContextPreparator();
		$this->sendBeacon(['samples' => array_fill(0, 30, ['metric' => 'problem_open', 'ms' => 200])]);

		$this->assertResponseCode(204);
		$this->assertCount(20, self::samples());
	}

	//A beacon reads no session: it costs the login of no one, and a setting the page put in a cookie
	//waits for the next page
	public function testABeaconOfASignedInPlayerChangesNothingOfTheirs()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE, 'sound' => 1]]);
		$this->login($context->user['name']);
		$_COOKIE['sound'] = 'off';
		$this->sendBeacon(['samples' => [['metric' => 'problem_open', 'ms' => 200]]]);
		unset($_COOKIE['sound']);

		$this->assertResponseCode(204);
		$this->assertSame(1, (int) $context->reloadUser()['sound']);
		$this->assertCount(1, self::samples());
	}

	public function testABeaconIsAPost()
	{
		new ContextPreparator();
		$this->get('/rum/record');

		$this->assertResponseCode(405);
	}

	//Every answer says what it took
	public function testAPageHasItsServerTiming()
	{
		new ContextPreparator(['tsumego' => 1]);
		$this->get('/sets');

		$this->assertResponseOk();
		$this->assertMatchesRegularExpression('/^sql;dur=[\d.]+;desc="([1-9]\d*) statements", app;dur=[\d.]+$/', (string) $this->headerValue('Server-Timing'));
	}

	//The cron keeps 60 days of samples
	public function testTheCronDropsTheOldSamples()
	{
		new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		Util::execute("INSERT INTO rum_sample (metric, milliseconds, created) VALUES ('problem_open', 100, NOW() - INTERVAL 61 DAY), ('problem_open', 200, NOW() - INTERVAL 59 DAY)");

		$this->get('/cron/daily/' . CRON_SECRET);

		$this->assertResponseOk();
		$this->assertSame([200], array_map(fn($sample) => (int) $sample['milliseconds'], self::samples()));
	}
}
