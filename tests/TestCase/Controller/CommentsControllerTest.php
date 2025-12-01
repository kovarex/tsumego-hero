<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

App::uses('TsumegoIssue', 'Model');

class CommentsControllerTest extends ControllerTestCase
{
	/**
	 * Test that a logged-in user can add a comment to a solved tsumego.
	 */
	public function testAddComment()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default)

		// The comment form is now directly in the comments section
		$messageField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__form textarea'));
		$messageField->click();
		$messageField->sendKeys("My first comment");
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__form button[type="submit"]'));
		$this->assertTrue($submitButton->isDisplayed());
		$this->assertTrue($submitButton->isEnabled());
		$submitButton->click();

		usleep(1500 * 1000);

		// Verify comment appears in the comments list
		$browser->get('comments');
		$this->assertTextContains('My first comment', $browser->driver->getPageSource());
	}

	/**
	 * Test that comments are hidden until the problem is solved.
	 */
	public function testDontShowCommentsUntilProblemIsSolved()
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']], 'comments' => [['message' => 'spoiler']]]]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$this->assertFalse($browser->getCssSelect('#commentSpace')[0]->isDisplayed());
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')");
		$this->assertTrue($browser->getCssSelect('#commentSpace')[0]->isDisplayed());
	}

	/**
	 * Test that comments are shown immediately on already-solved tsumegos.
	 */
	public function testShowCommentOnVisitingAlreadySolvedTsumego()
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']], 'comments' => [['message' => 'spoiler']], 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$this->assertTrue($browser->getCssSelect('#commentSpace')[0]->isDisplayed());
	}

	/**
	 * Test that the comments section can be collapsed/expanded via tabs.
	 */
	public function testCommentsToggle()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'comments' => [['message' => 'test comment']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Content should be HIDDEN by default (no tab selected)
		$content = $browser->driver->findElement(WebDriverBy::id('msg2x'));
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden by default');

		// Click COMMENTS tab to EXPAND
		$commentsTab = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__tab[data-filter="open"]'));
		$commentsTab->click();
		usleep(300 * 1000); // Wait for animation
		$this->assertTrue($content->isDisplayed(), 'Comments should be visible after clicking COMMENTS tab');
		$this->assertTrue($commentsTab->getAttribute('class') === 'tsumego-comments__tab active' || strpos($commentsTab->getAttribute('class'), 'active') !== false, 'COMMENTS tab should be active');

		// Click same tab again to COLLAPSE
		$commentsTab->click();
		usleep(300 * 1000);
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden after clicking COMMENTS tab again');
	}

	/**
	 * Test that issues are displayed correctly when prepopulated via ContextPreparator.
	 */
	public function testIssueDisplayed()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [['message' => 'Test issue to display']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		$pageSource = $browser->driver->getPageSource();

		// Issue should be displayed
		$this->assertTextContains('Issue #1', $pageSource);
		$this->assertTextContains('Test issue to display', $pageSource);
	}

	/**
	 * Test that coordinate links in comments are styled and have proper class.
	 */
	public function testCoordinateHighlight()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'comments' => [['message' => 'Play at C3 for the solution']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default)

		// Find the coordinate span
		$coordSpan = $browser->driver->findElement(WebDriverBy::cssSelector('.go-coord'));
		$this->assertNotNull($coordSpan);
		$this->assertEquals('C3', $coordSpan->getText());
	}

	/**
	 * Test that a user can reply to an issue.
	 */
	public function testReplyToIssue()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [['message' => 'Original issue']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Click Reply button to show the reply form
		$replyButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue__reply-btn'));
		$replyButton->click();
		usleep(200 * 1000); // Wait for form to appear

		// Fill in the reply
		$replyField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue__reply-form textarea'));
		$replyField->sendKeys("My reply to this issue");

		// Submit the reply
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue__reply-form button[type="submit"]'));
		$submitButton->click();

		// Wait for htmx to process the response
		usleep(1500 * 1000);

		// Verify reply appears on the page after reload
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('My reply to this issue', $pageSource);
	}

	/**
	 * Test that a user can delete their own comment.
	 */
	public function testDeleteOwnComment()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'comments' => [['message' => 'Comment to delete']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Verify comment is visible
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to delete', $pageSource);

		// Override confirm BEFORE htmx tries to use it (htmx uses window.confirm)
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find and click the Delete button
		$deleteButton = $browser->driver->findElement(WebDriverBy::cssSelector('.deleteComment'));
		$deleteButton->click();

		// Wait for htmx to process the delete request
		usleep(1500 * 1000);

		// Verify comment is no longer visible (reload to confirm it's deleted)
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextNotContains('Comment to delete', $pageSource);
	}

	/**
	 * Test deleting the last comment in an issue removes the entire issue via htmx.
	 *
	 * When the last comment in an issue is deleted, the issue itself should be
	 * removed from the DOM immediately (not just after page refresh).
	 */
	public function testDeleteLastCommentInIssueRemovesIssue()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [['message' => 'This is the only comment in this issue']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Verify issue and comment are visible
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('This is the only comment in this issue', $pageSource);
		$this->assertTextContains('tsumego-issue', $pageSource); // Issue container class

		// Override confirm for htmx
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find the delete button for the comment inside the issue
		$deleteButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue .deleteComment'));
		$deleteButton->click();

		// Wait for htmx to process the delete request
		usleep(1500 * 1000);

		// Verify issue is immediately gone from DOM (without reload)
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextNotContains('This is the only comment in this issue', $pageSource);

		// Also verify the issue container is gone
		$issueElements = $browser->driver->findElements(WebDriverBy::cssSelector('.tsumego-issue'));
		$this->assertEmpty($issueElements, 'Issue element should be removed from DOM after deleting last comment');
	}

	/**
	 * Test that comment counts update via htmx when deleting a comment.
	 *
	 * The counts in the section header and tabs should update immediately after deleting.
	 */
	public function testDeleteCommentUpdatesCountsViaHtmx()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [['message' => 'Issue comment 1']],
				'comments' => [['message' => 'Standalone comment 1']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Verify comments section is visible
		$browser->idExists('msg2x');

		// Verify initial counts: 1 issue + 1 standalone = 2 total
		// COMMENTS tab shows comments + open issues, CLOSED ISSUES tab shows closed issues
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('COMMENTS (1 + 1 open issue)', $pageSource, 'Should show COMMENTS with open issue count initially');
		$this->assertTextContains('CLOSED ISSUES (0)', $pageSource, 'Should show CLOSED ISSUES (0) initially');

		// Override confirm for htmx
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Delete the standalone comment
		$deleteButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comment--standalone .deleteComment'));
		$deleteButton->click();

		// Wait for htmx to process
		usleep(1500 * 1000);

		// Verify counts updated: should now show 0 comments + 1 open issue
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('COMMENTS (0 + 1 open issue)', $pageSource, 'Should show COMMENTS with 0 comments after deleting');
		$this->assertTextContains('CLOSED ISSUES (0)', $pageSource, 'Should still show CLOSED ISSUES (0)');
	}

	/**
	 * Test that closing an issue updates the tab counts via htmx.
	 *
	 * When an issue is closed on the play page, the COMMENTS and CLOSED ISSUES counts should update.
	 */
	public function testCloseIssueUpdatesOpenCountBadge()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'test set', 'num' => '1']],
				'issues' => [['message' => 'Open issue comment']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Verify comments section is visible
		$browser->idExists('msg2x');

		// Verify initial counts: 0 comments + 1 open issue in COMMENTS tab, 0 in CLOSED ISSUES tab
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('COMMENTS (0 + 1 open issue)', $pageSource, 'Should show COMMENTS with 1 open issue initially');
		$this->assertTextContains('CLOSED ISSUES (0)', $pageSource, 'Should show CLOSED ISSUES (0) initially');

		// Click close button on the issue
		$closeButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue button.btn--success'));
		$closeButton->click();

		// Wait for htmx to process
		usleep(1500 * 1000);

		// Verify counts updated: 0 comments, 0 open issues in COMMENTS, 1 in CLOSED ISSUES
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('COMMENTS (0)', $pageSource, 'Should show COMMENTS (0) after closing');
		$this->assertTextContains('CLOSED ISSUES (1)', $pageSource, 'Should show CLOSED ISSUES (1) after closing');
		// The issue status should now show "Closed"
		$this->assertTextContains('Closed', $pageSource, 'Issue should show Closed status');
	}

	/**
	 * Test that creating an issue via "Report as issue" checkbox works via htmx.
	 *
	 * When user checks "Report as issue" and submits, the issue should appear
	 * without page reload and counts should update.
	 */
	public function testCreateIssueViaCheckbox()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'test set', 'num' => '1']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Expand comments section (hidden by default for non-admins)

		// Initially there should be no issue DOM elements
		// Note: We check for .tsumego-issue elements, not "Issue #" text
		// because the page source includes JS template strings with "Issue #"
		$issues = $browser->getCssSelect('.tsumego-issue');
		$this->assertCount(0, $issues, 'Should have no issue elements initially');

		// Check the "Report as issue" checkbox
		$checkbox = $browser->driver->findElement(WebDriverBy::id('reportIssueCheckbox-tsumegoCommentForm'));
		$checkbox->click();

		// Fill in the message
		$textarea = $browser->driver->findElement(WebDriverBy::id('commentMessage-tsumegoCommentForm'));
		$textarea->sendKeys('This is a test issue report');

		// Submit the form
		$submitButton = $browser->driver->findElement(WebDriverBy::id('submitBtn-tsumegoCommentForm'));
		$submitButton->click();

		// Wait for htmx to process
		usleep(2000 * 1000);

		// Verify issue appears without page reload - check DOM element
		$issues = $browser->getCssSelect('.tsumego-issue');
		$this->assertCount(1, $issues, 'Issue element should appear after submission');

		// Verify the text content
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Issue #1', $pageSource, 'Issue should appear after submission');
		$this->assertTextContains('This is a test issue report', $pageSource, 'Issue message should appear');
		$this->assertTextContains('COMMENTS (0 + 1 open issue)', $pageSource, 'COMMENTS count should update with open issue');
		$this->assertTextContains('CLOSED ISSUES (0)', $pageSource, 'CLOSED ISSUES count should stay at 0');
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
		$this->expandComments($browser);

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
		$this->expandComments($browser);

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
		$this->expandComments($browser);

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
		$this->expandComments($browser);
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
		$this->expandComments($browser);
		$browser->waitUntilCssSelectorExists('.tsumego-comment--standalone', 5);

		// Make Issue button should exist on standalone comments
		$standaloneButtons = $browser->getCssSelect('.tsumego-comment--standalone .tsumego-comment__make-issue-btn');
		$this->assertCount(1, $standaloneButtons, 'Standalone comment should have Make Issue button');

		// Make Issue button should NOT exist on issue comments
		$issueButtons = $browser->getCssSelect('.tsumego-issue .tsumego-comment__make-issue-btn');
		$this->assertCount(0, $issueButtons, 'Issue comments should NOT have Make Issue button');

		$browser->assertNoErrors();
	}

	/**
	 * Test that filter state (COMMENTS tab) is preserved when replying to an issue via htmx.
	 *
	 * When viewing comments tab (open issues + comments), adding a reply to an open issue
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterReplyToOpenIssue()
	{
		// Setup: Create tsumego with one open issue AND one closed issue
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS],
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Click COMMENTS tab (shows open issues + comments)

		// Verify: Open issue is visible
		$openIssues = $browser->getCssSelect('.tsumego-issue--opened');
		$this->assertCount(1, $openIssues, 'Should have 1 open issue visible');
		$this->assertTrue($openIssues[0]->isDisplayed(), 'Open issue should be visible');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden');

		// Click Reply on the open issue
		$replyButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-btn'));
		$replyButton->click();
		usleep(200 * 1000);

		// Fill in and submit the reply
		$replyField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form textarea'));
		$replyField->sendKeys("Reply to open issue");
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]'));
		$submitButton->click();
		usleep(1500 * 1000); // Wait for htmx response and idiomorph

		// After htmx morph: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after morph');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after morph');

		// And open issue should still be visible
		$openIssuesAfter = $browser->getCssSelect('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after morph');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after morph');
	}

	/**
	 * Test that filter state is preserved when adding a standalone comment via htmx.
	 *
	 * When viewing comments tab with closed issues present, adding a new standalone comment
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterAddingStandaloneComment()
	{
		// Setup: Create tsumego with a standalone comment AND a closed issue
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'comments' => [['message' => 'Existing standalone comment']],
				'issues' => [
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Click COMMENTS tab (shows comments, hides closed issues)

		// Verify: Standalone comment is visible
		$standaloneComments = $browser->getCssSelect('.tsumego-comment--standalone');
		$this->assertCount(1, $standaloneComments, 'Should have 1 standalone comment visible');
		$this->assertTrue($standaloneComments[0]->isDisplayed(), 'Standalone comment should be visible');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden');

		// Add a new standalone comment via the form (use main form, not reply forms)
		$messageField = $browser->driver->findElement(WebDriverBy::id('commentMessage-tsumegoCommentForm'));
		$messageField->click();
		$messageField->sendKeys("New standalone comment");
		$submitButton = $browser->driver->findElement(WebDriverBy::id('submitBtn-tsumegoCommentForm'));
		$submitButton->click();
		usleep(1500 * 1000); // Wait for htmx response and idiomorph

		// After htmx morph: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after morph');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after adding comment');

		// New comment should be visible
		$standaloneCommentsAfter = $browser->getCssSelect('.tsumego-comment--standalone');
		$this->assertCount(2, $standaloneCommentsAfter, 'Should have 2 standalone comments after adding');
	}

	/**
	 * Test that filter state is preserved when deleting a comment from an open issue via htmx.
	 *
	 * When viewing comments tab with closed issues present, deleting a comment from an open issue
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterDeletingCommentFromOpenIssue()
	{
		// Setup: Create tsumego with an open issue with 2 comments AND a closed issue
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true], // Admin can delete any comment
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Click COMMENTS tab (shows comments, hides closed issues)

		// Add a second comment to the open issue so we can delete one
		$replyButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-btn'));
		$replyButton->click();
		usleep(200 * 1000);
		$replyField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form textarea'));
		$replyField->sendKeys("Extra comment to delete");
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]'));
		$submitButton->click();
		usleep(1500 * 1000);

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden before delete');

		// Override confirm BEFORE deleting
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find and click delete on one of the comments in the open issue
		$deleteButtons = $browser->getCssSelect('.tsumego-issue--opened .deleteComment');
		$this->assertGreaterThan(0, count($deleteButtons), 'Should have delete buttons in open issue');
		$deleteButtons[0]->click();
		usleep(1500 * 1000); // Wait for htmx response and idiomorph

		// After htmx morph: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after delete');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after deleting comment');

		// Open issue should still be visible
		$openIssuesAfter = $browser->getCssSelect('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after delete');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after delete');
	}

	/**
	 * Test that filter state is preserved when moving a comment out of an issue via drag-and-drop.
	 *
	 * When viewing comments tab with closed issues present, moving a comment out of an open issue
	 * (to standalone) should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterMovingCommentOutOfIssue()
	{
		// Setup: Create tsumego with an open issue with 2 comments AND a closed issue
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true], // Admin can drag-and-drop
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser); // Click COMMENTS tab (shows comments, hides closed issues)

		// Add a second comment to the open issue so we can move one
		$replyButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-btn'));
		$replyButton->click();
		usleep(200 * 1000);
		$replyField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form textarea'));
		$replyField->sendKeys("Comment to move");
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]'));
		$submitButton->click();
		usleep(1500 * 1000);

		// Get the comment ID to move
		$commentElements = $browser->getCssSelect('.tsumego-issue--opened .tsumego-comment');
		$this->assertGreaterThan(0, count($commentElements), 'Should have comments in open issue');
		$commentId = $commentElements[0]->getAttribute('data-comment-id');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden before move');

		// Simulate the move-comment API call via JavaScript (emulating drag-and-drop)
		$browser->driver->executeScript("
			var formData = new FormData();
			formData.append('data[Comment][tsumego_issue_id]', 'standalone');
			formData.append('data[Comment][htmx]', '1');
			fetch('/tsumego-issues/move-comment/' + " . $commentId . ", {
				method: 'POST',
				body: formData,
				headers: {'HX-Request': 'true'}
			}).then(function(response) {
				return response.text();
			}).then(function(html) {
				var target = document.getElementById('comments-section-" . $context->tsumego['id'] . "');
				if (target && html) {
					Idiomorph.morph(target, html, {morphStyle: 'outerHTML'});
					// Re-apply filter state after morph
					if (typeof currentCommentsFilter !== 'undefined' && currentCommentsFilter) {
						setTimeout(function() {
							var targetTab = document.querySelector('.tsumego-comments__tab[data-filter=\"' + currentCommentsFilter + '\"]');
							if (targetTab) targetTab.classList.add('active');
							applyCommentsFilter(currentCommentsFilter);
						}, 50);
					}
				}
			});
		");
		usleep(2000 * 1000); // Wait for fetch, morph, and filter re-apply

		// After morph: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getCssSelect('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after move');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after moving comment');

		// Open issue should still be visible
		$openIssuesAfter = $browser->getCssSelect('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after move');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after move');
	}

	/**
	 * Test that the comment form is hidden when viewing the CLOSED ISSUES tab.
	 */
	public function testCommentFormHiddenOnClosedIssuesTab()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'issues' => [
					['message' => 'Closed issue', 'status' => TsumegoIssue::$CLOSED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Click COMMENTS tab - form should be visible
		$commentsTab = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__tab[data-filter="open"]'));
		$commentsTab->click();
		usleep(300 * 1000);

		$form = $browser->driver->findElement(WebDriverBy::id('tsumegoCommentForm'));
		$formWrapper = $form->findElement(WebDriverBy::xpath('./..'));
		$this->assertTrue($formWrapper->isDisplayed(), 'Comment form should be visible on COMMENTS tab');

		// Click CLOSED ISSUES tab - form should be hidden
		$closedTab = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__tab[data-filter="closed"]'));
		$closedTab->click();
		usleep(300 * 1000);

		$this->assertFalse($formWrapper->isDisplayed(), 'Comment form should be hidden on CLOSED ISSUES tab');

		// Click COMMENTS tab again - form should be visible again
		$commentsTab->click();
		usleep(300 * 1000);

		$this->assertTrue($formWrapper->isDisplayed(), 'Comment form should be visible when back on COMMENTS tab');
	}

	/**
	 * Test that a newly added comment can be dragged (Make Issue button works).
	 *
	 * After adding a comment via htmx, the new comment should have a working Make Issue button.
	 */
	public function testNewlyAddedCommentCanBeDragged()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true], // Admin can drag
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$this->expandComments($browser);

		// Add a new comment
		$messageField = $browser->driver->findElement(WebDriverBy::id('commentMessage-tsumegoCommentForm'));
		$messageField->click();
		$messageField->sendKeys("Comment to make into issue");
		$submitButton = $browser->driver->findElement(WebDriverBy::id('submitBtn-tsumegoCommentForm'));
		$submitButton->click();
		usleep(2000 * 1000); // Wait for htmx response and idiomorph

		// Verify the comment was added
		$browser->waitUntilCssSelectorExists('.tsumego-comment--standalone', 5);
		$standaloneComments = $browser->getCssSelect('.tsumego-comment--standalone');
		$this->assertCount(1, $standaloneComments, 'Should have 1 standalone comment');

		// Find and click the Make Issue button
		$makeIssueBtn = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comment--standalone .tsumego-comment__make-issue-btn'));
		$this->assertTrue($makeIssueBtn->isDisplayed(), 'Make Issue button should be visible');
		$this->assertTrue($makeIssueBtn->isEnabled(), 'Make Issue button should be enabled');
		$makeIssueBtn->click();

		// Wait for htmx update
		usleep(1500 * 1000);
		$browser->waitUntilCssSelectorExists('.tsumego-issue', 5);

		// Verify comment is now in an issue
		$issues = $browser->getCssSelect('.tsumego-issue');
		$this->assertCount(1, $issues, 'Should have 1 issue now');
		$standaloneCommentsAfter = $browser->getCssSelect('.tsumego-comment--standalone');
		$this->assertCount(0, $standaloneCommentsAfter, 'Should have 0 standalone comments after making issue');

		// Verify the comment content is still there
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to make into issue', $pageSource);
		$this->assertTextContains('Issue #1', $pageSource);
	}

	/**
	 * Expand comments section if it's hidden.
	 *
	 * Comments are hidden by default. This helper ensures the comments section
	 * is visible by clicking the COMMENTS tab.
	 */
	private function expandComments($browser)
	{
		// Check if #msg2x (comments content) is visible
		$commentsContent = $browser->driver->findElement(WebDriverBy::id('msg2x'));
		if (!$commentsContent->isDisplayed())
		{
			// Click the COMMENTS tab to expand
			$commentsTab = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__tab[data-filter="open"]'));
			$commentsTab->click();
			usleep(350 * 1000); // Wait for any animation
		}
	}
}
