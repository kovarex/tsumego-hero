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
		$pageSource = $browser->driver->getPageSource();

		// Verify page loads with correct title
		$this->assertTextContains('Issues', $pageSource);

		// Verify issue is displayed
		$this->assertTextContains('Test issue for index page', $pageSource);

		// Verify tabs are present
		$this->assertTextContains('Opened', $pageSource);
		$this->assertTextContains('Closed', $pageSource);
	}

	public function testIssuesFilterOpened()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Open issue']]]]);
		$browser->get('tsumego-issues?status=opened');
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

	public function testPaginationPage2ShowsCorrectIssues()
	{
		$browser = Browser::instance();
		// Create 25 issues
		$issues = [];
		for ($i = 1; $i <= 25; $i++)
			$issues[] = ['message' => "Paged Issue $i"];

		new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'issues' => $issues]]);
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

	public function testMoveCommentReturnsHtmlForHtmxRequest()
	{
		// Create tsumego with a standalone comment
		 new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'comments' => [['message' => 'Comment to move into issue']]]]);

		// Get the comment ID
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->find('first', ['conditions' => ['message' => 'Comment to move into issue']]);
		$commentId = $comment['TsumegoComment']['id'];
		$tsumegoId = $comment['TsumegoComment']['tsumego_id'];

		// Make htmx POST request to move to new issue
		$this->testAction(
			'/tsumego-issues/move-comment/' . $commentId,
			[
				'method' => 'post',
				'data' => [
					'Comment' => [
						'tsumego_issue_id' => 'new',
						'htmx' => '1',
					],
				],
				'return' => 'contents',
			]
		);

		// Should return HTML, not a redirect
		$response = $this->contents;

		// Verify it returns the section-content HTML
		$this->assertTextContains('comments-section-' . $tsumegoId, $response);
		$this->assertTextContains('Comment to move into issue', $response);
		// Should now be in an issue
		$this->assertTextContains('Issue #', $response);
	}

	public function testMoveCommentToStandaloneReturnsHtmlForHtmx()
	{
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Comment inside issue']]]]);

		// Get the comment ID
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->find('first', ['conditions' => ['message' => 'Comment inside issue']]);
		$commentId = $comment['TsumegoComment']['id'];
		$tsumegoId = $comment['TsumegoComment']['tsumego_id'];

		// Make htmx POST request to make standalone
		$this->testAction(
			'/tsumego-issues/move-comment/' . $commentId,
			[
				'method' => 'post',
				'data' => [
					'Comment' => [
						'tsumego_issue_id' => 'standalone',
						'htmx' => '1',
					],
				],
				'return' => 'contents',
			]
		);

		$response = $this->contents;

		// Should return the section HTML
		$this->assertTextContains('comments-section-' . $tsumegoId, $response);
		$this->assertTextContains('Comment inside issue', $response);
		// Comment should be standalone now (in tsumego-comment--standalone wrapper)
		$this->assertTextContains('tsumego-comment--standalone', $response);
	}
}
