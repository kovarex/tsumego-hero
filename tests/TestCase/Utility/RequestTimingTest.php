<?php

use App\Cache\Engine\OpcacheFileEngine;
use App\Cache\Engine\TimedFileEngine;
use App\Utility\RequestTiming;
use App\Utility\Util;
use Cake\Core\Configure;
use Cake\Http\Response;

class RequestTimingTest extends AppTestCase
{
	private string $directory;

	public function setUp(): void
	{
		parent::setUp();
		$this->directory = TMP . 'tests' . DS . 'request_timing_' . bin2hex(random_bytes(4));
		mkdir($this->directory, 0777, true);
		Configure::write('SlowLog.path', $this->directory . DS . 'slow.log');
	}

	public function tearDown(): void
	{
		Configure::write('SlowLog.path', LOGS . 'slow.log');
		Configure::write('SlowLog.thresholdMilliseconds', RequestTiming::DEFAULT_THRESHOLD_MILLISECONDS);
		foreach (glob($this->directory . DS . '{,.}*', GLOB_BRACE) ?: [] as $file)
			if (is_file($file))
				unlink($file);
		rmdir($this->directory);
		parent::tearDown();
	}

	/** @return list<array<string, mixed>> */
	private function loggedEntries(): array
	{
		$path = RequestTiming::logPath();
		if (!is_file($path))
			return [];
		return array_map(fn(string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($path, FILE_IGNORE_NEW_LINES));
	}

	//The queries of the ORM and of Util::query() both count, each one with its time
	public function testTheQueriesOfARequestAreCountedAndTimed(): void
	{
		// the first query of the ORM on a table reads its schema too
		$this->fetchTable('User')->find()->where(['id' => -1])->first();
		RequestTiming::begin();
		Util::query('SELECT 1');
		Util::query('SELECT ? AS value', [2]);
		Util::execute('UPDATE user SET id = id WHERE id = ?', [-1]);
		$this->fetchTable('User')->find()->where(['id' => -1])->first();

		$measured = RequestTiming::measured();
		$this->assertSame(4, $measured['db_queries']);
		$this->assertGreaterThan(0, $measured['db_ms']);
		$this->assertGreaterThanOrEqual($measured['db_ms'], $measured['total_ms']);
		$sqls = array_column(RequestTiming::slowestQueries(), 'sql');
		$this->assertContains('SELECT ? AS value', $sqls);
		$this->assertCount(4, $sqls);
	}

	//The parts add up to the total, the rest of it is PHP
	public function testThePartsAddUpToTheTotal(): void
	{
		RequestTiming::begin();
		Util::query('SELECT SLEEP(0.01)');
		RequestTiming::external(fn() => usleep(5000));
		$measured = RequestTiming::measured();
		$parts = array_sum(array_map(fn(string $field) => (float) ($measured[$field] ?? 0), ['queue_ms', 'boot_ms', 'db_connect_ms', 'db_ms', 'cache_ms', 'external_ms', 'php_ms']));
		$this->assertEqualsWithDelta($measured['total_ms'], $parts, 0.5);
		$this->assertGreaterThanOrEqual(10, $measured['db_ms']);
		$this->assertGreaterThanOrEqual(5, $measured['external_ms']);
		$this->assertSame(1, $measured['external_calls']);
	}

	public function testTheServerTimingHeaderNamesEachPart(): void
	{
		$measured = ['total_ms' => 120.5, 'queue_ms' => 2.5, 'boot_ms' => 10.0, 'db_connect_ms' => 1.0, 'db_ms' => 50.0, 'cache_ms' => 0.5, 'external_ms' => 0.0,
			'php_ms' => 56.5, 'db_queries' => 12, 'cache_operations' => 3, 'cache_misses' => 1, 'external_calls' => 0];
		$this->assertSame('total;dur=120.5, queue;dur=2.5;desc="Waiting for PHP", boot;dur=10;desc="PHP and app start", dbconnect;dur=1;desc="DB connection", '
			. 'db;dur=50;desc="12 queries", cache;dur=0.5;desc="3 operations, 1 misses", php;dur=56.5;desc="The rest of PHP"',
			RequestTiming::serverTiming($measured));

		$measured = ['queue_ms' => null, 'boot_ms' => 0.0, 'db_connect_ms' => 0.0, 'cache_operations' => 0, 'external_ms' => 3.0, 'external_calls' => 1] + $measured;
		$this->assertSame('total;dur=120.5, db;dur=50;desc="12 queries", external;dur=3;desc="1 calls out", php;dur=56.5;desc="The rest of PHP"',
			RequestTiming::serverTiming($measured));
	}

	//The values written into the SQL become ?, so no value of a player is logged and the queries that differ only in them read alike
	public function testTheLoggedSqlHasNoValues(): void
	{
		$this->assertSame('SELECT * FROM user WHERE email = ? AND name = ? AND id = ?',
			RequestTiming::normalizeSql("SELECT *\n\tFROM user WHERE email = 'someone@example.com' AND name = \"it\\\"s\" AND id = 42"));
		$this->assertSame('SELECT a.id FROM `set` t1 JOIN set_connection sc ON sc.set_id = t1.id WHERE sc.num > ? AND t1.id IN (?, …)',
			RequestTiming::normalizeSql('SELECT a.id FROM `set` t1 JOIN set_connection sc ON sc.set_id = t1.id WHERE sc.num > 1.5 AND t1.id IN (1, 2, 3)'));
		$this->assertSame('SELECT `User`.`id` FROM `user` `User` WHERE `id` IN (?, …) LIMIT ?',
			RequestTiming::normalizeSql('SELECT `User`.`id` FROM `user` `User` WHERE `id` IN (:c0,:c1) LIMIT 1'));
		$this->assertSame(RequestTiming::LOGGED_SQL_LENGTH, mb_strlen(RequestTiming::normalizeSql('SELECT ' . str_repeat('a, ', 500) . 'b')));
	}

	//A request over the threshold is one line of JSON in the slow log, its slowest queries with it, without their values
	public function testASlowRequestIsLogged(): void
	{
		Configure::write('SlowLog.thresholdMilliseconds', 0);
		RequestTiming::begin();
		for ($index = 0; $index < 3; $index++)
			Util::query('SELECT ? AS value', [$index]);
		Util::query("SELECT COUNT(*) FROM user WHERE email = 'someone@example.com'");

		$response = RequestTiming::finish(new Response(), 'POST');

		$this->assertStringStartsWith('total;dur=', $response->getHeaderLine('Server-Timing'));
		$entries = $this->loggedEntries();
		$this->assertCount(1, $entries);
		$entry = $entries[0];
		$this->assertSame('POST', $entry['method']);
		$this->assertSame(200, $entry['status']);
		$this->assertSame(0, $entry['user_id']);
		$this->assertSame(4, $entry['db_queries']);
		$this->assertFalse($entry['session']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $entry['time']);
		foreach (['total_ms', 'boot_ms', 'db_ms', 'db_connect_ms', 'cache_ms', 'external_ms', 'php_ms', 'memory_mb'] as $field)
			$this->assertIsNumeric($entry[$field], $field);
		$queries = array_column($entry['queries'], 'count', 'sql');
		$this->assertSame(3, $queries['SELECT ? AS value']);
		$this->assertSame(1, $queries['SELECT COUNT(*) FROM user WHERE email = ?']);
		$this->assertStringNotContainsString('example.com', (string) file_get_contents(RequestTiming::logPath()));
	}

	public function testAFastRequestIsNotLogged(): void
	{
		Configure::write('SlowLog.thresholdMilliseconds', 60000);
		RequestTiming::begin();
		$response = RequestTiming::finish(new Response(), 'GET');
		$this->assertStringContainsString('db;dur=', $response->getHeaderLine('Server-Timing'));
		$this->assertSame([], $this->loggedEntries());
	}

	//A full log moves to slow.log.1, the older ones one further, and the oldest kept is dropped
	public function testTheLogIsRotatedBySize(): void
	{
		$path = RequestTiming::logPath();
		file_put_contents($path, str_repeat('x', RequestTiming::ROTATE_BYTES));
		for ($index = 1; $index <= RequestTiming::KEPT_FILES; $index++)
			file_put_contents($path . '.' . $index, 'file ' . $index);

		RequestTiming::write(['time' => 'now']);

		$this->assertSame('{"time":"now"}' . "\n", file_get_contents($path));
		$this->assertSame(RequestTiming::ROTATE_BYTES, filesize($path . '.1'));
		$this->assertSame('file 1', file_get_contents($path . '.2'));
		$this->assertSame('file ' . (RequestTiming::KEPT_FILES - 1), file_get_contents($path . '.' . RequestTiming::KEPT_FILES));
		$this->assertFileDoesNotExist($path . '.' . (RequestTiming::KEPT_FILES + 1));
		$this->assertCount(RequestTiming::KEPT_FILES + 1, RequestTiming::logFiles());
	}

	public function testWithoutTheLogDirectoryNothingIsWritten(): void
	{
		Configure::write('SlowLog.path', $this->directory . DS . 'missing' . DS . 'slow.log');
		RequestTiming::write(['time' => 'now']);
		$this->assertDirectoryDoesNotExist($this->directory . DS . 'missing');
	}

	public function testCrawlersAndScriptsAreToldFromBrowsers(): void
	{
		$bots = ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2)',
			'Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)', 'curl/8.5.0', 'python-requests/2.31', ''];
		$browsers = ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
			'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
			'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0'];
		foreach ($bots as $userAgent)
			$this->assertTrue(RequestTiming::isBot($userAgent), $userAgent);
		foreach ($browsers as $userAgent)
			$this->assertFalse(RequestTiming::isBot($userAgent), $userAgent);
	}

	//Apache says when it got the request (webroot/.htaccess), the rest until PHP started is the wait for PHP
	public function testTheWaitForPhpIsReadFromTheRequestStart(): void
	{
		$this->assertEqualsWithDelta(0.25, RequestTiming::queueSeconds(1700000000.5, 't=1700000000250000'), 0.0001);
		$this->assertNull(RequestTiming::queueSeconds(1700000000.5, null));
		$this->assertNull(RequestTiming::queueSeconds(1700000000.5, 't=1700000001000000'), 'a start after PHP\'s');
		$this->assertNull(RequestTiming::queueSeconds(1700000000.5, '1700000000250'), 'not what Apache sends');
	}

	//The reads, the writes and the misses of the caches count, those of the file caches and of the opcache one alike
	public function testTheCachesAreTimed(): void
	{
		$path = $this->directory . DS;
		$fileEngine = new TimedFileEngine();
		$fileEngine->init(['path' => $path, 'prefix' => 'timed_', 'duration' => 3600]);
		$opcacheEngine = new OpcacheFileEngine();
		$opcacheEngine->init(['path' => $path, 'prefix' => 'opcache_', 'duration' => 3600]);

		RequestTiming::begin();
		foreach ([$fileEngine, $opcacheEngine] as $engine)
		{
			$this->assertNull($engine->get('value'));
			$engine->set('value', 7);
			$this->assertSame(7, $engine->get('value'));
		}

		$measured = RequestTiming::measured();
		$this->assertSame(6, $measured['cache_operations']);
		$this->assertSame(2, $measured['cache_misses']);
		$this->assertGreaterThan(0, $measured['cache_ms']);
	}
}
