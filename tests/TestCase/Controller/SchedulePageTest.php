<?php


#[RetryOnBrowserFailure]
class SchedulePageTest extends IntegrationTestCase
{
	//A schedule that starts today publishes straight away, so the button says that instead of the neutral label.
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

	public function testUpcomingShowsTheSandboxSourceAndNumber(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 268, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 673]]]],
			'schedule' => [['tsumego' => 0, 'set' => 'set 1', 'date' => date('Y-m-d', strtotime('+1 day'))]]]);
		$this->login('admin');

		$browser = Browser::instance();
		$browser->get('/schedule');
		$this->assertTextContains('sandbox set', $browser->getTableCell('.data-table', 1, 1)->getText());
		$this->assertTextContains('268', $browser->getTableCell('.data-table', 1, 2)->getText());
	}

	//The preview under the form shows the problems the form would schedule, grouped by the day each publishes.
	public function testPreviewShowsTheProblemsByDay(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [['name' => 'sandbox set', 'num' => 2, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
		]);
		$this->login('admin');

		$browser = Browser::instance();
		$browser->get('/schedule');
		$browser->driver->executeScript(
			'var perDay = document.getElementById("schedule-per-day");'
			. 'perDay.value = "1";'
			. 'perDay.dispatchEvent(new Event("change", {bubbles: true}));');

		$browser->waitUntil(fn() => count($browser->getElements('#schedule-preview .preview-day')) === 2, context: 'two days in the preview');
		$numbers = array_map(fn($day) => $day->getText(), $browser->getElements('#schedule-preview .problem-nav__number'));
		$this->assertSame(['1', '2'], $numbers);
		// the target set has a problem 1 already, which publishing renumbers
		$this->assertCount(1, $browser->getElements('#schedule-preview .problem-nav__number--collision'));
		$browser->assertNoJsErrors();
	}

	private function submitLabel(Browser $browser): string
	{
		return trim($browser->driver->executeScript(
			'return document.getElementById("schedule-submit").textContent'));
	}
}
