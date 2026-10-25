<?php

use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class PreviewZoomTest extends ControllerTestCase
{
	use RetryTrait;

	private const SGF = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]SZ[19]KM[0.00]AW[cc][cd][dc][ed]AB[cb][db][eb][fc])';

	public function testPreviewToggleShowsBoards()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Toggle exists
		$this->assertNotNull($browser->find('.preview-zoom-toggle'));

		// Initially not zoomed
		$zoomed = $browser->driver->executeScript(
			'var el = document.querySelector(".preview-zoom-toggle");'
			. 'var c = el.closest(".set-view-main") || document.documentElement;'
			. 'return c.classList.contains("preview-zoomed")');
		$this->assertFalse($zoomed);

		// Enable previews
		$browser->driver->executeScript(
			'var t = document.querySelector(".preview-zoom-toggle"); t.checked = true;'
			. 't.dispatchEvent(new Event("change", {bubbles: true}));');
		$zoomed = $browser->driver->executeScript(
			'var el = document.querySelector(".preview-zoom-toggle");'
			. 'var c = el.closest(".set-view-main") || document.documentElement;'
			. 'return c.classList.contains("preview-zoomed")');
		$this->assertTrue($zoomed);
	}

	/**
	 * The solve history keeps its toggle inside the table it switches, so turning previews off there
	 * has to leave the page in the numbers mode just like turning them on leaves it in previews.
	 */
	public function testTurningPreviewsOffOnTheSolveHistoryBringsTheNumbersBack(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF,
				'attempts' => [['solved' => true, 'seconds' => 10, 'created' => '2026-08-15 12:00:00']]]]);
		$browser = Browser::instance();
		$address = 'users/solveHistory/' . $context->user['id'];

		$browser->get($address);
		$browser->driver->executeScript('localStorage.setItem("preview-mode", "1");');
		$browser->get($address);

		$this->assertGreaterThan(0, $browser->driver->executeScript(
			'return document.querySelectorAll("a[data-sgf-preview] svg").length'),
			'previews are drawn when the page opens');
		$this->assertTrue($browser->driver->executeScript(
			'return getComputedStyle(document.querySelector(".problem-nav__number")).display === "none"'),
			'the number makes room for the preview');

		$browser->driver->executeScript(
			'var t = document.querySelector(".preview-zoom-toggle"); t.checked = false;'
			. 't.dispatchEvent(new Event("change", {bubbles: true}));');

		$this->assertSame(0, $browser->driver->executeScript(
			'return document.querySelectorAll("a[data-sgf-preview] svg").length'),
			'the previews are gone');
		$this->assertFalse($browser->driver->executeScript(
			'return getComputedStyle(document.querySelector(".problem-nav__number")).display === "none"'),
			'the number is back');
	}
}
