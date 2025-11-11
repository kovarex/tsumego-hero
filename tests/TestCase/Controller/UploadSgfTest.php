<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class UploadSgfTest extends TestCaseWithAuth {
	public function testUploadSgf() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '2']],
				'status' => 'S']]);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]])), 0);
		$browser = new Browser();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]])), 0);
		$openLink = $browser->driver->findElement(WebDriverBy::cssSelector('#openSgfLink'));
		$this->assertTrue($openLink->isDisplayed());
		$this->assertSame($openLink->getText(), "Open");
		$openLink->click();
		usleep(1000 * 100);
		$commentEditButton = $browser->driver->findElement(WebDriverBy::cssSelector('#sgfCommentButton'));
		$commentEditButton->click();
		$commentEditField = $browser->driver->findElement(WebDriverBy::cssSelector('#commentEditField'));
		$commentEditField->click();
		$browser->driver->getKeyboard()->sendKeys("Hello from test");
		$commentEditButton->click();
		$saveButton = $browser->driver->findElement(WebDriverBy::cssSelector('#saveSGFButton'));
		$saveButton->click();

		$sgf = ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id']]]);
		$this->assertEquals(count($sgf), 1);
		$this->assertTextContains('Hello from test', $sgf[0]['Sgf']['sgf']);
	}
}
