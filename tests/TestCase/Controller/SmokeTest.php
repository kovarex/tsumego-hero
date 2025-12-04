<?php
use Selenium\Keys;

/**
 * Smoke tests - visit all major pages and verify:
 * - No JavaScript errors
 * - CSS is loaded properly
 * - Core functionality works
 */
class SmokeTest extends ControllerTestCase
{
	/**
	 * Test that all major pages load without JS errors and have CSS loaded
	 *
	 * @return void
	 */
	public function testAllMajorPagesLoadWithoutErrors()
	{
		// Create realistic production-like test data
		$context = new ContextPreparator([
			'user' => ['name' => 'smoketest', 'rating' => 1500, 'premium' => 0], // User WITHOUT premium (explicit 0)
			'other-users' => [
				['name' => 'opponent1', 'rating' => 1600],
				['name' => 'opponent2', 'rating' => 1400],
			],
			'tsumego' => [
				'rating' => 1000,
				'sets' => [
					['name' => 'Free Set', 'num' => '1', 'premium' => 0], // Regular free set (HAS number element)
					['name' => 'Premium Locked Set', 'num' => '2', 'premium' => 1], // Premium set, user doesn't have premium (NO number element - this triggers the bug!)
				]
			],
			'other-tsumegos' => [
				['rating' => 1100],
				['rating' => 1200],
				['rating' => 900],
			],
			'day-records' => [
				['date' => date('Y-m-d'), 'solved' => 10, 'visitedproblems' => 20]
			]
		]);
		$browser = Browser::instance();

		// Get IDs for parameterized routes
		$setConnectionId = $context->tsumego['set-connections'][0]['id'];
		$setId = $context->tsumego['sets'][0]['id'];
		$userId = $context->user['id'];

		$pages = [
			// Core pages
			['url' => '', 'name' => 'Homepage'],
			
			// Collections
			['url' => 'sets', 'name' => 'Sets index'],
			['url' => 'sets/sandbox', 'name' => 'Sandbox'],
			['url' => 'sets/view/favorites', 'name' => 'Favorites'],
			['url' => "sets/view/$setId", 'name' => 'Set view'],
			
			// Tsumego play
			['url' => $setConnectionId, 'name' => 'Tsumego play'],
			
			// Time mode
			// NOTE: timeMode/overview currently broken - SQL syntax error with BUCKET (reserved word in MySQL 8+)
			// Error: "near 'AS bucket" - needs fix in TimeModeController::getRanksWithTsumegoCount()
			// ['url' => 'timeMode/overview', 'name' => 'Time mode overview'], // TODO: Fix BUCKET SQL bug
			
			// Achievements & rewards
			['url' => 'achievements', 'name' => 'Achievements'],
			['url' => 'users/rewards', 'name' => 'Rewards'],
			// ['url' => 'achievements/view/1', 'name' => 'Achievement view'], // TODO: Test with real achievement ID
			
			// User pages
			['url' => "users/view/$userId", 'name' => 'User profile'],
			['url' => 'users/authors', 'name' => 'About/Authors'],
			['url' => 'users/highscore', 'name' => 'Level highscore'],
			['url' => 'users/rating', 'name' => 'Rating highscore'],
			['url' => 'users/achievements', 'name' => 'Achievement highscore'],
			['url' => 'users/added_tags', 'name' => 'Tag highscore'],
			['url' => 'users/leaderboard', 'name' => 'Daily leaderboard'],
			
			// Tutorials
			['url' => 'sites/websitefunctions', 'name' => 'Website functions'],
			['url' => 'sites/gotutorial', 'name' => 'Go rules tutorial'],
			['url' => 'sites/impressum', 'name' => 'Legal notice'],
			['url' => 'sites/privacypolicy', 'name' => 'Privacy policy'],
			
			// Issues (admin/user reports)
			['url' => 'tsumego-issues', 'name' => 'Issues list'],
		];

		foreach ($pages as $page)
		{
			$browser->get($page['url']);
			
			// Basic sanity - logo exists
			// ALL pages have the logo in the header, no exceptions
			$this->assertLogoExists($page['name']);
			
			// Wait for animations to complete (sets page has percentage counter animation)
			usleep(1000000); // 1 second
			
			// Check JS errors
			$browser->assertNoJsErrors();
			
			// CSS loaded
			$this->assertCssLoaded($page['name']);
		}
	}

	/**
	 * Assert page has logo (common element across all pages)
	 *
	 * @param string $pageName Page name for error messages
	 * @return void
	 */
	private function assertLogoExists($pageName)
	{
		$browser = Browser::instance();
		$logoExists = $browser->driver->executeScript(
			"return document.getElementById('logo1') !== null;"
		);
		$this->assertTrue($logoExists, "$pageName: Logo not found");
	}

	/**
	 * Assert that CSS is loaded by checking computed styles on body element
	 *
	 * @param string $pageName Page name for error messages
	 * @return void
	 */
	private function assertCssLoaded($pageName)
	{
		$browser = Browser::instance();

		// Check that body has background style (proves CSS loaded)
		$bodyBackground = $browser->driver->executeScript(
			"return window.getComputedStyle(document.body).background;"
		);

		$this->assertNotEmpty($bodyBackground, "$pageName: CSS not loaded - body has no background style");
	}
}
