<?php

use App\Model\Table\TsumegoStatusTable;
use App\Utility\Constants;

//The play page shows the next problem in place, without loading the page again (webroot/js/PlayNavigation.js)
#[RetryOnBrowserFailure]
class PlayNavigationBrowserTest extends IntegrationTestCase
{
	/** @return list<string> */
	private static function links(ContextPreparator $context): array
	{
		return array_map(fn($tsumego) => '/' . $tsumego['set-connections'][0]['id'], $context->tsumegos);
	}

	//A mark on the page that a load of the page would take away
	private static function markPage(Browser $browser): void
	{
		$browser->driver->executeScript('window.samePage = true;');
	}

	private static function assertSamePage(Browser $browser, string $message): void
	{
		self::assertTrue($browser->driver->executeScript('return window.samePage === true;'), $message);
	}

	private static function waitForProblem(Browser $browser, string $link, int $tsumegoID): void
	{
		// the page's globals as the window has them, its const playPage isn't in reach of the driver's scripts
		$browser->waitUntilJs('location.pathname === "' . $link . '" && window.tsumegoID === ' . $tsumegoID
			. ' && besogo.editor && document.querySelector("#target svg") !== null');
	}

	private static function statusOf(ContextPreparator $context, int $index): ?string
	{
		return TsumegoStatusTable::statusOf($context->user['id'], $context->tsumegos[$index]['id']);
	}

	//Next, a solve, Back and Forward: the problems change without the page loading again, each with its
	//own board, links, title and buttons, the visit and the results are recorded as with a load
	public function testTheProblemsOfACollectionAreShownInPlace(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2, 3]]);
		$links = self::links($context);
		$browser = Browser::instance();
		$browser->get(ltrim($links[0], '/'));
		self::markPage($browser);

		$browser->playWithResult('S');
		$browser->clickId('besogo-next-button');
		self::waitForProblem($browser, $links[1], (int) $context->tsumegos[1]['id']);
		self::assertSamePage($browser, 'Next shows the problem in place');
		$this->assertStringContainsString(' 2/3 ', $browser->driver->getTitle());
		$this->assertSame($links[2], $browser->find('#besogo-next-button')->getAttribute('href'));
		$this->assertSame($links[0], $browser->find('#besogo-back-button')->getAttribute('href'));
		$this->assertSame('2', $browser->find('#currentNavigationButton')->getText());
		$this->assertSame(1, (int) $browser->driver->executeScript('return document.querySelectorAll("#target svg.besogo-board, #target .besogo-board").length > 0 ? 1 : 0;'));
		// the button of the problem solved before shows it solved, as the page loaded after the result would
		$this->assertStringContainsString('problem-nav__item--S', $browser->driver->executeScript('return document.querySelector(".problem-nav__item").className;'));
		$browser->waitUntilJs('true', 1);
		for ($wait = 0; $wait < 30 && self::statusOf($context, 1) === null; $wait++)
			usleep(100000);
		$this->assertSame(TsumegoStatusTable::$VISITED, self::statusOf($context, 1), 'The visit is recorded when the problem is shown');

		// the second problem is solved on its own board
		$browser->playWithResult('S');
		$this->assertSame(TsumegoStatusTable::$SOLVED, self::statusOf($context, 1));
		$this->assertSame(TsumegoStatusTable::$SOLVED, self::statusOf($context, 0));

		$browser->driver->navigate()->back();
		self::waitForProblem($browser, $links[0], (int) $context->tsumegos[0]['id']);
		self::assertSamePage($browser, 'Back shows the problem in place');
		$browser->driver->navigate()->forward();
		self::waitForProblem($browser, $links[1], (int) $context->tsumegos[1]['id']);
		self::assertSamePage($browser, 'Forward shows the problem in place');

		// the arrow key goes on along Next
		$browser->driver->executeScript('document.dispatchEvent(new KeyboardEvent("keydown", {keyCode: 39}));');
		self::waitForProblem($browser, $links[2], (int) $context->tsumegos[2]['id']);
		self::assertSamePage($browser, 'The arrow key shows the problem in place');
		$browser->assertNoJsErrors();
	}

	//A fail costs a heart; the next problem is shown with the hearts the server has after it
	public function testTheNextProblemAfterAFailHasTheHeartsOfTheResult(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2]]);
		$links = self::links($context);
		$browser = Browser::instance();
		$browser->get(ltrim($links[0], '/'));
		self::markPage($browser);
		$before = (int) $browser->driver->executeScript('return currentHealth;');

		$browser->playWithResult('F');
		$browser->clickId('besogo-next-button');
		self::waitForProblem($browser, $links[1], (int) $context->tsumegos[1]['id']);

		self::assertSamePage($browser, 'Next shows the problem in place');
		$this->assertSame($before - 1, (int) $browser->driver->executeScript('return currentHealth;'));
		$this->assertStringStartsWith(($before - 1) . ' / ', $browser->driver->executeScript('return document.getElementById("health-counter").textContent;'));
		$browser->assertNoJsErrors();
	}

	//The board of a problem shown in place takes the place of the old one: the listeners and observers
	//it added to the window are taken away with it, so a long run of problems doesn't pile them up
	public function testTheBoardsShownInPlaceLeaveNoListenersBehind(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2, 3, 4]]);
		$links = self::links($context);
		$browser = Browser::instance();
		$browser->get(ltrim($links[0], '/'));
		// in a script of the page, the driver's own scripts run apart from the page's
		$browser->driver->executeScript('let script = document.createElement("script"); script.textContent = '
			. json_encode('window.resizeListeners = 0;'
				. 'const add = window.addEventListener, remove = window.removeEventListener;'
				. 'window.addEventListener = function(type, ...rest) { if (type === "resize") resizeListeners++; return add.call(this, type, ...rest); };'
				. 'window.removeEventListener = function(type, ...rest) { if (type === "resize") resizeListeners--; return remove.call(this, type, ...rest); };')
			. '; document.head.append(script);');

		foreach ([1, 2, 3] as $index)
		{
			$browser->clickId('besogo-next-button');
			self::waitForProblem($browser, $links[$index], (int) $context->tsumegos[$index]['id']);
			$this->assertSame(0, (int) $browser->driver->executeScript('return resizeListeners;'), 'The board added as many as the old one took away');
		}
		$browser->assertNoJsErrors();
	}

	//The rating mode decides its next problem when it is asked for, so its page loads
	public function testTheRatingModeLoadsItsNextProblem(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE, 'rating' => 1500], 'tsumegos' => [['set_order' => 1, 'rating' => 1500], ['set_order' => 2, 'rating' => 1500]]]);
		$browser = Browser::instance();
		$browser->get('ratingMode');
		$browser->playWithResult('S');
		self::markPage($browser);

		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('window.samePage === undefined && typeof besogo !== "undefined" && besogo.editor !== null');
		$this->assertSame('/ratingMode', parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH));
		$browser->assertNoJsErrors();
	}
}
