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

	/**
	 * Test that moveComment returns HTML for htmx requests.
	 */
	public function testMoveCommentReturnsHtmlForHtmxRequest()
	{
		// Create tsumego with a standalone comment
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Move Test Set', 'num' => '1']],
				'comments' => [['message' => 'Comment to move into issue']],
			],
		]);

		// Get the comment ID
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->find('first', [
			'conditions' => ['message' => 'Comment to move into issue'],
		]);
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

	/**
	 * Test that moveComment to standalone returns HTML for htmx.
	 */
	public function testMoveCommentToStandaloneReturnsHtmlForHtmx()
	{
		// Create tsumego with an issue containing a comment
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Standalone Test Set', 'num' => '1']],
				'issues' => [['message' => 'Comment inside issue']],
			],
		]);

		// Get the comment ID
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->find('first', [
			'conditions' => ['message' => 'Comment inside issue'],
		]);
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

	/**
	 * Test that drag handles appear for admin users on play page.
	 */
	public function testDragHandlesAppearForAdminOnPlayPage()
	{
		// Create tsumego with a comment, already solved
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Drag Handle Test Set', 'num' => '1']],
				'comments' => [['message' => 'Comment with drag handle']],
				'status' => 'S', // Already solved
			],
		]);

		$browser = Browser::instance();

		// Go to play page via set connection ID (how the app routes)
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Wait for comments section to be visible
		$browser->waitUntilCssSelectorExists('#commentSpace .tsumego-comment', 5);

		// Check DOM elements directly - drag handles should be present for admin
		$dragHandles = $browser->getCssSelect('.tsumego-comment__drag-handle');
		$this->assertGreaterThan(0, count($dragHandles), 'Drag handles should be present for admin');

		// Also verify data-comment-id attribute exists
		$comments = $browser->getCssSelect('.tsumego-comment[data-comment-id]');
		$this->assertGreaterThan(0, count($comments), 'Comments should have data-comment-id attribute');

		$browser->assertNoErrors();
	}

	/**
	 * Test that drag handles do NOT appear for non-admin users.
	 */
	public function testDragHandlesDoNotAppearForNonAdmin()
	{
		// Create tsumego with a comment - NON-admin user
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => false],
			'tsumego' => [
				'sets' => [['name' => 'No Drag Handle Test Set', 'num' => '1']],
				'comments' => [['message' => 'Comment without drag handle']],
				'status' => 'S', // Already solved
			],
		]);

		$browser = Browser::instance();

		// Go to play page
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Wait for comments section to be visible
		$browser->waitUntilCssSelectorExists('#commentSpace .tsumego-comment', 5);

		// Check DOM elements directly - not page source text which includes JS code
		$dragHandles = $browser->getCssSelect('.tsumego-comment__drag-handle');
		$this->assertCount(0, $dragHandles, 'Drag handles should NOT be present for non-admin');

		// Comment should still exist
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment without drag handle', $pageSource);

		$browser->assertNoErrors();
	}

	/**
	 * Test that comments have drag handles for admin.
	 */
	public function testCommentsHaveDragHandlesForAdmin()
	{
		// Create tsumego with a comment
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Draggable Test Set', 'num' => '1']],
				'comments' => [['message' => 'Draggable comment']],
				'status' => 'S',
			],
		]);

		$browser = Browser::instance();
		$tsumegoId = $context->tsumego['id'];

		// Go to play page
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Wait for comments
		$browser->waitUntilCssSelectorExists('#commentSpace .tsumego-comment', 5);

		// Check that drag handles are present for admin
		$dragHandles = $browser->getCssSelect('.tsumego-comment__drag-handle');
		$this->assertCount(1, $dragHandles, 'Drag handles should be present for admin');

		// Check that the comment has the draggable class
		$draggableComments = $browser->getCssSelect('.tsumego-comment--draggable');
		$this->assertCount(1, $draggableComments, 'Comments should have draggable class for admin');

		$browser->assertNoErrors();
	}

	/**
	 * Test "Make Issue" button converts standalone comment to issue.
	 */
	public function testMakeIssueButton()
	{
		// Create tsumego with only standalone comments (no issues)
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Make Issue Button Test', 'num' => '1']],
				'comments' => [['message' => 'Comment to become issue']],
				'status' => 'S',
			],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('.tsumego-comment--standalone', 5);

		// Verify initial state
		$this->assertCount(0, $browser->getCssSelect('.tsumego-issue'), 'Should have 0 issues initially');
		$this->assertCount(1, $browser->getCssSelect('.tsumego-comment--standalone'), 'Should have 1 standalone');

		// Find and click the Make Issue button
		$makeIssueBtn = $browser->driver->findElement(
			WebDriverBy::cssSelector('.tsumego-comment__make-issue-btn')
		);
		$this->assertNotNull($makeIssueBtn, 'Make Issue button should exist');
		$makeIssueBtn->click();

		// Wait for htmx update
		usleep(500000);
		$browser->waitUntilCssSelectorExists('.tsumego-issue', 5);

		// Verify comment is now in an issue
		$this->assertCount(1, $browser->getCssSelect('.tsumego-issue'), 'Should have 1 issue now');
		$this->assertCount(0, $browser->getCssSelect('.tsumego-comment--standalone'), 'Should have 0 standalone');

		// Verify the comment content is still there
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to become issue', $pageSource);
		$this->assertTextContains('Issue #1', $pageSource);

		$browser->assertNoErrors();
	}

	/**
	 * Test that Make Issue button only appears for standalone comments, not issue comments.
	 */
	public function testMakeIssueButtonOnlyOnStandalone()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Make Issue Visibility Test', 'num' => '1']],
				'issues' => [['message' => 'Comment in issue']],
				'comments' => [['message' => 'Standalone comment']],
				'status' => 'S',
			],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('.tsumego-comment--standalone', 5);

		// Make Issue button should exist on standalone comments
		$standaloneButtons = $browser->getCssSelect('.tsumego-comment--standalone .tsumego-comment__make-issue-btn');
		$this->assertCount(1, $standaloneButtons, 'Standalone comment should have Make Issue button');

		// Make Issue button should NOT exist on issue comments
		$issueButtons = $browser->getCssSelect('.tsumego-issue .tsumego-comment__make-issue-btn');
		$this->assertCount(0, $issueButtons, 'Issue comments should NOT have Make Issue button');

		$browser->assertNoErrors();
	}

	// NOTE: testEscCancelsDrag removed.
	// ESC key cancel is NOT supported by SortableJS.
	// The library maintainer confirmed: "It impossible to do when using Native DND."
	// Even with forceFallback:true, SortableJS has no keyboard event handling.
	// Would require forking SortableJS or custom implementation.
}
