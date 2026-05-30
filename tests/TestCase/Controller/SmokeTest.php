<?php

use Selenium\Keys;

App::uses('TimeModeUtil', 'Utility');

/**
 * Smoke tests - visit all major pages and verify:
 * - No JavaScript errors
 * - CSS is loaded properly
 * - Core functionality works
 */
class SmokeTest extends ControllerTestCase
{
	public function testAllMajorPagesLoadWithoutErrors()
	{
		// Create realistic production-like test data
		$context = new ContextPreparator([
			'user' => ['name' => 'smoketest', 'rating' => 1500, 'admin' => true],
			'other-users' => [
				['name' => 'opponent1', 'rating' => 1600],
				['name' => 'opponent2', 'rating' => 1400],
			],
			'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'Free Set', 'num' => '1']]],
			// Rating=1500 tsumego needed so ratingMode finds it for user with rating 1500 (range ±240)
			'tsumegos' => [['rating' => 1100], ['rating' => 1200], ['rating' => 1500, 'sets' => [['name' => 'Rating Set', 'num' => 1]]]],
			'tags' => [['name' => 'capture', 'approved' => 1]],
			'day-records' => [['date' => date('Y-m-d'), 'solved' => 10, 'visitedproblems' => 20]],
			// Two ranks needed so timeMode/overview hits the BUCKET GROUP BY code path (count==1 uses a simpler query)
			'time-mode-ranks' => ['15k', '14k'],
			'time-mode-sessions' => [
				// In-progress session for timeMode/play
				['category' => 1, 'rank' => '15k', 'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]],
				// Finished session for timeMode/result
				['category' => 1, 'rank' => '14k', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]],
			]]);
		$browser = Browser::instance();
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$tsumegoId = $context->tsumegos[0]['id'];
		$userId = $context->user['id'];
		$tagId = $context->tags[0]['id'];
		$finishedSessionId = $context->timeModeSessions[1]['id'];

		// auth values:
		//   'both'      - tested logged-in AND anonymously
		//   'logged-in' - only tested when logged in (e.g. requires session data)
		//   'anonymous' - only tested when logged out (e.g. login/registration forms)
		$pages = [
			// Core pages
			['url' => '', 'name' => 'Homepage', 'auth' => 'both'],

			// Collections
			['url' => 'sets', 'name' => 'Sets index', 'auth' => 'both'],
			['url' => "sets/view/$setId", 'name' => 'Set view', 'auth' => 'both'],
			['url' => 'sets/view/favorites', 'name' => 'Favorites', 'auth' => 'logged-in'],
			['url' => 'sets/sandbox', 'name' => 'Sandbox/premium sets', 'auth' => 'logged-in'],

			// SGF upload history
			['url' => "sgfs/view/$tsumegoId", 'name' => 'SGF upload history', 'auth' => 'both'],

			// Tsumego play
			['url' => $setConnectionId, 'name' => 'Tsumego play', 'auth' => 'both'],
			['url' => 'ratingMode', 'name' => 'Rating mode play', 'auth' => 'logged-in'],
			['url' => 'timeMode/overview', 'name' => 'Time mode overview', 'auth' => 'logged-in'],
			['url' => 'timeMode/play', 'name' => 'Time mode play', 'auth' => 'logged-in'],
			['url' => "timeMode/result/$finishedSessionId", 'name' => 'Time mode result', 'auth' => 'logged-in'],

			// Achievements
			['url' => 'achievements', 'name' => 'Achievements', 'auth' => 'both'],
			['url' => 'achievements/view/1', 'name' => 'Achievement detail', 'auth' => 'both'],
			['url' => "achievements/user/$userId", 'name' => 'User achievements', 'auth' => 'both'],

			// User pages
			['url' => "users/view/$userId", 'name' => 'User profile', 'auth' => 'both'],
			['url' => "tags/user/$userId", 'name' => 'User contributions', 'auth' => 'both'],
			['url' => "users/solveHistory/$userId", 'name' => 'Solve history', 'auth' => 'both'],
			['url' => 'users/authors', 'name' => 'About/Authors', 'auth' => 'both'],
			['url' => 'users/highscore', 'name' => 'Level highscore', 'auth' => 'both'],
			// ['url' => 'users/highscore3', 'name' => 'Time highscore', 'auth' => 'logged-in'], // dead/broken code - query uses wrong column names
			['url' => 'users/rating', 'name' => 'Rating highscore', 'auth' => 'both'],
			['url' => 'users/achievements', 'name' => 'Achievement highscore', 'auth' => 'both'],
			['url' => 'users/added_tags', 'name' => 'Tag highscore', 'auth' => 'both'],
			['url' => 'users/leaderboard', 'name' => 'Daily leaderboard', 'auth' => 'both'],

			// Auth pages (anonymous-only forms)
			['url' => 'users/login', 'name' => 'Login form', 'auth' => 'anonymous'],
			['url' => 'users/add', 'name' => 'Registration form', 'auth' => 'anonymous'],
			['url' => 'users/resetpassword', 'name' => 'Reset password form', 'auth' => 'anonymous'],
			['url' => 'users/newpassword/invalid-checksum', 'name' => 'New password form (invalid link)', 'auth' => 'anonymous'],

			// User account pages
			['url' => 'users/delete_account', 'name' => 'Delete account form', 'auth' => 'logged-in'],

			// Comments (requires login)
			['url' => 'comments', 'name' => 'Comments', 'auth' => 'logged-in'],

			// Tags
			['url' => 'tags/add', 'name' => 'Add tag form', 'auth' => 'both'],
			['url' => "tags/view/$tagId", 'name' => 'Tag detail', 'auth' => 'both'],

			// Tutorials
			['url' => 'sites/websitefunctions', 'name' => 'Website functions', 'auth' => 'both'],
			['url' => 'sites/gotutorial', 'name' => 'Go rules tutorial', 'auth' => 'both'],
			['url' => 'sites/impressum', 'name' => 'Legal notice', 'auth' => 'both'],
			['url' => 'sites/privacypolicy', 'name' => 'Privacy policy', 'auth' => 'both'],
			['url' => 'sites/about', 'name' => 'About/Credits', 'auth' => 'both'],

			// Issues
			['url' => 'tsumego-issues', 'name' => 'Issues list', 'auth' => 'both'],
		];

		// Pass 1: logged-in
		foreach (array_filter($pages, fn($p) => $p['auth'] !== 'anonymous') as $page)
			$this->assertPageLoadsOk($browser, $page['url'], $page['name'] . ' (logged-in)');

		// Pass 2: anonymous — clear PHP-side auth (otherwise Browser::get re-injects the cookie)
		Auth::logout();
		$browser->driver->manage()->deleteAllCookies();
		foreach (array_filter($pages, fn($p) => $p['auth'] !== 'logged-in') as $page)
			$this->assertPageLoadsOk($browser, $page['url'], $page['name'] . ' (anonymous)', false);
	}

