<?php

use Facebook\WebDriver\WebDriverBy;

class CommentsControllerTest extends ControllerTestCase
{
	public function testCommentsVisible()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->clickID('show');
		$browser->clickID('CommentMessage');
		$browser->driver->getKeyboard()->sendKeys("My first comment");
		$sumbitButton = $browser->driver->findElement(WebDriverBy::cssSelector('#tsumegoCommentForm input[type="submit"]'));
		$this->assertTrue($sumbitButton->isDisplayed());
		$this->assertTrue($sumbitButton->isEnabled());
		$browser->driver->action()->moveToElement($sumbitButton)->click()->perform();
		$sumbitButton->click();
		$browser->get('comments');
		$this->assertTextContains('My first comment', $browser->driver->getPageSource());
	}

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

	public function testShowCommentOnVisitingAlreadySolvedTsumego()
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']], 'comments' => [['message' => 'spoiler']], 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$this->assertTrue($browser->getCssSelect('#commentSpace')[0]->isDisplayed());
	}
}
