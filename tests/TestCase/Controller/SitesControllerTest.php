<?php

use App\Model\Table\UserTable;
use Facebook\WebDriver\WebDriverBy;
use App\Utility\Avatar;
use App\Utility\Constants;
use App\Utility\SiteGrowth;

#[RetryOnBrowserFailure]
class SitesControllerTest extends IntegrationTestCase
{
	public function testIndex()
	{
		$browser = Browser::instance();
		// we init DayRecord, so the main page has something to show:
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'daily_xp' => 5, 'daily_solved' => 1],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]]]);

		// Ensure there is a DayRecord pointing to an existing tsumego
		$dayRecordTable = $this->fetchTable('DayRecord');
		$dayRecordTable->saveOrFail($dayRecordTable->newEntity([
			'user_id' => $context->user['id'],
			'date' => date('Y-m-d'),
			'quote' => 'q01',
			'gems' => '0-0-0',
			'emerald_solves' => 0,
			'sapphire_solves' => 0,
			'ruby_solves' => 0,
		]));

		try
		{
			$browser->get('/');
		}
		catch (Exception $e)
		{
		} // ignoring js errors on the main page for now
		$this->assertStringContainsString('whats-new-list', $browser->driver->getPageSource());
	}

	public function testShowsPublishedTsumegoWithDateLabel()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => ['set_order' => 564, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]AW[dd])']]);

		$today = date('Y-m-d');
		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => $today,
			'published' => 1,
		]);

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringContainsString('Latest additions', $source);
		$this->assertStringContainsString('<time datetime="' . $today . '"', $source);

		$buttons = $browser->getElements('.problem-nav__number');
		$this->assertCount(1, $buttons);
		$this->assertSame('564', $buttons[0]->getText());

		// Published tsumego buttons should have preview data
		$previewLinks = $browser->getElements('.new-tsumego-box a[data-sgf-preview]');
		$this->assertNotEmpty($previewLinks);
	}

	public function testShowsLatestPublishedWhenTodayEmpty()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 564]);

		$pastDate = date('Y-m-d', strtotime('-20 days'));
		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => $pastDate,
			'published' => 1,
		]);

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringContainsString('Latest additions', $source);
		$this->assertStringContainsString('<time datetime="' . $pastDate . '"', $source);

		$buttons = $browser->getElements('.problem-nav__number');
		$this->assertCount(1, $buttons);
		$this->assertSame('564', $buttons[0]->getText());
	}

	public function testIgnoresUnpublishedFutureEntries()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 564]);

		$publishedDate = date('Y-m-d', strtotime('-100 days'));
		$unpublishedDate = date('Y-m-d', strtotime('+200 days'));

		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => $publishedDate,
			'published' => 1,
		]);
		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => $unpublishedDate,
			'published' => 0,
		]);

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringContainsString('Latest additions', $source);
		$this->assertStringContainsString('<time datetime="' . $publishedDate . '"', $source);
	}

	public function testIgnoresFuturePublishedEntries()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 564]);

		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => date('Y-m-d', strtotime('+100 days')),
			'published' => 1,
		]);

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringNotContainsString('Latest additions', $source);
	}

	public function testNoLatestAdditionsWhenScheduleEmpty()
	{
		$browser = Browser::instance();
		new ContextPreparator();

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringNotContainsString('Latest additions', $source);
	}

	public function testShowsMultipleSetsPublishedSameDay()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Level Evaluation', 'num' => '1']]],
			'tsumegos' => [
				['sets' => [['name' => 'Set Beginner', 'num' => '1']]],
			],
		]);

		$today = date('Y-m-d');
		foreach ($context->tsumegos as $tsumego)
			$this->addScheduleEntry([
				'tsumego_id' => $tsumego['id'],
				'set_id' => $tsumego['set-connections'][0]['set_id'],
				'date' => $today,
				'published' => 1,
			]);

		$browser->get('/');
		$source = $browser->driver->getPageSource();

		$this->assertStringContainsString('Latest additions', $source);
		$this->assertStringContainsString('Level Evaluation', $source);
		$this->assertStringContainsString('Set Beginner', $source);
		$this->assertSame(2, substr_count($source, 'class="scheduleTsumego"'));
		$this->assertCount(2, $browser->getElements('.problem-nav__number'));
	}

	//Test that the index page loads successfully with day_record data
	public function testIndexPageLoadsWithDayRecord()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'tsumego' => [],
			'day-records' => [[
				'date' => date('Y-m-d'),
				'quote' => 'q01']]]);

		// Act: Load the index page
		$browser->get('sites/index');

		// Assert: the index page rendered with a clean JS console
		$this->assertTrue($browser->titleContains('Tsumego Hero'), 'The index page rendered');
		$browser->assertNoJsErrors();
	}

	public function testIndexPageLoadsWithoutDayRecord()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');
		$this->assertTrue($browser->titleContains('Tsumego Hero'), 'The index page rendered');
		$browser->assertNoJsErrors();
	}

	//The news on the home page points to the What's new page, which lists every change.
	public function testWhatsNewLinksToChangelog()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('/');

		$browser->find('.whats-new-list ~ a[href="/changelog"]')->click();
		$this->assertStringContainsString('/changelog', $browser->driver->getCurrentURL());
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

	//The Rating Mode tile opens the rating mode; it used to open a problem in the level mode.
	public function testRatingModeTileOpensRatingMode()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
			'tsumegos' => [['rating' => 1000, 'description' => '1000 rating tsumego', 'sets' => [['name' => 'set 1', 'num' => '1']]]]]);

		$browser = Browser::instance();
		$browser->get('/');
		$browser->find('.mode-button-rating')->click();

		$this->assertSame('1000 rating tsumego', $browser->find('#descriptionText')->getText());
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode_id']);
	}

	//Test that quotes with existing images don't use fallback
	public function testQuoteWithImagesUsesOwnImages()
	{
		$browser = Browser::instance();
		// Arrange: Set up context with q01 which has all images
		$context = new ContextPreparator([
			'tsumego' => [],  // Create a tsumego for the day_record to reference
			'day-records' => [
				[
					'date' => date('Y-m-d'),
					'quote' => 'q01', // q01 has all images and CSS
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

	//Test that page title is set correctly via view variables (not session)
	//This is part of the session elimination effort - Phase 1: View State
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

	//Test that navigation highlighting works correctly for home page
	//This is part of the session elimination effort - Phase 1: View State
	public function testNavigationHighlightingForHomePage()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');

		// The home link gets the green highlight via the nav__link--active class
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('class="homeMenuLink nav__link--active"', $pageSource,
			'Home page should have navigation highlighting');
	}

	//When a day_record exists, the user of the day name links to their profile.
	public function testUserOfTheDayLinksToProfile()
	{
		$context = new ContextPreparator([
			'tsumego' => [],
			'other-users' => [['name' => 'Alice']],
		]);

		$dayRecordTable = $this->fetchTable('DayRecord');
		$dayRecordTable->saveOrFail($dayRecordTable->newEntity([
			'user_id' => $context->otherUsers[0]['id'],
			'date' => date('Y-m-d'),
			'quote' => 'q01',
			'gems' => '0-0-0',
			'emerald_solves' => 0,
			'sapphire_solves' => 0,
			'ruby_solves' => 0,
		]));

		$browser = Browser::instance();
		$browser->get('sites/index');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('DN_Alice', $pageSource,
			'User of the day name should appear on the page');
		$this->assertStringContainsString(
			'href="/users/view/' . $context->otherUsers[0]['id'] . '"',
			$pageSource,
			'User of the day should link to their profile'
		);
	}

	//Without a day_record the user-of-the-day section is not rendered.
	public function testUserOfTheDayHiddenWithoutDayRecord()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringNotContainsString('user-pick-all', $pageSource,
			'User of the day should be hidden when no day_record exists');
	}

	//The landing page shows recent achievements in reverse chronological
	//order, limited to 7 rows for layout. Older entries are cut off.
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

		$rows = $browser->getElements('.recent-achievement-row');
		$this->assertCount(7, $rows, 'Should show at most 7 achievements');

		// Most recent first: Bob's 10000 Problems (Jan 10)
		$this->assertStringContainsString('10000 Problems', $rows[0]->getText());
		$this->assertStringContainsString('DN_Bob', $rows[0]->getText());
		$link = $rows[0]->findElement(WebDriverBy::cssSelector('.recent-achievement-achievement-link'));
		$this->assertStringContainsString('/achievements/view/10', $link->getAttribute('href'));

		// The row shows the board the site draws for the account, which keeps the square
		// corners of a board instead of the circle of a picture
		$avatar = $rows[0]->findElement(WebDriverBy::cssSelector('.recent-achievement-user-link img.user-avatar'));
		$this->assertStringContainsString('/board/', $avatar->getAttribute('src'));
		$this->assertStringContainsString('user-avatar--board', $avatar->getAttribute('class'));

		// Second: Alice's 9000 Problems (Jan 9)
		$this->assertStringContainsString('9000 Problems', $rows[1]->getText());
		$this->assertStringContainsString('DN_Alice', $rows[1]->getText());

		// Third: Charlie's 8000 Problems (Jan 8)
		$this->assertStringContainsString('8000 Problems', $rows[2]->getText());
		$this->assertStringContainsString('DN_Charlie', $rows[2]->getText());

		// Verify reverse chronological order
		$allText = implode("\n", array_map(fn($row) => $row->getText(), $rows));

		// Oldest three (Jan 1-3) should be cut off by the limit of 7
		$this->assertStringNotContainsString('3000 Problems', $allText);
		$this->assertStringNotContainsString('2000 Problems', $allText);
		$this->assertStringNotContainsString('1000 Problems', $allText);

		// All three users appear in the visible rows
		$this->assertStringContainsString('DN_Alice', $allText);
		$this->assertStringContainsString('DN_Bob', $allText);
		$this->assertStringContainsString('DN_Charlie', $allText);
	}

	//A picture of another host can stop working, which the browser draws as a broken image.
	//The board of the account takes over, so the row keeps its square avatar.
	//The picture is broken by the test instead of by the fixture: a page that loads with a
	//failing image records it as an error, and every navigation asserts that there are none.
	public function testAnAvatarWhosePictureIsGoneShowsTheBoardOfTheAccount(): void
	{
		$context = new ContextPreparator([
			'other-users' => [[
				'name' => 'Alice',
				'external_id' => '123456789012345678901',
				'picture' => '/img/ac036.png',
				'avatar_setting_id' => UserTable::AVATAR_GOOGLE,
				'achievement-statuses' => [['id' => 1, 'created' => '2026-01-01 12:00:00']],
			]],
		]);
		$userId = $context->otherUsers[0]['id'];
		$browser = Browser::instance();
		$browser->get('sites/index');

		$avatar = '.recent-achievement-user-link img.user-avatar';
		$before = $browser->driver->executeScript("const img = document.querySelector('{$avatar}'); return img.getAttribute('src') + ' | ' + img.className;");
		$this->assertStringContainsString('/img/ac036.png', $before, 'The row shows the picture the player picked');
		$this->assertStringNotContainsString('user-avatar--board', $before);

		$browser->driver->executeScript("document.querySelector('{$avatar}').src = '/img/no-such-picture.png';");
		$browser->waitUntilJs("document.querySelector('{$avatar}').src.endsWith('/avatar/{$userId}')");

		$after = $browser->driver->executeScript("return document.querySelector('{$avatar}').className;");
		$this->assertStringContainsString('user-avatar--board', $after);

		// The page records the broken picture the test asked for as an error, so the record
		// is dropped: a later check on this page would otherwise assert on it.
		$browser->driver->executeScript('window.__jsErrors = [];');
	}

	//The home page hands the growth of the site to the chart, a point a month for the problems and the users.
	/**
	 * @group browser
	 */
	public function testHomepageChartGetsTheSiteGrowth(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [['sets' => [['name' => 'public set', 'num' => 1]]]]]);
		// the cache is a file, it would carry the graph of the previous test over
		SiteGrowth::forget();

		$browser = Browser::instance();
		$browser->get('/');

		$chartData = SiteGrowth::chartData();
		$this->assertNotEmpty($chartData);
		$props = json_decode($browser->find('.chart-box[data-line-chart-root]')->getAttribute('data-props'), true);
		$this->assertSame(['Problems', 'Users'], array_column($props['series'], 'label'));
		$this->assertSame(array_map(fn($month) => [$month['date'], $month['problems']], $chartData), $props['series'][0]['points']);
		$this->assertSame(array_map(fn($month) => [$month['date'], $month['users']], $chartData), $props['series'][1]['points']);
	}

	//A logged-in user sees their account block (name + Sign Out) in the nav and
	//no Sign In link, and Discuss is a real link.
	public function testLoggedInNavShowsAccountAndNoSignIn()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$browser = Browser::instance();
		$browser->get('/');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('site-nav__account', $pageSource, 'Logged-in nav should have an account block');
		$this->assertStringContainsString('users/logout', $pageSource, 'Logged-in account block should offer Sign Out');
		$this->assertStringNotContainsString('site-nav__signin-drawer', $pageSource, 'Logged-in nav should not show a Sign In drawer block');
		$this->assertStringNotContainsString('class="discuss-disabled"', $pageSource, 'Logged-in nav should not show the disabled Discuss link');
	}

	//A logged-out user sees a Sign In link in the nav (both the desktop block and
	//the drawer entry) and a disabled Discuss link, and no account block.
	public function testLoggedOutNavShowsSignInAndDisabledDiscuss()
	{
		new ContextPreparator(['user' => null]);

		$browser = Browser::instance();
		$browser->getAnonymous('/');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('site-nav__signin-drawer', $pageSource, 'Logged-out nav should show a Sign In drawer block');
		$this->assertStringContainsString('site-nav__signin', $pageSource, 'Logged-out nav should show the desktop Sign In block');
		$this->assertStringContainsString('id="signInMenu"', $pageSource, 'Logged-out nav should render the Sign In link');
		$this->assertStringContainsString('class="discuss-disabled"', $pageSource, 'Logged-out nav should show the disabled Discuss link');
		$this->assertStringNotContainsString('users/logout', $pageSource, 'Logged-out nav should not offer Sign Out');
	}

	private function addScheduleEntry(array $fields): void
	{
		$scheduleTable = $this->fetchTable('Schedule');
		$scheduleTable->saveOrFail($scheduleTable->newEntity($fields));
	}
}
