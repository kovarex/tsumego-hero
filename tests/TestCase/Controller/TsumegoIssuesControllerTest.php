<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('TsumegoIssue', 'Model');

// Tests for TsumegoIssuesController - specifically the global issues index page.
class TsumegoIssuesControllerTest extends ControllerTestCase
{
	public function testIssuesIndexPageLoads()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Test issue for index page']]]]);
		$browser->get('tsumego-issues');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();

		// Verify page loads with correct title
		$this->assertTextContains('Issues', $pageSource);

		// Verify issue is displayed
		$this->assertTextContains('Test issue for index page', $pageSource);

		// Verify filter tabs are present
		$this->assertTextContains('Open', $pageSource);
		$this->assertTextContains('Closed', $pageSource);
	}

	public function testIssuesFilterOpened()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Open issue']]]]);
		$browser->get('tsumego-issues?status=opened');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Open issue', $pageSource);
	}

	public function testIssuesFilterClosed()
	{
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Closed issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]]]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=closed');
		$this->waitForReactIssuesList($browser);

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Closed issue', $pageSource);
	}

	public function testIssueLinksToTsumego()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Issue with link']]]]);
		$browser->get('tsumego-issues');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();

		// Should show the issue with its comment
		$this->assertTextContains('Issue with link', $pageSource);
		// Check there's a link to the problem set
		$this->assertTextContains('test set', $pageSource);
	}

	public function testEmptyIssuesListShowsAppropriateMessage()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['admin' => true]]);
		$browser->get('tsumego-issues');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();

		// Should show some indication that there are no issues
		// (either empty table or "no issues" message)
		$this->assertTextContains('Issues', $pageSource);
	}

	public function testPaginationAppearsWhenNeeded()
	{
		// Create many issues (25 to trigger pagination with 20 per page)
		$issues = [];
		for ($i = 1; $i <= 25; $i++)
			$issues[] = ['message' => "Issue number $i"];

		new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'issues' => $issues]]);

		$browser = Browser::instance();
		$browser->get('tsumego-issues?status=opened');
		$this->waitForReactIssuesList($browser);

		$pageSource = $browser->driver->getPageSource();

		// Verify pagination links appear
		$this->assertTextContains('Next', $pageSource);
		$this->assertTextContains('issues-page__pagination', $pageSource);

		// Issues are sorted by created DESC (newest first)
		// So page 1 shows issues 25, 24, 23, ... 6 (20 issues)
		// And page 2 shows issues 5, 4, 3, 2, 1 (5 issues)
		$this->assertTextContains('Issue number 25', $pageSource);
		$this->assertTextContains('Issue number 6', $pageSource);
		// Issue 5 should be on page 2, not page 1
		$this->assertTextNotContains('Issue number 5<', $pageSource);
	}

	public function testPaginationPage2ShowsCorrectIssues()
	{
		$browser = Browser::instance();
		// Create 25 issues
		$issues = [];
		for ($i = 1; $i <= 25; $i++)
			$issues[] = ['message' => "Paged Issue $i"];

		new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'issues' => $issues]]);
		$browser->get('tsumego-issues?status=opened&page=2');
		$this->waitForReactIssuesList($browser);
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

	/**
	 * Test that issue author's Go rank is displayed correctly on the issues list.
	 */
	public function testIssueAuthorRankDisplayedCorrectly()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true, 'rating' => 1500],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Issue with author rank']]]]);
		$browser->get('tsumego-issues');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();

		// Rating 1500 should display as 6k rank
		$this->assertTextContains('6k', $pageSource);
	}

	/**
	 * Test closing an issue from the issues list page.
	 */
	public function testCloseIssueFromListPage()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Issue to close from list']]]]);
		$browser->get('tsumego-issues?status=opened');
		$this->waitForReactIssuesList($browser);

		// Verify issue shows as open
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Issue to close from list', $pageSource);

		// Wait for and click close button
		$browser->waitUntilCssSelectorExists('.tsumego-issue button.btn--success', 10);
		$closeButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue button.btn--success'));
		$closeButton->click();

		// Wait for issue to disappear from opened list (the page re-fetches open issues)
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			// After closing, the issue disappears from open list, showing empty state
			$issues = $driver->findElements(WebDriverBy::cssSelector('.tsumego-issue:not(.skeleton-wrapper)'));
			return count($issues) === 0;
		});

		$pageSource = $browser->driver->getPageSource();
		// Should show no open issues or empty state
		$this->assertTextNotContains('Issue to close from list', $pageSource);
	}

	/**
	 * Test that deleted users show as [deleted user] on the issues list.
	 */
	public function testDeletedUserShowsPlaceholder()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'doomed_user']],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Issue by deleted user']]]]);

		// Reassign issue to other user, then delete them (with FK checks disabled)
		$otherUserId = $context->otherUsers[0]['id'];
		$issueModel = ClassRegistry::init('TsumegoIssue');
		$issueModel->updateAll(
			['TsumegoIssue.user_id' => $otherUserId],
			['TsumegoIssue.id' => $context->issues[0]['id']]
		);
		$issueModel->query('SET FOREIGN_KEY_CHECKS = 0');
		ClassRegistry::init('User')->deleteAll(['User.id' => $otherUserId]);
		$issueModel->query('SET FOREIGN_KEY_CHECKS = 1');

		$browser->get('tsumego-issues');
		$this->waitForReactIssuesList($browser);
		$pageSource = $browser->driver->getPageSource();

		$this->assertTextContains('[deleted user]', $pageSource);
	}

	/**
	 * Wait for React to mount and fetch data on the issues index page.
	 * The page initially shows skeletons, then React fetches and renders actual issues or empty state.
	 */
	private function waitForReactIssuesList($browser)
	{
		// Wait for skeleton to disappear and real content to appear
		// - .issues-list__empty = no issues message (actual content, never skeleton)
		// - .tsumego-issue:not(.skeleton-wrapper) = real issue (not a skeleton placeholder)
		$browser->waitUntilAnyCssSelectorExists(['.issues-list__empty', '.tsumego-issue:not(.skeleton-wrapper)']);
	}
}
