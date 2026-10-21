<?php

use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SchedulePageTest extends TestCaseWithAuth
{
	use RetryTrait;

	/**
	 * A schedule that starts today publishes straight away, so the button says that
	 * instead of the neutral label.
	 */
	public function testSubmitButtonSaysWhenItPublishesRightAway(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
		]);
		$this->login('admin');

		$browser = Browser::instance();
		$browser->get('/schedule');

		$this->assertSame('Schedule and publish now', $this->submitLabel($browser));

		$browser->driver->executeScript(
			'var d = document.getElementById("schedule-date");'
			. 'd.value = "2099-01-01";'
			. 'd.dispatchEvent(new Event("change", {bubbles: true}));');

		$this->assertSame('Schedule', $this->submitLabel($browser));
	}

	private function submitLabel(Browser $browser): string
	{
		return trim($browser->driver->executeScript(
			'return document.getElementById("schedule-submit").textContent'));
	}
}
