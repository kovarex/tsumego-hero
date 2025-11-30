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

		// The comment form is now directly in the comments section
		$messageField = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__form textarea'));
		$messageField->click();
		$messageField->sendKeys("My first comment");
		$submitButton = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__form button[type="submit"]'));
		$this->assertTrue($submitButton->isDisplayed());
		$this->assertTrue($submitButton->isEnabled());
		$submitButton->click();

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
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'comments' => [['message' => 'test comment']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Content should be visible by default
		$content = $browser->driver->findElement(WebDriverBy::id('msg2x'));
		$this->assertTrue($content->isDisplayed());

		// Click toggle to collapse
		$toggle = $browser->driver->findElement(WebDriverBy::id('show2'));
		$toggle->click();
		usleep(300 * 1000); // Wait for animation
		$this->assertFalse($content->isDisplayed());

		// Click toggle to expand again
		$toggle->click();
		usleep(300 * 1000);
		$this->assertTrue($content->isDisplayed());
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

		// Verify reply appears on the page
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

		// Verify comment is visible
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to delete', $pageSource);

		// Find and click the Delete button (accept the confirm dialog)
		$deleteButton = $browser->driver->findElement(WebDriverBy::cssSelector('.deleteComment'));
		// Execute JS to override confirm and click
		$browser->driver->executeScript("window.confirm = function() { return true; };");
		$deleteButton->click();

		// Verify comment is no longer visible
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextNotContains('Comment to delete', $pageSource);
	}
}
