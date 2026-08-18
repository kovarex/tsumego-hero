<?php

use PHPUnitRetry\RetryTrait;
use Selenium\Keys;

App::uses('TimeModeUtil', 'Utility');

/**
 * Smoke tests - visit all major pages and verify:
 * - No JavaScript errors
 * - CSS is loaded properly
 * - Core functionality works
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SmokeTest extends ControllerTestCase
{
	use RetryTrait;
	public function testAllMajorPagesLoadWithoutErrors()
	{
		// Create realistic production-like test data
		$context = new ContextPreparator([
			'user' => ['name' => 'smoketest', 'rating' => 1500, 'admin' => true],
			'other-users' => [
				['name' => 'opponent1', 'rating' => 1600],
				['name' => 'opponent2', 'rating' => 1400],
				// Smoke test roles: premium and regular (non-premium) logged-in users
				['name' => 'premiumuser', 'rating' => 1500, 'premium' => 1],
				['name' => 'regularuser', 'rating' => 1500],
			],
			'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'Free Set', 'num' => '1']]],
			// Rating=1500 tsumego needed so ratingMode finds it for user with rating 1500 (range ±240)
			'tsumegos' => [['rating' => 1100], ['rating' => 1200], ['rating' => 1500, 'sets' => [['name' => 'Rating Set', 'num' => 1]]]],
			'tags' => [['name' => 'capture', 'approved' => 1]],
			'day-records' => [['date' => date('Y-m-d')]],
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

		// The test scenarios (roles) and the user that plays each one; null = logged out.
		// No hierarchy is assumed between them - each page declares its own set.
		$roles = [
			'admin' => $userId,
			'premium' => ContextPreparator::getUserIdFromName('premiumuser'),
			'regular' => ContextPreparator::getUserIdFromName('regularuser'),
			'anonymous' => null,
		];
		// Common sets derived from $roles so they cannot drift when roles change:
		// $everyone = every scenario, $loggedIn = every scenario that logs a user in.
		$everyone = array_keys($roles);
		$loggedIn = array_keys(array_filter($roles, fn($roleUserId) => $roleUserId !== null));

		// auth - the test scenarios that may view the page. Each page declares its
		// own array of scenarios (no hierarchy assumed); $everyone / $loggedIn are
		// convenience sets for the common cases, e.g. ['admin', 'premium'] for the sandbox.
		$pages = [
			// Core pages
			['url' => '', 'name' => 'Homepage', 'auth' => $everyone],

			// Collections
			['url' => 'sets', 'name' => 'Sets index', 'auth' => $everyone],
			['url' => "sets/view/$setId", 'name' => 'Set view', 'auth' => $everyone],
			['url' => 'sets/view/favorites', 'name' => 'Favorites', 'auth' => $loggedIn],
			['url' => 'sets/sandbox', 'name' => 'Sandbox/premium sets', 'auth' => ['admin', 'premium']],

			// SGF upload history (admin-only)
			['url' => "sgfs/view/$tsumegoId", 'name' => 'SGF upload history', 'auth' => ['admin']],

			// Tsumego play
			['url' => $setConnectionId, 'name' => 'Tsumego play', 'auth' => $everyone],
			['url' => 'ratingMode', 'name' => 'Rating mode play', 'auth' => $loggedIn],
			['url' => 'timeMode/overview', 'name' => 'Time mode overview', 'auth' => $loggedIn],
			['url' => 'timeMode/play', 'name' => 'Time mode play', 'auth' => $loggedIn],
			['url' => "timeMode/result/$finishedSessionId", 'name' => 'Time mode result', 'auth' => $loggedIn],

			// Achievements
			['url' => 'achievements', 'name' => 'Achievements', 'auth' => $everyone],
			['url' => 'achievements/view/1', 'name' => 'Achievement detail', 'auth' => $everyone],
			['url' => "achievements/user/$userId", 'name' => 'User achievements', 'auth' => $everyone],

			// User pages
			['url' => "users/view/$userId", 'name' => 'User profile', 'auth' => $everyone],
			['url' => "tags/user/$userId", 'name' => 'User contributions', 'auth' => $everyone],
			['url' => "users/solveHistory/$userId", 'name' => 'Solve history', 'auth' => $everyone],
			['url' => 'users/authors', 'name' => 'About/Authors', 'auth' => $everyone],
			['url' => 'users/highscore', 'name' => 'Level highscore', 'auth' => $everyone],
			['url' => 'users/time_mode', 'name' => 'Time mode highscore', 'auth' => $everyone],
			['url' => 'users/rating', 'name' => 'Rating highscore', 'auth' => $everyone],
			['url' => 'users/achievements', 'name' => 'Achievement highscore', 'auth' => $everyone],
			['url' => 'users/added_tags', 'name' => 'Tag highscore', 'auth' => $everyone],
			['url' => 'users/leaderboard', 'name' => 'Daily leaderboard', 'auth' => $everyone],

			// Auth pages (anonymous-only forms)
			['url' => 'users/login', 'name' => 'Login form', 'auth' => ['anonymous']],
			['url' => 'users/add', 'name' => 'Registration form', 'auth' => ['anonymous']],
			['url' => 'users/resetpassword', 'name' => 'Reset password form', 'auth' => ['anonymous']],
			['url' => 'users/newpassword/invalid-checksum', 'name' => 'New password form (invalid link)', 'auth' => ['anonymous']],

			// User account pages
			['url' => 'users/delete_account', 'name' => 'Delete account form', 'auth' => $loggedIn],

			// Comments (requires login)
			['url' => 'comments', 'name' => 'Comments', 'auth' => $loggedIn],

			// Tags
			['url' => 'tags/add', 'name' => 'Add tag form', 'auth' => $everyone],
			['url' => "tags/view/$tagId", 'name' => 'Tag detail', 'auth' => $everyone],

			// Tutorials
			['url' => 'sites/websitefunctions', 'name' => 'Website functions', 'auth' => $everyone],
			['url' => 'sites/gotutorial', 'name' => 'Go rules tutorial', 'auth' => $everyone],
			['url' => 'sites/impressum', 'name' => 'Legal notice', 'auth' => $everyone],
			['url' => 'sites/privacypolicy', 'name' => 'Privacy policy', 'auth' => $everyone],
			['url' => 'sites/about', 'name' => 'About/Credits', 'auth' => $everyone],

			// Issues
			['url' => 'tsumego-issues', 'name' => 'Issues list', 'auth' => $everyone],
		];

		// One pass per scenario; each pass visits every page that lists that scenario.
		foreach ($roles as $role => $roleUserId)
		{
			$this->switchUser($browser, $roleUserId);
			foreach (array_filter($pages, fn($p) => in_array($role, $p['auth'], true)) as $page)
				$this->assertPageLoadsOk($browser, $page['url'], $page['name'] . " ($role)", $roleUserId !== null);
		}
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

	/**
	 * Switch the PHP-side auth identity and drop stale browser cookies so the
	 * next Browser::get() injects the new user. Null userId logs out (anonymous).
	 */
	private function switchUser($browser, ?int $userId): void
	{
		Auth::logout();
		if ($userId !== null)
		{
			$_COOKIE['hackedLoggedInUserID'] = (string) $userId;
			$_COOKIE['disable-achievements'] = true;
			Auth::init();
		}
		else
			unset($_COOKIE['hackedLoggedInUserID']);
		$browser->driver->manage()->deleteAllCookies();
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
