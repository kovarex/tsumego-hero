<?php

use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\ActivePlayers;
use App\Utility\SiteGrowth;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Group;

//The charts of the site, drawn by app/charts: the growth of the site on the home page, the days of a player on the
//profile and the rating of a problem in its history.
#[Group('browser')]
#[RetryOnBrowserFailure]
class ChartsBrowserTest extends IntegrationTestCase
{
	public function testHomePageDrawsTheProblemsAndUsersAndTheLegendHidesALine(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'public set', 'num' => 1]]]]]);
		// the cache is a file, it would carry the graph of the previous test over
		SiteGrowth::forget();

		$browser = Browser::instance();
		$browser->get('/');
		// the chart opens on the growth unless the visitor picked the active players before
		$browser->driver->executeScript("localStorage.removeItem('siteGrowthView')");
		$browser->get('/');
		$browser->waitUntil(fn() => count($browser->getElements('.chart-box .line-chart__series')) === 2, 10, 'both lines drawn');
		$this->assertSame(['Problems', 'Users'], array_map(
			fn($series) => $series->getAttribute('data-label'),
			$browser->getElements('.chart-box .line-chart__series')));

		$browser->hover($browser->find('.chart-box .line-chart__plot > svg'));
		$browser->waitUntilCssSelectorDisplayed('.chart-box .chart__tooltip');
		$this->assertSame(date('M Y') . "\nProblems: 1\nUsers: 1", $browser->find('.chart-box .chart__tooltip')->getText());

		$browser->driver->findElement(WebDriverBy::xpath("//button[contains(@class, 'chart__legend-item') and normalize-space() = 'Users']"))->click();
		$browser->waitUntil(fn() => count($browser->getElements('.chart-box .line-chart__series')) === 1, 10, 'the users line hidden');
		$this->assertSame('Problems', $browser->find('.chart-box .line-chart__series')->getAttribute('data-label'));
		$browser->assertNoJsErrors();
	}

	//The active players are behind the toggle of the Site Growth chart: it opens on the growth, the toggle switches to
	//the players who solved a problem each day, 6M first with today as the last point "so far", the ranges and the
	//toggle switch without a request, and the choice is kept for the next visit; the right column has no chart
	public function testHomePageTogglesTheSiteGrowthToTheActivePlayers(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$solve = fn(int $daysAgo) => ['solved' => true, 'created' => $day($daysAgo) . ' 10:00:00'];
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'public set', 'num' => 1]], 'attempts' => [$solve(300), $solve(20), $solve(5), $solve(0)]]]]);
		SiteGrowth::forget();
		ActivePlayers::forget();

		$browser = Browser::instance();
		$browser->get('/');
		$browser->driver->executeScript("localStorage.removeItem('siteGrowthView')");
		$browser->get('/');
		$state = function () use ($browser) {
			$state = $browser->driver->executeScript(<<<'JS'
			const chart = document.querySelector('.chart-box');
			const buttons = [...chart.querySelectorAll('.range-chart__range')];
			return {
				buttons: buttons.map(button => button.textContent),
				pressed: buttons.filter(button => button.getAttribute('aria-pressed') === 'true').map(button => button.textContent),
				series: [...chart.querySelectorAll('.line-chart__series')].map(series => series.getAttribute('data-label')),
				points: chart.querySelector('.line-chart__line')?.getAttribute('d').split('L').length ?? 0,
				requests: performance.getEntriesByType('resource').filter(entry => ['fetch', 'xmlhttprequest'].includes(entry.initiatorType)).length,
				rightColumnCharts: document.querySelectorAll('.split > :nth-child(2) [data-range-chart-root], .split > :nth-child(2) .line-chart__plot').length};
			JS);
			ksort($state);
			return $state;
		};
		$click = fn(string $text) => $browser->driver->executeScript("[...document.querySelectorAll('.chart-box .range-chart__range')].find(button => button.textContent === arguments[0]).click()", [$text]);
		$browser->waitUntil(fn() => count($state()['series']) === 2, 10, 'the growth drawn');
		$opened = $state();
		$this->assertSame([['Growth', 'Active players'], ['Growth'], ['Problems', 'Users'], 0],
			[$opened['buttons'], $opened['pressed'], $opened['series'], $opened['rightColumnCharts']], 'it opens on the growth, the right column has no chart');

		$click('Active players');
		$browser->waitUntil(fn() => $state()['series'] === ['Players'], 10, 'the active players');
		// a point a day from 300 days ago is more than half a year, so 6M starts with the range itself
		$sixMonths = (int) round((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime('-6 months')))) / 86400) + 1;
		$active = $state();
		$this->assertSame([['Growth', 'Active players', '1M', '6M', '1Y', 'All'], ['Active players', '6M'], $sixMonths],
			[$active['buttons'], $active['pressed'], $active['points']]);

		// today, the last point, is "so far"
		$browser->driver->executeScript(<<<'JS'
			const svg = document.querySelector('.chart-box svg');
			const box = svg.getBoundingClientRect();
			svg.dispatchEvent(new MouseEvent('mousemove', {bubbles: true, clientX: box.right - 2, clientY: box.top + box.height / 2}));
			JS);
		$browser->waitUntilCssSelectorDisplayed('.chart-box .chart__tooltip');
		$this->assertSame("Today so far\nPlayers: 1", $browser->find('.chart-box .chart__tooltip')->getText());
		// one axis: its numbers in the chart's text colour like the dates, not the blue of the line
		$colors = $browser->driver->executeScript("return [...document.querySelectorAll('.chart-box .line-chart__tick')].map(tick => getComputedStyle(tick).fill)");
		$this->assertCount(1, array_unique($colors), implode(', ', $colors));

		$click('1Y');
		$browser->waitUntil(fn() => $state()['pressed'] === ['Active players', '1Y'], 10, 'the range 1Y');
		$this->assertGreaterThan(50, $state()['points'], 'a point a week of the year');
		$this->assertLessThan(56, $state()['points']);

		$click('Growth');
		$browser->waitUntil(fn() => $state()['series'] === ['Problems', 'Users'], 10, 'back to the growth');
		$this->assertSame($opened['requests'], $state()['requests'], 'no request for any of it');

		// the choice is kept: the next visit opens on the view picked last
		$click('Active players');
		$browser->get('/');
		$browser->waitUntil(fn() => $state()['series'] === ['Players'], 10, 'opens on the active players');
		$browser->driver->executeScript("localStorage.removeItem('siteGrowthView')");
		$browser->assertNoJsErrors();
	}

	public function testProfileDrawsTheSolvesFailsRatingAndTimeModeRunsOfThePlayer(): void
	{
		// the first two days of the current quarter, the page the level chart opens on, whatever the day of the run
		$first = date('Y') . '-' . sprintf('%02d', intdiv((int) date('n') - 1, 3) * 3 + 1) . '-01';
		$second = date('Y-m-d', strtotime($first . ' +1 day'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [[
				'sets' => [['name' => 'set', 'num' => 1]],
				'attempts' => [
					['solved' => true, 'user_rating' => 1500, 'created' => $first . ' 10:00:00'],
					['solved' => true, 'user_rating' => 1510, 'created' => $first . ' 11:00:00'],
					['solved' => false, 'user_rating' => 1490, 'created' => $first . ' 12:00:00'],
					['solved' => false, 'user_rating' => 1480, 'created' => $second . ' 10:00:00']]]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				['category' => TimeModeCategoryTable::BLITZ, 'status' => TimeModeSessionStatusTable::SOLVED, 'rank' => '5k'],
				['category' => TimeModeCategoryTable::BLITZ, 'status' => TimeModeSessionStatusTable::FAILED, 'rank' => '5k']]]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);

		// all three at once: the level mode on the left, the newest day first
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-level .bar-chart__bar');
		$texts = fn(string $selector) => array_map(fn($element) => $element->getText(), $browser->getElements('#profile-chart-level ' . $selector));
		$this->assertSame([$second, $first], $texts('.bar-chart__category'));
		$this->assertSame(['1', '3'], $texts('.bar-chart__total'));
		$this->assertSame(['2'], $texts("[data-label='Solves']"));
		$this->assertSame(['1', '1'], $texts("[data-label='Fails']"));

		// the rating on the right, the time mode runs under it
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-rating .line-chart__line');
		$this->assertSame('Overall rating', $browser->find('#profile-chart-rating .chart__title')->getText());
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-time .bar-chart__bar');
		$this->assertSame('2', $browser->find('#profile-chart-time .bar-chart__total')->getText());

		$layout = fn() => $browser->driver->executeScript(<<<'JS'
			const box = id => { const rect = document.getElementById(id).getBoundingClientRect(); return {left: Math.round(rect.left), top: Math.round(rect.top), width: Math.round(rect.width)}; };
			const svgWidths = [...document.querySelectorAll('.profile-charts svg[role="img"]')].map(svg => Math.round(svg.getBoundingClientRect().width));
			return {level: box('profile-chart-level'), rating: box('profile-chart-rating'), time: box('profile-chart-time'), svgWidths: svgWidths, toggles: document.querySelectorAll('[data-profile-show]').length};
			JS);
		$pc = $layout();
		$this->assertSame(0, $pc['toggles']);
		$this->assertLessThan($pc['rating']['left'], $pc['level']['left'], 'the level on the left');
		$this->assertSame($pc['rating']['left'], $pc['time']['left'], 'the time mode runs in the column of the rating');
		$this->assertGreaterThan($pc['rating']['top'], $pc['time']['top'], 'under it');
		$this->assertSame($pc['level']['width'], $pc['rating']['width'], 'two columns of the same width');
		$this->assertCount(1, $pc['svgWidths'], 'the rating line');
		$this->assertGreaterThan($pc['rating']['width'] * 0.8, $pc['svgWidths'][0], 'drawn at the width of its column');

		// a phone stacks them: the level, the rating, the time mode runs
		$window = $browser->driver->manage()->window();
		$size = $window->getSize();
		$window->setSize(new Facebook\WebDriver\WebDriverDimension(390, 900));
		try
		{
			$browser->get('users/view/' . $context->user['id']);
			$browser->waitUntilCssSelectorDisplayed('#profile-chart-time .bar-chart__bar');
			$phone = $layout();
			$this->assertSame($phone['level']['left'], $phone['rating']['left']);
			$this->assertLessThan($phone['rating']['top'], $phone['level']['top']);
			$this->assertLessThan($phone['time']['top'], $phone['rating']['top']);
			$this->assertGreaterThan($phone['rating']['width'] * 0.8, $phone['svgWidths'][0]);
		}
		finally
		{
			$window->setSize($size);
		}
		$browser->assertNoJsErrors();
	}

	//The ranges above the rating chart, all printed with the page: 6M first, a click switches, a point a week for a year and a
	//point a month for all of five years; the value axis is in ranks
	public function testProfileRatingChartSwitchesRanges(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => array_map(fn($rating) => [
				'solved' => true,
				'user_rating' => $rating[1],
				'created' => $day($rating[0]) . ' 10:00:00'], [[1800, 1200], [300, 1300], [100, 1400], [10, 1500], [3, 1490]])]]]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		// WebDriver hands the object back with its keys in no set order, so they are sorted
		$state = function () use ($browser) {
			$state = $browser->driver->executeScript(<<<'JS'
			const chart = document.getElementById('profile-chart-rating');
			const line = chart.querySelector('.line-chart__line');
			return {
				ranges: [...chart.querySelectorAll('.range-chart__range')].map(button => button.textContent),
				active: chart.querySelector('.range-chart__range[aria-pressed="true"]')?.textContent ?? null,
				points: line ? line.getAttribute('d').split('L').length : 0,
				ranks: [...chart.querySelectorAll(".line-chart__tick[text-anchor='end']")].map(tick => tick.textContent)};
			JS);
			ksort($state);
			return $state;
		};
		$pick = function (string $range) use ($browser, $state) {
			$browser->driver->executeScript("[...document.querySelectorAll('#profile-chart-rating .range-chart__range')].find(button => button.textContent === arguments[0]).click()", [$range]);
			$browser->waitUntil(fn() => $state()['active'] === $range, 10, 'the range ' . $range);
			return $state()['points'];
		};
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-rating .line-chart__line');
		// the axis names the ranks at their lower edges around the ratings of half a year, 1400 to 1500
		$this->assertSame(['active' => '6M', 'points' => 3, 'ranges' => ['1M', '6M', '1Y', 'All'], 'ranks' => ['7k', '6k', '5k']], $state());

		$this->assertSame(4, $pick('1Y'), 'a point a week');
		$months = count(array_unique(array_map(fn($daysAgo) => substr($day($daysAgo), 0, 7), [1800, 300, 100, 10, 3])));
		$this->assertSame($months, $pick('All'), 'a point a month');
		$this->assertSame(2, $pick('1M'));
		$this->assertSame(3, $pick('6M'), 'back to the range of the page');
		$browser->assertNoJsErrors();
	}

	//The level chart shows a calendar quarter a page, with a pager under the title: page 1 the current quarter, < off on
	//it, > off on the last, the current page marked, a quarter without attempts empty; a player of one page has no pager
	public function testProfileLevelChartPagesAQuarterAtATime(): void
	{
		$quarterStart = fn(int $quartersAgo) => date('Y-m-d', strtotime(date('Y') . '-' . sprintf('%02d', intdiv((int) date('n') - 1, 3) * 3 + 1) . '-01 -' . (3 * $quartersAgo) . ' months'));
		$attempt = fn(string $day) => ['solved' => true, 'user_rating' => 1500, 'created' => $day . ' 10:00:00'];
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => [
				$attempt($quarterStart(0)),
				$attempt(date('Y-m-d', strtotime($quarterStart(0) . ' +1 day'))),
				$attempt($quarterStart(1)),
				$attempt($quarterStart(4))]]]]);
		$label = fn(int $quartersAgo) => date('M', strtotime($quarterStart($quartersAgo))) . ' – ' . date('M Y', strtotime($quarterStart($quartersAgo) . ' +2 months'));

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		// WebDriver hands the object back with its keys in no set order, so they are sorted
		$state = function () use ($browser) {
			$state = $browser->driver->executeScript(<<<'JS'
			const chart = document.getElementById('profile-chart-level');
			const pager = chart.querySelector('.level-chart__pager');
			return {
				range: chart.querySelector('.level-chart__range')?.textContent ?? null,
				days: chart.querySelectorAll('.bar-chart__category').length,
				empty: chart.querySelector('.level-chart__empty')?.textContent ?? null,
				items: pager ? [...pager.children].map(item => item.textContent) : null,
				current: pager?.querySelector('[aria-current="page"]')?.textContent ?? null,
				previous: pager?.querySelector('.level-chart__previous').disabled ?? null,
				next: pager?.querySelector('.level-chart__next').disabled ?? null};
			JS);
			ksort($state);
			return $state;
		};
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-level .level-chart__pager');
		$this->assertSame(['current' => '1', 'days' => 2, 'empty' => null, 'items' => ['<', '1', '2', '…', '5', '>'], 'next' => false, 'previous' => true, 'range' => $label(0)], $state());

		$browser->clickCssSelect('#profile-chart-level .level-chart__page:nth-of-type(3)');
		$browser->waitUntil(fn() => $state()['current'] === '2', 10, 'page 2');
		$this->assertSame(['days' => 1, 'items' => ['<', '1', '2', '3', '…', '5', '>'], 'previous' => false, 'range' => $label(1)], array_intersect_key($state(), array_flip(['range', 'days', 'items', 'previous'])));

		$browser->clickCssSelect('#profile-chart-level .level-chart__next');
		$browser->waitUntil(fn() => $state()['current'] === '3', 10, 'an empty quarter');
		$this->assertSame(['days' => 0, 'empty' => 'No problems in level mode this quarter.', 'range' => $label(2)], array_intersect_key($state(), array_flip(['range', 'days', 'empty'])));

		$browser->driver->executeScript("[...document.querySelectorAll('#profile-chart-level .level-chart__page')].find(button => button.textContent === '5').click()");
		$browser->waitUntil(fn() => $state()['current'] === '5', 10, 'the last page');
		$this->assertSame(['days' => 1, 'next' => true, 'range' => $label(4)], array_intersect_key($state(), array_flip(['range', 'days', 'next'])));

		$browser->clickCssSelect('#profile-chart-level .level-chart__previous');
		$browser->waitUntil(fn() => $state()['current'] === '4', 10, 'back to page 4');

		// one page needs no pager
		$browser->get('users/view/' . $context->otherUsers[0]['id']);
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-level .chart__title');
		$this->assertSame([null, 'No problems in level mode yet.'], [$state()['items'], $state()['empty']]);
		$browser->assertNoJsErrors();
	}

	public function testProblemHistoryDrawsTheRatingOfTheProblem(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [[
				'sets' => [['name' => 'set', 'num' => 1]],
				'attempts' => [
					['solved' => true, 'tsumego_rating' => 1200, 'created' => date('Y-m-d', strtotime('-3 days')) . ' 10:00:00'],
					['solved' => false, 'tsumego_rating' => 1250, 'created' => date('Y-m-d', strtotime('-1 day')) . ' 10:00:00']]]]]);

		$browser = Browser::instance();
		$browser->get('tsumegos/history/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorDisplayed('.timeGraph .line-chart__line');
		$this->assertSame('Rating history', $browser->find('.timeGraph .chart__title')->getText());
		// the value axis fits the rating instead of starting at zero, with its ticks at the edges of the ranks around it:
		// 9k from 1150, 8k from 1250
		$valueTicks = array_map(fn($tick) => $tick->getText(), $browser->getElements(".timeGraph .line-chart__tick[text-anchor='end']"));
		$this->assertSame(['9k', '8k'], $valueTicks);
		// the ranks are in the chart's text colour like the dates under it, not the green of the line
		$colors = $browser->driver->executeScript("return [...document.querySelectorAll('.timeGraph .line-chart__tick')].map(tick => getComputedStyle(tick).fill)");
		$this->assertCount(1, array_unique($colors), implode(', ', $colors));
		// the tooltip of the last day: the rating as a plain number, with its rank
		$browser->driver->executeScript(<<<'JS'
			const svg = document.querySelector('.timeGraph svg');
			const box = svg.getBoundingClientRect();
			svg.dispatchEvent(new MouseEvent('mousemove', {bubbles: true, clientX: box.right - 2, clientY: box.top + box.height / 2}));
			JS);
		$browser->waitUntilCssSelectorDisplayed('.timeGraph .chart__tooltip');
		$this->assertStringContainsString('Rating: 1250 (8k)', $browser->find('.timeGraph .chart__tooltip')->getText());
		$browser->assertNoJsErrors();
	}
}
