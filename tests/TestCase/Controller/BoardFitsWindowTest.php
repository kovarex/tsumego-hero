<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverDimension;

/**
 * The board of a problem and the buttons under it fit in the window, however tall the shown part
 * of the board is (forum#105).
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class BoardFitsWindowTest extends IntegrationTestCase
{
	/** The stones of /15210 (The L Group 7/46): the shown part is taller than wide, but not enough to be turned on its side. */
	private const TALL_SGF = '(;GM[1]FF[4]SZ[19]ST[2]AB[bd][ca][cb][cc][cd]AW[bf][bg][ce][db][dd][de][eb]G[KILL];B[aa]C[+])';

	/** Layout of the page in pixels, as the player sees it scrolled to the top */
	private function layout(Browser $browser): array
	{
		return $browser->driver->executeScript(<<<'JS'
			window.scrollTo(0, 0);
			var board = document.querySelector(".besogo-board").getBoundingClientRect();
			var buttons = document.querySelector(".besogo-tsumegoPlayTool").getBoundingClientRect();
			var target = document.getElementById("target").getBoundingClientRect();
			var viewBox = besogo.boardCanvasSvg.viewBox.baseVal;
			return {
				windowHeight: window.innerHeight,
				boardWidth: board.width,
				boardHeight: board.height,
				boardCenter: board.left + board.width / 2,
				targetCenter: target.left + target.width / 2,
				buttonsBottom: buttons.bottom,
				shapeRatio: viewBox.width / viewBox.height,
			};
			JS);
	}

	private function openProblem(Browser $browser, int $width, int $height): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::TALL_SGF]]);
		$browser->driver->manage()->window()->setSize(new WebDriverDimension($width, $height));
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		$browser->waitUntilJs('document.readyState === "complete"');
	}

	private function assertFitsTheWindow(array $layout, string $message): void
	{
		$this->assertLessThanOrEqual($layout['windowHeight'], $layout['buttonsBottom'], $message . ': the buttons under the board are in the window');
		$this->assertEqualsWithDelta($layout['shapeRatio'], $layout['boardWidth'] / $layout['boardHeight'], 0.02, $message . ': the board keeps its shape');
		$this->assertEqualsWithDelta($layout['targetCenter'], $layout['boardCenter'], 2, $message . ': the board stays in the middle');
	}

	public function testATallBoardFitsTheWindowWithItsButtons(): void
	{
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$this->openProblem($browser, 1400, 900);
			$this->assertLessThan(1, $this->layout($browser)['shapeRatio'], 'The shown part is taller than wide');
			$this->assertFitsTheWindow($this->layout($browser), 'Opened');

			// turned on its side, it is wider than tall and still fits
			$browser->clickId('boardSpinClockwise');
			$browser->waitUntilJs('besogo.boardCanvasSvg.viewBox.baseVal.width > besogo.boardCanvasSvg.viewBox.baseVal.height');
			$this->assertFitsTheWindow($this->layout($browser), 'Rotated');

			$window->setSize(new WebDriverDimension(1400, 700));
			$browser->waitUntilJs('document.querySelector(".besogo-tsumegoPlayTool").getBoundingClientRect().bottom <= window.innerHeight');
			$this->assertFitsTheWindow($this->layout($browser), 'A lower window');
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}

	public function testAWindowHighEnoughLeavesTheBoardAsWideAsBefore(): void
	{
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$this->openProblem($browser, 1400, 2000);
			$layout = $this->layout($browser);
			$targetWidth = $browser->driver->executeScript('return document.getElementById("target").getBoundingClientRect().width;');
			$this->assertEqualsWithDelta($targetWidth / 2, $layout['boardWidth'], 1, 'A regular board takes half of the width');
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
