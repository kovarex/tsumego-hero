<?php

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class ImageZoomTest extends IntegrationTestCase
{
	//The set cover is shown at its natural size already, so hovering it must not blow it up
	public function testSetCoverHasNoHoverPreview(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'test set', 'num' => 1]]]]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$this->fetchTable('Set')->updateAll(['image' => 'gokyo-shumo.png'], ['id' => $setId]);

		$browser = Browser::instance();
		$browser->get('sets/view/' . $setId);
		$cover = $browser->find('.set-view__cover img');
		$browser->hover($cover);

		$this->assertSame(0, $browser->driver->executeScript('return document.querySelectorAll(".image-zoom__preview, .zoom-preview").length;'));
		$browser->assertNoJsErrors();
	}

	//The About timeline screenshots are shown small, the hover preview shows them bigger but always inside the window
	public function testAboutScreenshotPreviewFitsTheWindow(): void
	{
		$browser = Browser::instance();
		$browser->get('sites/about');
		$browser->waitUntilJs('document.querySelector(".image-zoom img").complete');
		$browser->hover($browser->find('.image-zoom img'));
		$browser->waitUntilCssSelectorDisplayed('.image-zoom__preview');

		$sizes = $browser->driver->executeScript('
			const rect = document.querySelector(".image-zoom__preview").getBoundingClientRect();
			const thumbnail = document.querySelector(".image-zoom img");
			return {
				left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width,
				windowWidth: window.innerWidth, windowHeight: window.innerHeight,
				thumbnailWidth: thumbnail.getBoundingClientRect().width};');
		$this->assertGreaterThan($sizes['thumbnailWidth'], $sizes['width']);
		$this->assertGreaterThanOrEqual(0, $sizes['left']);
		$this->assertGreaterThanOrEqual(0, $sizes['top']);
		$this->assertLessThanOrEqual($sizes['windowWidth'], $sizes['right']);
		$this->assertLessThanOrEqual($sizes['windowHeight'], $sizes['bottom']);

		$browser->hover($browser->find('.timeline__event__title'));
		$browser->waitUntilCssSelectorDoesntExist('.image-zoom__preview');
		$browser->assertNoJsErrors();
	}
}
