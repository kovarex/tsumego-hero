<?php


#[RetryOnBrowserFailure]
class BesogoTest extends IntegrationTestCase
{
	public function testBesogoTests()
	{
		$browser = Browser::instance();
		$browser->get("/besogo/tests.html");

		// The runner fills its table while the page parses and raises the flag when it is done,
		// so the counts below can never come from a half finished run.
		$browser->waitUntilJs('window.besogoTestsFinished === true');

		// Counted from the table rows: the page source also carries the runner's own script text,
		// which mentions both words on its own.
		$counts = $browser->driver->executeScript('
			var rows = document.querySelectorAll("table tr");
			var passed = 0, failed = 0;
			rows.forEach(function (row) {
				if (row.textContent.indexOf("passed") !== -1) passed++;
				if (row.textContent.indexOf("failed") !== -1) failed++;
			});
			return [window.besogoTestCount, rows.length, passed, failed];
		');

		$this->assertSame($counts[0], $counts[1], 'every besogo test got a row');
		$this->assertSame($counts[0], $counts[2], 'every besogo test passes');
		$this->assertSame(0, $counts[3], 'no besogo test fails');
	}
}
