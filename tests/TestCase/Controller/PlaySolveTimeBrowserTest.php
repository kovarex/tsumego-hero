<?php

use App\Utility\Constants;

/**
 * The time of a problem is the time of all the tries until it is solved. Each result sends only the
 * time since the previous one, so a reset on the page doesn't count the earlier tries again (forum#149).
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class PlaySolveTimeBrowserTest extends IntegrationTestCase
{
	/** Black (1,1), white (1,2), black (2,1) is correct, black (2,1) at once is wrong. */
	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19](;B[aa];W[ab];B[ba]C[+])(;B[ba];W[aa];B[ca]))';

	private function sentSeconds(Browser $browser): array
	{
		return $browser->driver->executeScript('return window.__sentSeconds;');
	}

	public function testAResetOnThePageDoesNotCountTheFirstTryAgain(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		$browser->driver->executeScript(<<<'JS'
			window.__sentSeconds = [];
			var originalFetch = window.fetch;
			window.fetch = function (url, init) {
				if (String(url).indexOf('/tsumegos/result') !== -1)
					window.__sentSeconds.push(JSON.parse(init.body).seconds);
				return originalFetch.apply(window, arguments);
			};
			// the first try takes a long time
			window.clockStart = performance.now() - 100000;
			JS);

		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.__sentSeconds.length == 1');
		$browser->waitForSubmitResult();
		$browser->clickId('besogo-reset-button');
		$browser->waitForSubmitResult();

		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber == 2');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.__sentSeconds.length == 2');
		$browser->waitForSubmitResult();

		[$firstTry, $secondTry] = $this->sentSeconds($browser);
		$this->assertGreaterThanOrEqual(100, $firstTry, 'The failed try sends its time');
		$this->assertLessThan(50, $secondTry, 'The solve sends only the time since the failed try');

		$attempt = ClassRegistry::init('TsumegoAttempt')->find('first', ['conditions' => ['user_id' => $context->user['id']]])['TsumegoAttempt'];
		$this->assertTrue((bool) $attempt['solved']);
		$this->assertEqualsWithDelta($firstTry + $secondTry, (int) $attempt['seconds'], 1, 'The problem took the time of both tries, once');
	}
}
