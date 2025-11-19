<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class TsumegosControllerTest extends TestCaseWithAuth {
	public function testSetNameAndNumIsVisible() {
		foreach ([false, true] as $openBySetConnectionID) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '666']]]]);
			$this->testAction(
				$openBySetConnectionID
				? ('/' . $context->tsumego['set-connections'][0]['id'])
				: ('tsumegos/play/' . $context->tsumego['id']),
				['return' => 'view']);
			$this->assertTextContains("tsumego set 1", $this->view);

			$dom = $this->getStringDom();
			$href = $dom->querySelector('#playTitleA');
			$this->assertTextContains('tsumego set 1', $href->textContent);
			$this->assertTextContains('666', $href->textContent);
		}
	}

	public function testViewingTsumegoInMoreSets() {
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);
		$tsumegoID = $context->tsumego['id'];
		$this->testAction('tsumegos/play/' . $tsumegoID, ['return' => 'view']);

		// The first one was selected into the title
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);

		$duplicateTable = $dom->querySelector('.duplicateTable');
		$links = $duplicateTable->getElementsByTagName('a');
		$this->assertSame(count($links), 2);
		$this->assertTextContains('/' . $context->tsumego['set-connections'][0]['id'], $links[0]->getAttribute('href'));
		$this->assertTextContains('tsumego set 1', $links[0]->textContent);
		$this->assertTextContains('666', $links[0]->textContent);
		$this->assertTextContains('/' . $context->tsumego['set-connections'][1]['id'], $links[1]->getAttribute('href'));
		$this->assertTextContains('tsumego set 2', $links[1]->textContent);
		$this->assertTextContains('777', $links[1]->textContent);
	}

	public function testViewingTsumegoInMoreSetsAndSpecifyingWhichOneIsTheMainOne() {
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$this->testAction('tsumegos/play/' . $context->tsumego['id'] . '?sid=' . $context->tsumego['sets'][1]['id'], ['return' => 'view']);

		// The second one was selected by the sid parameter
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 2', $href->textContent);
		$this->assertTextContains('777', $href->textContent);

		// all of them are listed in duplicite locations
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("666", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextContains("777", $this->view);
	}

	public function testViewingTsumegoWithoutAnySGF() {
		$context = new ContextPreparator(
			['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '666']]]]);

		$this->testAction('tsumegos/play/' . $context->tsumego['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);
	}

	// testing the same things as testViewingTsumegoInMoreSets, but using the web driver to do so
	// if this test fails, it probably means something is wrong with the web driver configuration
	public function testViewingTsumegoInMoreSetsUsingWebDriver() {
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$href = $browser->driver->findElement(WebDriverBy::cssSelector('#playTitleA'));
		$this->assertTextContains('set 1', $href->getText());
		$this->assertTextContains('666', $href->getText());
	}

	public function testTheNextAndBackButtonLinsWhenBothPointToOtherTsumegos() {
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'tsumego set 1', 'num' => '1']]],
				['sets' => [['name' => 'tsumego set 1', 'num' => '3']]]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$backButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-back-button'));
		$this->assertSame($backButton->getAttribute('href'), '/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$nextButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'));
		$this->assertSame($nextButton->getAttribute('href'), '/' . $context->otherTsumegos[1]['set-connections'][0]['id']);
	}
}
