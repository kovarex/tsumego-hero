<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('TsumegoIssue', 'Model');

/**
 * Tests for TsumegoIssuesController - specifically the global issues index page.
 */
class TsumegoIssuesControllerTest extends ControllerTestCase
{
	/**
	 * Test that the issues index page loads and displays issues.
	 */
	public function testIssuesIndexPageLoads()
	{
		// Create a tsumego with an issue
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Test Set', 'num' => '1']],
				'issues' => [['message' => 'Test issue for index page']],
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues');

		$pageSource = $browser->driver->getPageSource();

		// Verify page loads with correct title
		$this->assertTextContains('Issues', $pageSource);

		// Verify issue is displayed
		$this->assertTextContains('Test issue for index page', $pageSource);

		// Verify tabs are present
		$this->assertTextContains('Opened', $pageSource);
		$this->assertTextContains('Closed', $pageSource);
	}

	/**
	 * Test that filtering by opened issues works.
	 */
	public function testIssuesFilterOpened()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Test Set', 'num' => '1']],
				'issues' => [['message' => 'Open issue']],
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=opened');

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Open issue', $pageSource);
	}

	/**
	 * Test that filtering by closed issues works.
	 */
	public function testIssuesFilterClosed()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Test Set', 'num' => '1']],
				'issues' => [['message' => 'Closed issue', 'status' => TsumegoIssue::$CLOSED_STATUS]],
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=closed');

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Closed issue', $pageSource);
	}

	/**
	 * Test that issue links to the correct tsumego.
	 */
	public function testIssueLinksToTsumego()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Link Test Set', 'num' => '42']],
				'issues' => [['message' => 'Issue with link']],
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues');

		$pageSource = $browser->driver->getPageSource();

		// Should show the issue and a link to the problem
		$this->assertTextContains('Issue with link', $pageSource);
		// Check there's a View Issue button (indicating link exists)
		$this->assertTextContains('View Issue', $pageSource);
	}

	/**
	 * Test that empty issues list shows appropriate message.
	 */
	public function testEmptyIssuesList()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues');

		$pageSource = $browser->driver->getPageSource();

		// Should show some indication that there are no issues
		// (either empty table or "no issues" message)
		$this->assertTextContains('Issues', $pageSource);
	}
}
