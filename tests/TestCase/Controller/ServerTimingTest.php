<?php

use App\Utility\PlayOutcome;
use App\Utility\RequestTiming;
use Cake\Core\Configure;

//Every answer of the site says where its time went (Server-Timing), and a slow one is written to the slow log
class ServerTimingTest extends IntegrationTestCase
{
	private string $logPath;

	public function setUp(): void
	{
		parent::setUp();
		$this->logPath = TMP . 'tests' . DS . 'server_timing_' . bin2hex(random_bytes(4)) . '.log';
		Configure::write('SlowLog.path', $this->logPath);
	}

	public function tearDown(): void
	{
		Configure::write('SlowLog.path', LOGS . 'slow.log');
		Configure::write('SlowLog.thresholdMilliseconds', RequestTiming::DEFAULT_THRESHOLD_MILLISECONDS);
		@unlink($this->logPath);
		parent::tearDown();
	}

	//The parts of the header in their order, the queries of the database among them
	private function assertServerTiming(): void
	{
		$header = (string) $this->headerValue('Server-Timing');
		$this->assertMatchesRegularExpression('/^total;dur=[\d.]+, (.*, )?db;dur=[\d.]+;desc="[1-9]\d* queries", (.*, )?php;dur=[\d.]+;desc="The rest of PHP"$/', $header);
	}

	public function testAPageSaysWhereItsTimeWent(): void
	{
		Configure::write('SlowLog.thresholdMilliseconds', 60000);
		new ContextPreparator(['tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]]]]]);
		$this->get('/sets');

		$this->assertResponseOk();
		$this->assertServerTiming();
		$this->assertFileDoesNotExist($this->logPath, 'a fast request is not logged');
	}

	//The result of a problem, an answer in JSON, is timed too, and logged when slow: as its action, with the player's id
	public function testASlowResultIsLogged(): void
	{
		Configure::write('SlowLog.thresholdMilliseconds', 0);
		$context = new ContextPreparator(['user' => ['name' => 'player'], 'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]]]]]);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];

		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_connection_id' => $context->tsumegos[0]['set-connections'][0]['id'],
			'seconds' => 5,
			'outcome' => PlayOutcome::SolvedClean->value]);

		$this->assertResponseOk();
		$this->assertServerTiming();
		$entries = array_map(fn(string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($this->logPath, FILE_IGNORE_NEW_LINES));
		$this->assertCount(1, $entries);
		$this->assertSame('POST', $entries[0]['method']);
		$this->assertSame('Tsumegos/result', $entries[0]['route']);
		$this->assertSame(200, $entries[0]['status']);
		$this->assertSame($context->user['id'], $entries[0]['user_id']);
		$this->assertFalse($entries[0]['session'], 'the site keeps no PHP session, so no request waits for the lock of one');
		$this->assertGreaterThan(0, $entries[0]['db_queries']);
		$this->assertNotEmpty($entries[0]['queries']);
	}

	//A page that isn't there is timed as well, without a route
	public function testAMissingPageIsTimed(): void
	{
		Configure::write('SlowLog.thresholdMilliseconds', 0);
		$this->get('/no-such-page-at-all/really');

		$this->assertResponseCode(404);
		$this->assertStringStartsWith('total;dur=', (string) $this->headerValue('Server-Timing'));
		$entry = json_decode((string) file_get_contents($this->logPath), true, flags: JSON_THROW_ON_ERROR);
		$this->assertSame(404, $entry['status']);
	}
}
