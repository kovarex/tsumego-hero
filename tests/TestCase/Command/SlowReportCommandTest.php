<?php

use App\Utility\SlowLogReport;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

//bin/cake slow_report: what the slow log (RequestTiming) says about the last days
class SlowReportCommandTest extends AppTestCase
{
	use ConsoleIntegrationTestTrait;

	private string $logPath;

	public function setUp(): void
	{
		parent::setUp();
		$this->logPath = TMP . 'tests' . DS . 'slow_report_' . bin2hex(random_bytes(4)) . '.log';
	}

	public function tearDown(): void
	{
		foreach ([$this->logPath, $this->logPath . '.1'] as $file)
			@unlink($file);
		parent::tearDown();
	}

	//A line of the slow log
	/** @param array<string, mixed> $fields */
	private static function entry(string $time, string $route, float $total, array $fields = []): string
	{
		return json_encode($fields + [
			'time' => $time, 'method' => 'GET', 'route' => $route, 'status' => 200, 'user_id' => 1,
			'bot' => false, 'total_ms' => $total, 'queue_ms' => null, 'boot_ms' => 0.1 * $total, 'db_connect_ms' => 0, 'db_ms' => 0.6 * $total,
			'cache_ms' => 0, 'external_ms' => 0, 'php_ms' => 0.3 * $total, 'db_queries' => 10, 'cache_operations' => 0,
			'cache_misses' => 0, 'external_calls' => 0, 'session' => false, 'memory_mb' => 8,
			'queries' => [['ms' => 0.5 * $total, 'count' => 2, 'sql' => 'SELECT * FROM tsumego_status WHERE user_id = ?']],
		]) . "\n";
	}

	public function testTheReportSumsUpTheSlowRoutes(): void
	{
		$day = gmdate('Y-m-d', strtotime('-1 day'));
		$lines = '';
		foreach ([1000, 1100, 1200, 1300, 5000] as $index => $total)
			$lines .= self::entry($day . 'T10:15:0' . $index . 'Z', 'Sets/view', $total, ['user_id' => $index + 1]);
		$lines .= self::entry($day . 'T00:00:30Z', 'Tsumegos/result', 2000, ['method' => 'POST', 'external_ms' => 1000, 'php_ms' => 0, 'db_ms' => 800, 'bot' => true]);
		$lines .= "not a line of the log\n";
		file_put_contents($this->logPath, $lines);
		// the rotated file is read too, but an entry older than the days asked for is not
		file_put_contents($this->logPath . '.1', self::entry(gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days')), 'Users/view', 9000));

		$this->exec('slow_report --file ' . $this->logPath);

		$this->assertExitSuccess();
		$this->assertOutputContains('6 slow requests');
		$this->assertOutputContains('1 lines of the log could not be read');
		$this->assertOutputRegExp('/GET Sets\/view +5 +1200 +5000 +5000 +0% +10% +0% +60% +0% +0% +30%/');
		$this->assertOutputRegExp('/POST Tsumegos\/result +1 +2000 +2000 +2000 +0% +10% +0% +40% +0% +50% +0%/');
		$this->assertOutputNotContains('Users/view');
		$this->assertOutputRegExp('/5800 +12 +6  SELECT \* FROM tsumego_status WHERE user_id = \?/');
		$this->assertOutputRegExp('/  10 +5 #{40}/');
		$this->assertOutputRegExp('/' . $day . ' 10:15 UTC +5 requests, +5 players, +1 routes/');
		$this->assertOutputContains('From crawlers and scripts: 1. With a PHP session started: 0');

		$this->exec('slow_report --all --file ' . $this->logPath);
		$this->assertOutputContains('7 slow requests');
		$this->assertOutputContains('GET Users/view');
	}

	public function testWithoutSlowRequestsTheReportSaysSo(): void
	{
		$this->exec('slow_report --file ' . $this->logPath);

		$this->assertExitSuccess();
		$this->assertOutputContains('No slow requests since');
		$this->assertOutputContains('no log file at');
	}

	public function testThePercentilesAreOfTheNearestRank(): void
	{
		$this->assertSame(0.0, SlowLogReport::percentile([], 0.5));
		$this->assertSame(3.0, SlowLogReport::percentile([1.0, 2.0, 3.0, 4.0, 5.0], 0.5));
		$this->assertSame(5.0, SlowLogReport::percentile([1.0, 2.0, 3.0, 4.0, 5.0], 0.95));
		$this->assertSame(2.0, SlowLogReport::percentile([1.0, 2.0, 3.0, 4.0], 0.5));
	}
}
