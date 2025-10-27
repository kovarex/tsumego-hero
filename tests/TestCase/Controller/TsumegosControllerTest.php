<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../ContextPreparator.php');

class TsumegosControllerTest extends TestCaseWithAuth {
	public function testSetNameAndNumIsVisible() {
		$context = (new ContextPreparator())->setTsumegoSets([['name' => 'tsumego set 1', 'num' => '666']])->prepare();
		$this->testAction('tsumegos/play/' . $context->tsumego['Tsumego']['id'], ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);
	}

	public function testViewingTsumegoInMoreSets() {
		$context = (new ContextPreparator())->setTsumegoSets(
			[
				['name' => 'tsumego set 1', 'num' => '666'],
				['name' => 'tsumego set 2', 'num' => '777']],
		)->prepare();
		$this->testAction('tsumegos/play/' . $context->tsumego['Tsumego']['id'], ['return' => 'view']);

		// The first one was selected into the title
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);

		// all of them are listed in duplicite locations
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("666", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextContains("777", $this->view);
	}
}
