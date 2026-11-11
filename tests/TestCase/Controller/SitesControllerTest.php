<?php

use App\Model\Table\UserTable;
use Facebook\WebDriver\WebDriverBy;
use App\Utility\Auth;
use App\Utility\Avatar;
use App\Utility\Constants;
use App\Utility\TsumegoUtil;
use App\Utility\SiteGrowth;
use PHPUnit\Framework\Attributes\Group;

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

	//The problems of a set published together are shown in the order of their numbers, whatever order
	//they were created in.
	public function testLatestAdditionsShowEachSetInTheOrderOfItsNumbers(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'Evaluation', 'num' => '3']]],
			['sets' => [['name' => 'Other', 'num' => '2']]],
			['sets' => [['name' => 'Evaluation', 'num' => '1']]],
			['sets' => [['name' => 'Other', 'num' => '1']]],
			['sets' => [['name' => 'Evaluation', 'num' => '2']]],
		]]);
		$today = date('Y-m-d');
		foreach ($context->tsumegos as $tsumego)
			$this->addScheduleEntry([
				'tsumego_id' => $tsumego['id'],
				'set_id' => $tsumego['set-connections'][0]['set_id'],
				'date' => $today,
				'published' => 1,
			]);

		$this->get('/');

		$numbersBySet = [];
		foreach ($this->viewVariable('tsumegoButtonsOfPublishedTsumegos') as $button)
			$numbersBySet[$button->setTitle][] = $button->order;
		$this->assertSame([1, 2, 3], $numbersBySet['Evaluation']);
		$this->assertSame([1, 2], $numbersBySet['Other']);
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

	//Play leads a first-time visitor to the first problem of the first site collection in the order
	//of the collections page: not one in the trash, not one of a player's collection
	public function testPlayLeadsAFirstVisitToTheFirstProblemOfTheSiteCollections()
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'second collection', 'num' => 1, 'order' => 2]]],
			['sets' => [['name' => 'first collection', 'num' => 2, 'order' => 1]]],
			['sets' => [['name' => 'first collection', 'num' => 3, 'order' => 1]]],
			['sets' => [['name' => 'first collection', 'num' => 1, 'order' => 1]], 'deleted' => '2026-01-01 00:00:00'],
			['sets' => [['name' => 'player collection', 'num' => 1, 'order' => 0, 'user_id' => 'self']]]]]);
		Auth::logout();
		unset($_COOKIE['lastVisit']);

		$this->get('/');

		$expected = 'href="/tsumegos/play/' . $context->tsumegos[1]['id'] . '"';
		$this->assertResponseContains($expected, 'The menu Play leads to the first problem');
		$this->assertGreaterThanOrEqual(2, substr_count((string) $this->_response->getBody(), $expected), 'The Level Mode tile leads there too');
	}

	//A site without a problem in its collections has nothing to play, so Play shows the collections
	public function testPlayLeadsToTheCollectionsWithoutAnyProblem()
	{
		new ContextPreparator();
		Auth::logout();
		unset($_COOKIE['lastVisit']);

		$this->assertNull(TsumegoUtil::firstProblemID());
		$this->assertSame('/sets', TsumegoUtil::playUrl());
	}

	//A returning visitor continues with the problem opened last
	public function testPlayLeadsToTheProblemOpenedLast()
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'first collection', 'num' => 1, 'order' => 1]]],
			['sets' => [['name' => 'first collection', 'num' => 2, 'order' => 1]]]]]);
		Auth::logout();
		$_COOKIE['lastVisit'] = (string) $context->tsumegos[1]['id'];

		$this->get('/');

		$this->assertResponseContains('href="/tsumegos/play/' . $context->tsumegos[1]['id'] . '"');
		$this->assertResponseNotContains('href="/tsumegos/play/' . $context->tsumegos[0]['id'] . '"');
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
	#[Group('browser')]
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
		$props = json_decode($browser->find('.chart-box[data-growth-chart-root]')->getAttribute('data-props'), true)['growth'];
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
		$this->assertStringContainsString('<li><a href="/forums">Forums</a></li>', $pageSource, 'The menu has the forums');
		$this->assertStringContainsString('href="/comments">Discuss</a>', $pageSource, 'The comments are under Home');
	}

	//A logged-out user sees a Sign In link in the nav (both the desktop block and
	//the drawer entry), the forums and no Discuss, and no account block.
	public function testLoggedOutNavShowsSignInAndTheForums()
	{
		new ContextPreparator(['user' => null]);

		$browser = Browser::instance();
		$browser->getAnonymous('/');

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('site-nav__signin-drawer', $pageSource, 'Logged-out nav should show a Sign In drawer block');
		$this->assertStringContainsString('site-nav__signin', $pageSource, 'Logged-out nav should show the desktop Sign In block');
		$this->assertStringContainsString('id="signInMenu"', $pageSource, 'Logged-out nav should render the Sign In link');
		$this->assertStringContainsString('<li><a href="/forums">Forums</a></li>', $pageSource, 'The menu has the forums');
		$this->assertStringNotContainsString('>Discuss</a>', $pageSource, 'The comments need a sign-in');
		$this->assertStringNotContainsString('users/logout', $pageSource, 'Logged-out nav should not offer Sign Out');
	}

	//The title and the links of the latest additions are light text on the dark picture of their box; until the
	//picture has loaded (a phone on a slow line), or when it doesn't, the box itself is dark, in both themes
	public function testLatestAdditionsReadWithoutTheirPicture(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => ['set_order' => 7, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]AW[dd])']]);
		$this->addScheduleEntry([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'date' => date('Y-m-d'),
			'published' => 1]);
		$browser->get('/');
		foreach (['light', 'dark'] as $theme)
		{
			$contrasts = $browser->driver->executeScript(<<<'JS'
				document.documentElement.dataset.theme = arguments[0];
				const box = document.querySelector('.new-tsumego-box--news');
				box.style.backgroundImage = 'none';
				const rgb = color => color.match(/[\d.]+/g).map(Number);
				const luminance = color => {
					const [r, g, b] = rgb(color).slice(0, 3).map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
					return 0.2126 * r + 0.7152 * g + 0.0722 * b;
				};
				const contrast = (a, b) => { const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x); return (light + 0.05) / (dark + 0.05); };
				const background = getComputedStyle(box).backgroundColor;
				return {
					background: background,
					opaque: rgb(background)[3] === undefined || rgb(background)[3] === 1,
					heading: contrast(getComputedStyle(document.querySelector('.latest-additions-heading')).color, background),
					link: contrast(getComputedStyle(document.querySelector('.scheduleTsumego')).color, background)};
				JS, [$theme]);
			$this->assertTrue($contrasts['opaque'], $theme . ': the box has a background colour of its own, ' . $contrasts['background']);
			$this->assertGreaterThanOrEqual(4.5, $contrasts['heading'], $theme . ': the title reads on ' . $contrasts['background']);
			$this->assertGreaterThanOrEqual(4.5, $contrasts['link'], $theme . ': the link reads on ' . $contrasts['background']);
		}
	}

	private function addScheduleEntry(array $fields): void
	{
		$scheduleTable = $this->fetchTable('Schedule');
		$scheduleTable->saveOrFail($scheduleTable->newEntity($fields));
	}
}
