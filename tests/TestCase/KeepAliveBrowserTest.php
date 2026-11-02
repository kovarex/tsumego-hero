<?php

//webroot/js/keepAlive.js keeps the connection to the server open while the player is on a page, so the
//next page does not wait for a new connection.
#[RetryOnBrowserFailure]
class KeepAliveBrowserTest extends AppTestCase
{
	public function testAnOpenPageAsksForTheKeepAliveFileEveryFewSeconds(): void
	{
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->waitUntilJs('typeof KeepAlive === "object" && KeepAlive.isRunning()');

		// The first request goes out after 4 s, the next one 4 s later
		$requests = 'performance.getEntriesByType("resource").filter(entry => entry.name.endsWith("/keepalive.txt")).length';
		$browser->waitUntilJs($requests . ' >= 2', 15);
		$this->assertGreaterThanOrEqual(2, $browser->driver->executeScript('return ' . $requests . ';'));
	}

	public function testThePingStopsWhenThePlayerHasBeenAwayForAWhile(): void
	{
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->waitUntilJs('typeof KeepAlive === "object" && KeepAlive.isRunning()');

		// The last input as if it were 6 minutes ago: the next tick finds the player away and stops
		$browser->driver->executeScript('KeepAlive.pretendLastInputWasMsAgo(6 * 60 * 1000);');
		$browser->waitUntilJs('!KeepAlive.isRunning()', 10);

		// Any input brings it back
		$browser->driver->executeScript('window.dispatchEvent(new Event("keydown"));');
		$this->assertTrue($browser->driver->executeScript('return KeepAlive.isRunning();'));
	}
}
