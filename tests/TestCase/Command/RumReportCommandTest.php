<?php

use App\Command\RumReportCommand;
use App\Utility\Util;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

class RumReportCommandTest extends AppTestCase
{
	use ConsoleIntegrationTestTrait;

	public function testTheReportHasThePercentilesOfEachMetric(): void
	{
		new ContextPreparator();
		foreach (range(1, 20) as $milliseconds)
			Util::execute("INSERT INTO rum_sample (metric, milliseconds, server_milliseconds) VALUES ('problem_next', ?, 5)", [$milliseconds * 10]);
		Util::execute("INSERT INTO rum_sample (metric, milliseconds) VALUES ('result_post', 90)");
		// older than the report goes back
		Util::execute("INSERT INTO rum_sample (metric, milliseconds, created) VALUES ('problem_open', 100, NOW() - INTERVAL 10 DAY)");

		$this->exec('rum_report --days=7');

		$this->assertExitSuccess();
		$this->assertOutputRegExp('/problem_next\s+20\s+100\s+150\s+190\s+5$/m');
		$this->assertOutputRegExp('/result_post\s+1\s+90\s+90\s+90\s+-$/m');
		$this->assertOutputNotContains('problem_open');
	}

	public function testThePercentileIsOfTheNearestRank(): void
	{
		$this->assertSame(2, RumReportCommand::percentile([3, 1, 2, 4], 50));
		$this->assertSame(4, RumReportCommand::percentile([3, 1, 2, 4], 95));
		$this->assertSame(7, RumReportCommand::percentile([7], 50));
	}
}
