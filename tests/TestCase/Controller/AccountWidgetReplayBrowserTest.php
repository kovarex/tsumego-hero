<?php

use App\Utility\Constants;
use PHPUnitRetry\RetryTrait;

/**
 * The account bar animates a result for a second. A page left sooner (a refresh, Next,
 * the golden tsumego redirect, a lost line left before it is played out) leaves the
 * result to the next page, which plays it as if it had just happened there.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class AccountWidgetReplayBrowserTest extends TestCaseWithAuth
{
	use RetryTrait;

	/**
	 * Black (1,1), white (1,2), black (2,1) is correct. Black (2,1) is lost at once, but the
	 * line goes on: white answers (1,1) and only black (3,1) ends it.
	 */
	/** AccountWidget.unshownResultKey, Firefox doesn't let the test scripts see the class itself */
	private const UNSHOWN_RESULT_KEY = 'accountWidgetUnshownResult';

	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19](;B[aa];W[ab];B[ba]C[+])(;B[ba];W[aa];B[ca]))';

	private function prepare(Browser $browser): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'sgf' => self::SGF]]);
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get($this->playUrl($context));
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor && typeof besogo.editor.getCurrent === "function"');
		return $context;
	}

	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	private function unshownResult(Browser $browser): ?array
	{
		return $browser->driver->executeScript('return JSON.parse(sessionStorage.getItem("' . self::UNSHOWN_RESULT_KEY . '"));');
	}

	/** The animation leaves the transition on the bar, a page that animated nothing has none */
	private function barAnimated(Browser $browser): bool
	{
		return $browser->driver->executeScript('return document.getElementById("xp-bar-fill").style.webkitTransition;') !== '';
	}

	/** The request outlives the page that sent it, so the server may record it a moment after the page is gone */
	private function waitForDamage(ContextPreparator $context, int $damage): void
	{
		$deadline = microtime(true) + 10;
		while ((int) $context->reloadUser()['damage'] !== $damage && microtime(true) < $deadline)
			usleep(100 * 1000);
		$this->assertSame($damage, (int) $context->reloadUser()['damage']);
	}

	public function testRefreshingALostLinePlaysTheFailOnTheNextPage(): void
	{
		foreach (['refresh', 'leave'] as $case)
		{
			$browser = Browser::instance();
			$context = $this->prepare($browser);
			$this->assertFalse($this->barAnimated($browser), $case);

			$browser->clickBoard(2, 1);
			$browser->waitUntilJs('sessionStorage.getItem("' . self::UNSHOWN_RESULT_KEY . '") !== null');
			$unshown = $this->unshownResult($browser);
			$this->assertEqualsWithDelta(1000, $unshown['before']['rating'], 0.001, $case);
			$this->assertLessThan(1000, $unshown['after']['rating'], $case);

			$browser->get($case == 'refresh' ? $this->playUrl($context) : '/sets');

			$this->assertTrue($this->barAnimated($browser), $case . ': the next page plays the fail');
			$this->assertNull($this->unshownResult($browser), $case . ': and only once');
			$this->assertLessThan(1000, (float) $browser->driver->executeScript('return accountWidget.rating;'), $case);
			$this->waitForDamage($context, 1);

			$browser->get($this->playUrl($context));
			$this->assertFalse($this->barAnimated($browser), $case . ': the page after it plays nothing');
		}
	}

	public function testAResultShownInFullIsNotPlayedAgain(): void
	{
		$browser = Browser::instance();
		$context = $this->prepare($browser);

		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(3, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$browser->waitForSubmitResult();
		$this->assertTrue($this->barAnimated($browser));
		$browser->waitUntilJs('sessionStorage.getItem("' . self::UNSHOWN_RESULT_KEY . '") === null');

		$browser->get($this->playUrl($context));
		$this->assertFalse($this->barAnimated($browser));
		$this->assertEqualsWithDelta(
			(float) $context->reloadUser()['rating'],
			(float) $browser->driver->executeScript('return accountWidget.rating;'),
			0.01);
	}

	/** Watches where the replay starts and where it ends, which is over before a test could look at the bar */
	private function replay(Browser $browser, array $unshown): ?array
	{
		return $browser->driver->executeScript(<<<'JS'
			sessionStorage.setItem(arguments[1], JSON.stringify(arguments[0]));
			let animation = null;
			let originalAnimateTo = accountWidget.animateTo.bind(accountWidget);
			accountWidget.animateTo = function (values) {
				animation = {from: accountWidget.values(), to: values};
				return originalAnimateTo(values);
			};
			accountWidget.replayUnshownResult();
			accountWidget.animateTo = originalAnimateTo;
			return animation;
			JS, [$unshown, self::UNSHOWN_RESULT_KEY]);
	}

	public function testTheReplayGoesFromTheRatingBeforeTheResultToTheRecordedOne(): void
	{
		$browser = Browser::instance();
		$this->prepare($browser);
		$now = (float) $browser->driver->executeScript('return Date.now();');
		$page = $browser->driver->executeScript('return accountWidget.values();');
		$this->assertEqualsWithDelta(1000, $page['rating'], 0.001);
		$withRating = fn(float $rating) => array_merge($page, ['rating' => $rating]);

		$animation = $this->replay($browser, [
			'before' => $withRating(1100),
			'after' => $withRating(1090),
			'leftAt' => $now]);
		$this->assertEqualsWithDelta(1100, $animation['from']['rating'], 0.001);
		$this->assertEqualsWithDelta(1000, $animation['to']['rating'], 0.001, 'The page knows the recorded rating');

		// the page was rendered before the server recorded the result, so the bar goes where the result is expected to go
		$animation = $this->replay($browser, [
			'before' => $withRating(1000),
			'after' => $withRating(990),
			'leftAt' => $now]);
		$this->assertEqualsWithDelta(1000, $animation['from']['rating'], 0.001);
		$this->assertEqualsWithDelta(990, $animation['to']['rating'], 0.001);

		$this->assertNull($this->replay($browser, [
			'before' => $withRating(1100),
			'after' => $withRating(1090),
			'leftAt' => $now - 60 * 1000]), 'A result left a minute ago isn\'t news anymore');
		$this->assertNull($this->unshownResult($browser));
	}
}
