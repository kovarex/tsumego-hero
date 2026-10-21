<?php

use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class PreviewZoomTest extends ControllerTestCase
{
	use RetryTrait;

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
			'return document.documentElement.classList.contains("preview-zoomed")');
		$this->assertFalse($zoomed);

		// Enable previews
		$browser->driver->executeScript(
			'var t = document.querySelector(".preview-zoom-toggle"); t.checked = true;'
			. 't.dispatchEvent(new Event("change", {bubbles: true}));');
		$zoomed = $browser->driver->executeScript(
			'return document.documentElement.classList.contains("preview-zoomed")');
		$this->assertTrue($zoomed);
	}
}
