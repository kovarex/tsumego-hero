<?php

//The label of a small button and the number in a bar of a chart sit in the middle of their height, within a pixel,
//in the light and the dark theme: the buttons on another player's profile (Send message, Challenge, Add friend) and
//the numbers in the bars of the profile's level mode chart.
#[RetryOnBrowserFailure]
class TextCentredBrowserTest extends IntegrationTestCase
{
	//How far the middle of each text sits from the middle of its box, in pixels: for a button its line in its content
	//box, for a bar the number (trimmed to the height of its digits) in the bar
	private const string MEASURE = <<<'JS'
		const middle = r => (r.top + r.bottom) / 2;
		if (arguments[0] === 'button')
			return [...document.querySelectorAll('.profile-header .btn, .profile-header__message')].filter(b => b.checkVisibility() && b.innerText.trim()).map(button => {
				const style = getComputedStyle(button), box = button.getBoundingClientRect(), range = document.createRange();
				range.selectNodeContents(button);
				const top = box.top + parseFloat(style.borderTopWidth) + parseFloat(style.paddingTop);
				const bottom = box.bottom - parseFloat(style.borderBottomWidth) - parseFloat(style.paddingBottom);
				return {text: button.innerText.trim(), off: middle(range.getBoundingClientRect()) - (top + bottom) / 2};
			});
		return [...document.querySelectorAll('.bar-chart__segment')].map(segment => {
			const range = document.createRange();
			range.selectNodeContents(segment);
			const value = segment.querySelector('.bar-chart__value')?.getBoundingClientRect() ?? range.getBoundingClientRect();
			return {text: segment.innerText.trim(), off: middle(value) - middle(segment.getBoundingClientRect())};
		});
		JS;

	public static function themes(): array
	{
		return ['light' => ['light'], 'dark' => ['dark']];
	}

	#[PHPUnit\Framework\Attributes\DataProvider('themes')]
	public function testButtonLabelsAndBarNumbersSitInTheMiddle(string $theme): void
	{
		$today = date('Y-m-d');
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => [
				['solved' => true, 'user_rating' => 1500, 'created' => $today . ' 10:00:00'],
				['solved' => false, 'user_rating' => 1500, 'created' => $today . ' 11:00:00']]]]]);
		$browser = Browser::instance();
		$failures = [];
		foreach (['button' => '/users/view/' . ContextPreparator::getUserIdFromName('bob'), 'bar' => '/users/view/' . $context->user['id']] as $kind => $url)
		{
			$browser->get($url);
			$browser->driver->executeScript('document.documentElement.dataset.theme = arguments[0];', [$theme]);
			$browser->find($kind === 'button' ? '.profile-header .btn' : '.bar-chart__segment');
			$results = $browser->driver->executeScript(self::MEASURE, [$kind]);
			$this->assertNotEmpty($results, $kind . 's on the profile');
			foreach ($results as $result)
				if (abs($result['off']) > 1)
					$failures[] = $kind . ' "' . $result['text'] . '" ' . round($result['off'], 1) . 'px from the middle';
		}
		$this->assertSame([], $failures, 'Texts off the middle of their box in the ' . $theme . ' theme');
	}
}
