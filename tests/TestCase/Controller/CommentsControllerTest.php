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
	 * Test that the comments section can be collapsed/expanded.
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

		// Content should be HIDDEN by default (msg2xselected = false)
		$content = $browser->driver->findElement(WebDriverBy::id('msg2x'));
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden by default');

		// Click toggle to EXPAND
		$toggle = $browser->driver->findElement(WebDriverBy::id('show2'));
		$toggle->click();
		usleep(300 * 1000); // Wait for animation
		$this->assertTrue($content->isDisplayed(), 'Comments should be visible after first click');

		// Click toggle to COLLAPSE again
		$toggle->click();
		usleep(300 * 1000);
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden after second click');
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
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('ALL (2)', $pageSource, 'Should show ALL (2) initially');
		$this->assertTextContains('COMMENTS (1)', $pageSource, 'Should show COMMENTS (1) initially');
		$this->assertTextContains('ISSUES (1)', $pageSource, 'Should show ISSUES (1) initially');

		// Override confirm for htmx
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Delete the standalone comment
		$deleteButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comment--standalone .deleteComment'));
		$deleteButton->click();

		// Wait for htmx to process
		usleep(1500 * 1000);

		// Verify counts updated: should now show 1 total (just the issue)
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('ALL (1)', $pageSource, 'Should show ALL (1) after deleting standalone comment');
		$this->assertTextContains('COMMENTS (0)', $pageSource, 'Should show COMMENTS (0) after deleting standalone comment');
		$this->assertTextContains('ISSUES (1)', $pageSource, 'Should still show ISSUES (1)');
	}

	/**
	 * Test that closing an issue updates the open count badge via htmx OOB.
	 *
	 * When an issue is closed on the play page, the "🔴 1 open" badge should update.
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

		// Verify open badge shows "1 open" initially
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('1 open', $pageSource, 'Should show 1 open initially');

		// Click close button on the issue
		$closeButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-issue button.btn--success'));
		$closeButton->click();

		// Wait for htmx to process
		usleep(1500 * 1000);

		// Verify open badge is gone (no more open issues)
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextNotContains('1 open', $pageSource, 'Should not show "1 open" after closing');
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
		$this->assertTextContains('ISSUES (1)', $pageSource, 'Issues count should update');
		$this->assertTextContains('1 open', $pageSource, 'Open issues badge should show');
	}

	/**
	 * Expand comments section if it's hidden.
	 *
	 * Comments are hidden by default. This helper ensures the comments section
	 * is visible before interacting with it.
	 */
	private function expandComments($browser)
	{
		// Check if #msg2x (comments content) is visible
		$commentsContent = $browser->driver->findElement(WebDriverBy::id('msg2x'));
		if (!$commentsContent->isDisplayed())
		{
			// Click the toggle button to expand
			$toggleButton = $browser->driver->findElement(WebDriverBy::id('show2'));
			$toggleButton->click();
			usleep(350 * 1000); // Wait for fadeIn animation (250ms + buffer)
		}
	}
}
