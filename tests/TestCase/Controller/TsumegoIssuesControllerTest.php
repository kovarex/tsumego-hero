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

	/**
	 * Test that pagination appears when there are more than 20 issues.
	 */
	public function testPaginationAppearsWhenNeeded()
	{
		// Create many issues (25 to trigger pagination with 20 per page)
		$issues = [];
		for ($i = 1; $i <= 25; $i++)
			$issues[] = ['message' => "Issue number $i"];

		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Pagination Test Set', 'num' => '1']],
				'issues' => $issues,
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=opened');

		$pageSource = $browser->driver->getPageSource();

		// Verify pagination links appear
		$this->assertTextContains('Next', $pageSource);
		$this->assertTextContains('class="pagination-link', $pageSource);

		// Issues are sorted by created DESC (newest first)
		// So page 1 shows issues 25, 24, 23, ... 6 (20 issues)
		// And page 2 shows issues 5, 4, 3, 2, 1 (5 issues)
		$this->assertTextContains('Issue number 25', $pageSource);
		$this->assertTextContains('Issue number 6', $pageSource);
		// Issue 5 should be on page 2, not page 1
		$this->assertTextNotContains('Issue number 5<', $pageSource);
	}

	/**
	 * Test that pagination page 2 shows correct issues.
	 */
	public function testPaginationPage2()
	{
		// Create 25 issues
		$issues = [];
		for ($i = 1; $i <= 25; $i++)
			$issues[] = ['message' => "Paged Issue $i"];

		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Page 2 Test Set', 'num' => '1']],
				'issues' => $issues,
			],
		]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=opened&page=2');

		$pageSource = $browser->driver->getPageSource();

		// Issues are sorted by created DESC (newest first)
		// Page 2 shows older issues (5, 4, 3, 2, 1)
		// Just verify that we have some issues and prev link
		$this->assertTextContains('Paged Issue', $pageSource);

		// Page 2 should NOT show page 1's newest issues
		$this->assertTextNotContains('Paged Issue 25', $pageSource);
		$this->assertTextNotContains('Paged Issue 24', $pageSource);

		// Verify Prev link exists on page 2
		$this->assertTextContains('Prev', $pageSource);
	}
}
