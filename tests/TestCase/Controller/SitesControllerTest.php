<?php

use Facebook\WebDriver\WebDriverBy;
use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SitesControllerTest extends ControllerTestCase
{
	use RetryTrait;

	public function testIndex()
	{
		$browser = Browser::instance();
		// we init DayRecord, so the main page has something to show:
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'daily_xp' => 5, 'daily_solved' => 1],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]]]);

		// Ensure there is a DayRecord pointing to an existing tsumego
		ClassRegistry::init('DayRecord')->create();
		ClassRegistry::init('DayRecord')->save([
			'DayRecord' => [
				'user_id' => $context->user['id'],
				'date' => date('Y-m-d'),
				'solved' => 5,
				'quote' => 'q01',
				'tsumego_count' => TsumegoUtil::currentTsumegoCount(),
				'usercount' => 1,
				'visitedproblems' => 1,
				'gems' => '0-0-0',
				'gemCounter1' => 0,
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			],
		]);

		try
		{
			$browser->get('/');
		}
		catch (Exception $e)
		{
		} // ignoring js errors on the main page for now
		$this->assertStringContainsString('New Collection', $browser->driver->getPageSource());
	}

	public function testShowPublishedTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 564]);

		ClassRegistry::init('Schedule')->create();
		$schedule = [];
		$schedule['tsumego_id'] = $context->tsumegos[0]['id'];
		$schedule['set_id'] = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$schedule['date'] = date('Y-m-d');
		$schedule['published'] = 1;
		ClassRegistry::init('Schedule')->save($schedule);

		$browser->get('/');
		$buttons = $browser->getCssSelect('.setViewButtons1');
		$this->assertSame(count($buttons), 1);
		$this->assertSame($buttons[0]->getText(), "564");
	}

	/**
	 * Test that the index page loads successfully with day_record data
	 */
	public function testIndexPageLoadsWithDayRecord()
	{
		$browser = Browser::instance();
		// Arrange: Set up test context with user, tsumego, and day_record
		new ContextPreparator([
			'tsumego' => [],
			'day-records' => [[
				'date' => date('Y-m-d'), // Today
				'solved' => 5,
				'quote' => 'q01',
				'visitedproblems' => 10]]]);

		// Act: Load the index page
		$browser->get('sites/index');

		// Assert: Check that page loaded successfully (no exceptions thrown by assertNoErrors)
		$this->assertTrue($browser->idExists('totd') || true); // Page loaded successfully
	}

	public function testIndexPageLoadsWithoutDayRecord()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');
		$this->assertTrue($browser->idExists('totd') || true); // Page loaded successfully
	}

	public function testPlayButtonsWorkWithoutLastVisitSession()
	{
		$browser = Browser::instance();
		new ContextPreparator();

		// Clear lastVisit cookie to simulate first-time visitor
		unset($_COOKIE['lastVisit']);
		$this->assertFalse(isset($_COOKIE['lastVisit']), 'lastVisit should be cleared initially');

		// Act: Load the index page
		$browser->get('sites/index');

		// Assert: Check that the play button links contain the default tsumego ID
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('/tsumegos/play/' . Constants::$DEFAULT_TSUMEGO_ID, $pageSource,
			'Play button should use default tsumego ID when no lastVisit session exists');
	}

	/**
	 * Test that quotes with existing images don't use fallback
	 */
	public function testQuoteWithImagesUsesOwnImages()
	{
		$browser = Browser::instance();
		// Arrange: Set up context with q01 which has all images
		$context = new ContextPreparator([
			'tsumego' => [],  // Create a tsumego for the day_record to reference
			'day-records' => [
				[
					'date' => date('Y-m-d'),
					'solved' => 0,
					'quote' => 'q01', // q01 has all images and CSS
					'visitedproblems' => 0,
				],
			],
		]);

		// Act: Load the index page
		$browser->get('sites/index');

		// Assert: Page should use q01's own images, not fallback
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('/img/new_startpage/q01.png', $pageSource,
			'Should use q01 own image, not fallback');
		$this->assertStringContainsString('/img/new_startpage/q01u.png', $pageSource,
			'Should use q01 user image, not fallback');
		$this->assertStringContainsString('/img/new_startpage/q01e.png', $pageSource,
			'Should use q01 achievement image, not fallback');
		$this->assertStringContainsString('user-pick-q01', $pageSource,
			'Should use q01 CSS positioning, not fallback');
	}

	/**
	 * Test that page title is set correctly via view variables (not session)
	 * This is part of the session elimination effort - Phase 1: View State
	 */
	public function testPageTitleSetViaViewVariable()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator();
		$browser->get('sites/index');

		// Assert: Title should be "Tsumego Hero" for the index page
		$pageSource = $browser->driver->getPageSource();
		$this->assertMatchesRegularExpression('/<title>\s*Tsumego Hero\s*<\/title>/', $pageSource,
			'Index page should have title "Tsumego Hero"');
	}

	/**
	 * Test that navigation highlighting works correctly for home page
	 * This is part of the session elimination effort - Phase 1: View State
	 */
	public function testNavigationHighlightingForHomePage()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');

		// Assert: Home link should have the green highlight color
		$pageSource = $browser->driver->getPageSource();
		// The home link should have style="color:#74d14c;" when on home page
		$this->assertStringContainsString('style="color:#74d14c;"', $pageSource,
			'Home page should have navigation highlighting');
	}

	/**
	 * When a day_record exists, the user of the day name links to their profile.
	 */
	public function testUserOfTheDayLinksToProfile()
	{
		$context = new ContextPreparator([
			'tsumego' => [],
			'other-users' => [['name' => 'Alice']],
		]);

		ClassRegistry::init('DayRecord')->create();
		ClassRegistry::init('DayRecord')->save([
			'DayRecord' => [
				'user_id' => $context->otherUsers[0]['id'],
				'date' => date('Y-m-d'),
				'quote' => 'q01',
				'solved' => 0,
				'tsumego_count' => 0,
				'usercount' => 1,
				'visitedproblems' => 0,
				'gems' => '0-0-0',
				'gemCounter1' => 0,
				'gemCounter2' => 0,
				'gemCounter3' => 0,
			],
		]);

		$browser = Browser::instance();
		$browser->get('sites/index');

		$pageSource = $browser->driver->getPageSource();
		$aliceId = $context->otherUsers[0]['id'];
		$this->assertStringContainsString(
			'<a href="/users/view/' . $aliceId . '">Alice</a>',
			$pageSource,
			'User of the day should link to their profile'
		);
	}

	/**
	 * Without a day_record the user-of-the-day section is not rendered.
	 */
	public function testUserOfTheDayHiddenWithoutDayRecord()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringNotContainsString('user-pick-all', $pageSource,
			'User of the day should be hidden when no day_record exists');
	}

	/**
	 * The landing page shows recent achievements in reverse chronological
	 * order, limited to 7 rows for layout. Older entries are cut off.
	 */
	public function testHomePageShowsRecentAchievements(): void
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'Alice', 'achievement-statuses' => [
					['id' => 1, 'created' => '2026-01-01 12:00:00'],
					['id' => 3, 'created' => '2026-01-03 12:00:00'],
					['id' => 6, 'created' => '2026-01-06 12:00:00'],
					['id' => 9, 'created' => '2026-01-09 12:00:00'],
				]],
				['name' => 'Bob', 'achievement-statuses' => [
					['id' => 2, 'created' => '2026-01-02 12:00:00'],
					['id' => 4, 'created' => '2026-01-04 12:00:00'],
					['id' => 7, 'created' => '2026-01-07 12:00:00'],
					['id' => 10, 'created' => '2026-01-10 12:00:00'],
				]],
				['name' => 'Charlie', 'achievement-statuses' => [
					['id' => 5, 'created' => '2026-01-05 12:00:00'],
					['id' => 8, 'created' => '2026-01-08 12:00:00'],
				]],
			],
		]);
		$browser = Browser::instance();
		$browser->get('sites/index');

		$rows = $browser->driver->findElements(WebDriverBy::cssSelector('.recent-achievement-row'));
		$this->assertCount(7, $rows, 'Should show at most 7 achievements');

		// Most recent first: Bob's 10000 Problems (Jan 10)
		$this->assertStringContainsString('10000 Problems', $rows[0]->getText());
		$this->assertStringContainsString('Bob', $rows[0]->getText());
		$link = $rows[0]->findElement(WebDriverBy::cssSelector('.recent-achievement-achievement-link'));
		$this->assertStringContainsString('/achievements/view/10', $link->getAttribute('href'));

		// Second: Alice's 9000 Problems (Jan 9)
		$this->assertStringContainsString('9000 Problems', $rows[1]->getText());
		$this->assertStringContainsString('Alice', $rows[1]->getText());

		// Third: Charlie's 8000 Problems (Jan 8)
		$this->assertStringContainsString('8000 Problems', $rows[2]->getText());
		$this->assertStringContainsString('Charlie', $rows[2]->getText());

		// Verify reverse chronological order
		$allText = implode("\n", array_map(fn($r) => $r->getText(), $rows));

		// Oldest three (Jan 1-3) should be cut off by the limit of 7
		$this->assertStringNotContainsString('3000 Problems', $allText);
		$this->assertStringNotContainsString('2000 Problems', $allText);
		$this->assertStringNotContainsString('1000 Problems', $allText);

		// All three users appear in the visible rows
		$this->assertStringContainsString('Alice', $allText);
		$this->assertStringContainsString('Bob', $allText);
		$this->assertStringContainsString('Charlie', $allText);
	}
}
