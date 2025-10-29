<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
require_once(__DIR__ . '/../../../Browser.php');
require_once(__DIR__ . '/../../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class TsumegoNavigationButtonsComponentTest extends TestCaseWithAuth {
	public function buttonsTestGeneric($currentNum, $otherNums, $expectedNums) {
		$contextParameters = [];
		$index = [];
		$contextParameters['tsumego'] = ['sets' => [['name' => 'tsumego set 1', 'num' => $currentNum]]];
		$index[$currentNum] = 0;
		$contextParameters['other-tsumegos'] = [];
		$otherNumsCount = count($otherNums);
		for ($i = 0; $i < $otherNumsCount; $i++) {
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $otherNums[$i]]]];
			$index[$otherNums[$i]] = $i + 1;
		}
		$context = new ContextPreparator($contextParameters);

		$browser = new Browser();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$div = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumegoNavi2'));
		$links = $div->findElements(WebDriverBy::tagName('a'));

		// there should be exactly 3 links pointing to the first, current and last tsumego
		$this->assertSame(count($links), count($expectedNums));
		for ($i = 0; $i < count($links); $i++) {
			$this->assertSame($links[$i]->getAttribute('href'), '/' . $context->allTsumegos[$index[$expectedNums[$i]]]['set-connections'][0]['id']);
		}
	}

	// the next and back buttons will go back to the parent set when this is last (for next) or first (for back) tsumego of that set.
	public function testNavigationButtons() {
		$this->buttonsTestGeneric(2, [1, 3], [1, 2, 3]); // current is middle
		$this->buttonsTestGeneric(1, [2, 3], [1, 2, 3]); // current is starting
		$this->buttonsTestGeneric(3, [1, 2], [1, 2, 3]); // current is last
	}
}
