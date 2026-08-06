<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use PHPUnitRetry\RetryTrait;

/**
 * Browser tests for AJAX tooltip preview loader (in previewBoard.js).
 *
 * Exercises the full stack: PHP endpoint /api/preview/{id} returns
 * pre-processed SGF board data, and the client-side JS fetches it on hover.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class TooltipPreviewTest extends ControllerTestCase
{
	use RetryTrait;

	private const SGF = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]SZ[19]KM[0.00]AW[cc][cd][dc][ed]AB[cb][db][eb][fc])';

	public function testHoverShowsPreviewOnSolveHistory(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'hoverer'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempt' => ['solved' => true],
			],
		]);

		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$link = $browser->driver->findElement(
			WebDriverBy::cssSelector('a[data-tsumego-id="' . $tsumegoId . '"]')
		);

		$this->assertEmpty($link->findElements(WebDriverBy::tagName('svg')),
			'SVG should not exist before hover');

		$browser->hover($link);

		$wait = new WebDriverWait($browser->driver, 5, 200);
		$wait->until(function () use ($link) {
			return !empty($link->findElements(WebDriverBy::tagName('svg')));
		});

		$this->assertNotEmpty($link->findElements(WebDriverBy::tagName('svg')),
			'SVG preview should appear after hover');
	}

	public function testHoverDoesNotFetchWhenAlreadyCached(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'cacher'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempt' => ['solved' => true],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$link = $browser->driver->findElement(
			WebDriverBy::cssSelector('a[data-tsumego-id="' . $context->tsumegos[0]['id'] . '"]')
		);

		// First hover loads the SVG
		$browser->hover($link);
		$wait = new WebDriverWait($browser->driver, 5, 200);
		$wait->until(function () use ($link) {
			return !empty($link->findElements(WebDriverBy::tagName('svg')));
		});

		// Move away and wait for tooltip to hide
		$browser->hover($browser->driver->findElement(WebDriverBy::tagName('body')));
		$span = $link->findElement(WebDriverBy::cssSelector('span.tooltip-box'));
		$browser->driver->wait(5, 100)->until(function () use ($span) {
			return $span->getCssValue('opacity') == '0';
		});

		// Hover again and wait for tooltip to reappear
		$browser->hover($link);
		$browser->driver->wait(5, 100)->until(function () use ($span) {
			return $span->getCssValue('opacity') == '1';
		});

		// SVG should still be present from DOM cache, no duplicate created
		$this->assertCount(1, $link->findElements(WebDriverBy::tagName('svg')),
			'SVG should still be present from cache, no duplicate');
	}

	public function testDebouncePreventsFetchOnQuickHover(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'fastswiper'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempt' => ['solved' => true],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$link = $browser->driver->findElement(
			WebDriverBy::cssSelector('a[data-tsumego-id="' . $context->tsumegos[0]['id'] . '"]')
		);

		// Hover and immediately leave, before the 200ms debounce fires
		$browser->hover($link);
		$browser->hover($browser->driver->findElement(WebDriverBy::tagName('body')));

		// Poll for SVG appearance; timeout means debounce correctly cancelled the fetch
		$wait = new WebDriverWait($browser->driver, 1, 100);
		$svgAppeared = true;
		try
		{
			$wait->until(function () use ($link) {
				return !empty($link->findElements(WebDriverBy::tagName('svg')));
			});
		}
		catch (\Facebook\WebDriver\Exception\TimeoutException $e)
		{
			$svgAppeared = false;
		}

		$this->assertFalse($svgAppeared,
			'SVG should not appear after quick hover-away (debounce cancelled the fetch)');
	}
}
