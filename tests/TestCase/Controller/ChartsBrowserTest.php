<?php

use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\SiteGrowth;
use Facebook\WebDriver\WebDriverBy;

//The charts of the site, drawn by app/charts: the growth of the site on the home page, the days of a player on the
//profile and the rating of a problem in its history.
/**
 * @group browser
 */
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

		// the left side opens on the level mode, the newest day first
		$browser->waitUntilCssSelectorDisplayed('#userShowLevelLeft .bar-chart__bar');
		$texts = fn(string $selector) => array_map(fn($element) => $element->getText(), $browser->getElements('#userShowLevelLeft ' . $selector));
		$this->assertSame([$yesterday, $twoDaysAgo], $texts('.bar-chart__category'));
		$this->assertSame(['1', '3'], $texts('.bar-chart__total'));
		$this->assertSame(['2'], $texts("[data-label='Solves']"));
		$this->assertSame(['1', '1'], $texts("[data-label='Fails']"));

		// the right side opens on the rating
		$browser->waitUntilCssSelectorDisplayed('#userShowRatingRight .line-chart__line');
		$this->assertSame('Overall rating', $browser->find('#userShowRatingRight .chart__title')->getText());

		$browser->clickId('userShowTimeButtonLeft');
		$browser->waitUntilCssSelectorDisplayed('#userShowTimeLeft .bar-chart__bar');
		$this->assertSame('2', $browser->find('#userShowTimeLeft .bar-chart__total')->getText());
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
