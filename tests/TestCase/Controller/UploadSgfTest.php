<?php

use Facebook\WebDriver\WebDriverBy;

class UploadSgfTest extends TestCaseWithAuth
{
	public function testUploadSgf()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S']]);
		$initialCount = count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]]));
		$this->assertSame(0, $initialCount);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$this->assertSame(0, count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]])));
		$openLink = $browser->driver->findElement(WebDriverBy::cssSelector('#openSgfLink'));
		$this->assertTrue($openLink->isDisplayed());
		$this->assertSame($openLink->getText(), "Open");
		$openLink->click();
		$browser->waitUntilIDExists('#sgfCommentButton');
		$commentEditButton = $browser->driver->findElement(WebDriverBy::cssSelector('#sgfCommentButton'));
		$commentEditButton->click();
		$commentEditField = $browser->driver->findElement(WebDriverBy::cssSelector('#commentEditField'));
		$commentEditField->click();
		$browser->driver->getKeyboard()->sendKeys("Hello from test");
		$commentEditButton->click();
		$saveButton = $browser->driver->findElement(WebDriverBy::cssSelector('#saveSGFButton'));
		$saveButton->click();

		$sgf = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumego['id']],
			'order' => 'id DESC']);

		// After the addition of a new problem, and first edit, there is exactly 1 entry in the sgf database, which
		// is the first edit.
		// This is to avoid default first empty sgf in the history for every problem.
		$this->assertSame(1, count($sgf));
		$this->assertTextContains('Hello from test', $sgf[0]['Sgf']['sgf']);
	}

	public function testOpeningSgfFromHistory()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S',
				'sgfs' => ['(;GM[1]FF[4]SZ[19]C[Version 1])', '(;GM[1]FF[4]SZ[19]C[Version 2])']]]);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]])), 2);
		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumego['id']);
		$links = $browser->driver->findElements(WebDriverBy::cssSelector('.openHistoryPointLink'));
		$this->assertCount(2, $links);

		$links[0]->click();
		$browser->waitUntilIDExists('commentBox');

		$commentBox = $browser->driver->findElement(WebDriverBy::cssSelector('#commentBox'));
		$this->assertSame($commentBox->getText(), 'Version 2');

		$browser->get('/sgfs/view/' . $context->tsumego['id']);
		$links = $browser->driver->findElements(WebDriverBy::cssSelector('.openHistoryPointLink'));
		$this->assertCount(2, $links);
		$links[1]->click();
		$browser->waitUntilIDExists('commentBox');
		$commentBox = $browser->driver->findElement(WebDriverBy::cssSelector('#commentBox'));
		$this->assertSame($commentBox->getText(), 'Version 1');
	}

	public function testOpeningSgfDiff()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S',
				'sgfs' => ['(;GM[1]FF[4]SZ[19]C[Version 1])', '(;GM[1]FF[4]SZ[19]C[Version 2];B[aa])']]]);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]])), 2);
		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumego['id']);
		$links = $browser->driver->findElements(WebDriverBy::cssSelector('.openDiff'));
		$this->assertCount(1, $links);
		$links[0]->click();
		$browser->waitUntilIDExists('commentBox');
		$commentBox = $browser->driver->findElement(WebDriverBy::cssSelector('#commentBox'));
		$this->assertSame($commentBox->getText(), 'Version 2');

		$browser->waitUntilCssSelectorExists('.sgf-plus-mark');
		$plusMark = $browser->driver->findElements(WebDriverBy::cssSelector('.sgf-plus-mark'));
		$this->assertCount(2, $plusMark); // one on the board, one on the tree I guess? But mainly there is some
	}
}
