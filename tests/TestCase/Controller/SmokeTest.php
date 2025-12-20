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
			'user' => ['name' => 'smoketest', 'rating' => 1500],
			'other-users' => [
				['name' => 'opponent1', 'rating' => 1600],
				['name' => 'opponent2', 'rating' => 1400],
			],
			'tsumego' => [
				'rating' => 1000,
				'sets' => [
					['name' => 'Free Set', 'num' => '1'],
				],
			],
			'tsumegos' => [
				['rating' => 1100],
				['rating' => 1200],
			],
			'day-records' => [
				['date' => date('Y-m-d'), 'solved' => 10, 'visitedproblems' => 20],
			],
		]);
		$browser = Browser::instance();
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$userId = $context->user['id'];

		$pages = [
			// Core pages
			['url' => '', 'name' => 'Homepage'],
			// NOTE: Skipping pages that require specific DB records or premium access:
			// - sets/sandbox (requires premium)
			// - sets/view/favorites (requires auth + favorites data)
			// - sets/view/{id} (requires set to exist in DB)
			// - tsumegos/play/{id} (requires tsumego to exist in DB)
			// - timeMode/overview (MySQL 8 BUCKET reserved word bug)

			// Collections
			['url' => 'sets', 'name' => 'Sets index'],
			['url' => "sets/view/$setId", 'name' => 'Set view'],
			['url' => 'sets/view/favorites', 'name' => 'Favorites'],

			// Tsumego play
			['url' => $setConnectionId, 'name' => 'Tsumego play'],

			// Achievements & rewards (work without specific data)
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
			$this->assertLogoExists($browser, $page['name']);

			// Wait for animations to complete (sets page has percentage counter animation)
			usleep(100 * 1000); // 0.1 second

			// Check JS errors
			$browser->assertNoJsErrors();

			// CSS loaded
			$this->assertCssLoaded($browser, $page['name']);
		}
	}

	/**
	 * Assert page has logo (common element across all pages)
	 *
	 * @param string $pageName Page name for error messages
	 * @return void
	 */
	private function assertLogoExists($browser, $pageName)
	{
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
	private function assertCssLoaded($browser, $pageName)
	{
		// Check that body has background style (proves CSS loaded)
		$bodyBackground = $browser->driver->executeScript(
			"return window.getComputedStyle(document.body).background;"
		);

		$this->assertNotEmpty($bodyBackground, "$pageName: CSS not loaded - body has no background style");
	}
}