	/**
	 * Test that error pages also render with the site layout (logo, CSS, no JS errors).
	 */
	public function testErrorPagesRenderWithLayout()
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();

		// Navigate directly (not via Browser::get which auto-asserts no errors)
		$browser->driver->get(Util::getMyAddress() . '/nonexistent-page-' . uniqid());

		$this->assertLogoExists($browser, '404 error page');
		$this->assertCssLoaded($browser, '404 error page');
		$browser->assertNoJsErrors();

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('Page Not Found', $pageSource, '404 error page should show "Page Not Found"');
	}

	private function assertHttpStatusOk($browser, string $label): void
	{
		$status = $browser->driver->executeScript(
			"var xhr = new XMLHttpRequest(); xhr.open('HEAD', window.location.href, false); xhr.send(); return xhr.status;"
		);
		$this->assertSame(200, $status, "$label: page returned HTTP $status");
	}

	private function assertPageLoadsOk($browser, string $url, string $label, bool $loggedIn = true): void
	{
		if ($loggedIn)
			$browser->get($url);
		else
			$browser->getAnonymous($url);
		$this->assertLogoExists($browser, $label);
		$this->assertCssLoaded($browser, $label);
		$this->assertHttpStatusOk($browser, $label);
	}

	private function assertLogoExists($browser, $pageName): void
	{
		$logoExists = $browser->driver->executeScript(
			"return document.getElementById('logo1') !== null;"
		);
		$this->assertTrue($logoExists, "$pageName: Logo not found");
	}

	private function assertCssLoaded($browser, $pageName): void
	{
		$bodyBackground = $browser->driver->executeScript(
			"return window.getComputedStyle(document.body).background;"
		);
		$this->assertNotEmpty($bodyBackground, "$pageName: CSS not loaded - body has no background style");
	}
}
