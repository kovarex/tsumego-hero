<?php

use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
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

	public function testProfileDrawsTheSolvesFailsRatingAndTimeModeRunsOfThePlayer(): void
	{
		$twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [[
				'sets' => [['name' => 'set', 'num' => 1]],
				'attempts' => [
					['solved' => true, 'user_rating' => 1500, 'created' => $twoDaysAgo . ' 10:00:00'],
					['solved' => true, 'user_rating' => 1510, 'created' => $twoDaysAgo . ' 11:00:00'],
					['solved' => false, 'user_rating' => 1490, 'created' => $twoDaysAgo . ' 12:00:00'],
					['solved' => false, 'user_rating' => 1480, 'created' => $yesterday . ' 10:00:00']]]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				['category' => TimeModeCategoryTable::BLITZ, 'status' => TimeModeSessionStatusTable::SOLVED, 'rank' => '5k'],
				['category' => TimeModeCategoryTable::BLITZ, 'status' => TimeModeSessionStatusTable::FAILED, 'rank' => '5k']]]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);

		// all three at once: the level mode on the left, the newest day first
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-level .bar-chart__bar');
		$texts = fn(string $selector) => array_map(fn($element) => $element->getText(), $browser->getElements('#profile-chart-level ' . $selector));
		$this->assertSame([$yesterday, $twoDaysAgo], $texts('.bar-chart__category'));
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
		$state = fn() => $browser->driver->executeScript(<<<'JS'
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
		$browser->waitUntilCssSelectorDisplayed('#profile-chart-level .level-chart__pager');
		$this->assertSame(['range' => $label(0), 'days' => 2, 'empty' => null, 'items' => ['<', '1', '2', '…', '5', '>'], 'current' => '1', 'previous' => true, 'next' => false], $state());

		$browser->clickCssSelect('#profile-chart-level .level-chart__page:nth-of-type(3)');
		$browser->waitUntil(fn() => $state()['current'] === '2', 10, 'page 2');
		$this->assertSame(['range' => $label(1), 'days' => 1, 'items' => ['<', '1', '2', '3', '…', '5', '>'], 'previous' => false], array_intersect_key($state(), array_flip(['range', 'days', 'items', 'previous'])));

		$browser->clickCssSelect('#profile-chart-level .level-chart__next');
		$browser->waitUntil(fn() => $state()['current'] === '3', 10, 'an empty quarter');
		$this->assertSame(['range' => $label(2), 'days' => 0, 'empty' => 'No problems in level mode this quarter.'], array_intersect_key($state(), array_flip(['range', 'days', 'empty'])));

		$browser->driver->executeScript("[...document.querySelectorAll('#profile-chart-level .level-chart__page')].find(button => button.textContent === '5').click()");
		$browser->waitUntil(fn() => $state()['current'] === '5', 10, 'the last page');
		$this->assertSame(['range' => $label(4), 'days' => 1, 'next' => true], array_intersect_key($state(), array_flip(['range', 'days', 'next'])));

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
		// the value axis fits the rating instead of starting at zero
		$valueTicks = array_map(fn($tick) => $tick->getText(), $browser->getElements(".timeGraph .line-chart__tick[text-anchor='end']"));
		$this->assertSame('1,200', $valueTicks[0]);
		$this->assertSame('1,250', end($valueTicks));
		$browser->assertNoJsErrors();
	}
}
