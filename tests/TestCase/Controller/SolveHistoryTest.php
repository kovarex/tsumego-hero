<?php

use Facebook\WebDriver\WebDriverBy;
use PHPUnitRetry\RetryTrait;

/**
 * Tests for Users/solveHistory page.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SolveHistoryTest extends ControllerTestCase
{
	use RetryTrait;

	private const SGF = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]SZ[19]KM[0.00]AW[cc][cd][dc][ed]AB[cb][db][eb][fc])';

	public function testSolveHistoryShowsAttemptedTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'solver'],
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

		$this->assertNotEmpty($link->getText(), 'Tsumego button should have text');
	}

	public function testSolveHistoryShowsTableHeader(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempt' => ['solved' => true],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$headers = $browser->driver->findElements(WebDriverBy::cssSelector('table thead td'));
		$this->assertNotEmpty($headers, 'Table should have header cells');

		$headerTexts = array_map(fn($el) => $el->getText(), $headers);
		$this->assertContains('Set', $headerTexts);
		$this->assertContains('Solved', $headerTexts);
	}

	public function testSolveHistoryShowsMultipleAttempts(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'multi'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempt' => ['solved' => true],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'sgf' => self::SGF,
					'attempt' => ['solved' => false, 'misplays' => 2],
				],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$links = $browser->driver->findElements(WebDriverBy::cssSelector('a[data-tsumego-id]'));
		$this->assertGreaterThanOrEqual(2, count($links), 'Should show at least 2 tsumego links');
	}
}
