<?php

use Facebook\WebDriver\WebDriverBy;
use PHPUnitRetry\RetryTrait;

/**
 * Browser tests for inline SGF preview (data-sgf-preview attribute).
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

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$link = $browser->driver->findElement(
			WebDriverBy::cssSelector('a[data-tsumego-id="' . $context->tsumegos[0]['id'] . '"]')
		);

		$this->assertNotEmpty($link->getAttribute('data-sgf-preview'),
			'Button should have inline SGF preview data');

		$this->assertEmpty($link->findElements(WebDriverBy::tagName('svg')),
			'SVG should not exist before hover');

		$browser->hover($link);

		$this->assertNotEmpty($link->findElements(WebDriverBy::tagName('svg')),
			'SVG preview should appear instantly on hover');
	}

	public function testSvgStaysInDomAfterMouseOut(): void
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

		$browser->hover($link);
		$this->assertNotEmpty($link->findElements(WebDriverBy::tagName('svg')));

		$browser->hover($browser->driver->findElement(WebDriverBy::tagName('body')));
		$this->assertCount(1, $link->findElements(WebDriverBy::tagName('svg')),
			'SVG should persist in DOM after mouse out');

		$browser->hover($link);
		$this->assertCount(1, $link->findElements(WebDriverBy::tagName('svg')),
			'No duplicate SVG on second hover');
	}
}
