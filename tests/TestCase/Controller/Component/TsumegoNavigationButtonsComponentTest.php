<?php

use Facebook\WebDriver\WebDriverBy;

class TsumegoNavigationButtonsComponentTest extends TestCaseWithAuth
{
	public function buttonsTestGeneric($currentNum, $otherNums, $expectedNums)
	{
		$contextParameters = [];
		$index = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['tsumego'] = ['sets' => [['name' => 'tsumego set 1', 'num' => $currentNum]]];
		$index[$currentNum] = 0;
		$contextParameters['other-tsumegos'] = [];
		$otherNumsCount = count($otherNums);
		for ($i = 0; $i < $otherNumsCount; $i++)
		{
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $otherNums[$i]]]];
			$index[$otherNums[$i]] = $i + 1;
		}
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$div = $browser->driver->findElement(WebDriverBy::cssSelector('.tsumegoNavi2'));
		$links = $div->findElements(WebDriverBy::tagName('a'));

		// checking that the generated navigation buttons numbers and their links match the expected values
		$this->assertSame(count($links), count($expectedNums));
		for ($i = 0; $i < count($links); $i++)
		{
			$this->assertSame($links[$i]->getText(), strval($context->allTsumegos[$index[$expectedNums[$i]]]['set-connections'][0]['num']));
			$this->assertSame($links[$i]->getAttribute('href'), '/' . $context->allTsumegos[$index[$expectedNums[$i]]]['set-connections'][0]['id']);
		}
	}

	public function testNavigationButtonsLimitedFromBothSides()
	{
		$this->buttonsTestGeneric(2, [1, 3], [1, 2, 3]); // current is middle
		$this->buttonsTestGeneric(1, [2, 3], [1, 2, 3]); // current is starting
		$this->buttonsTestGeneric(3, [1, 2], [1, 2, 3]); // current is last
	}

	public function testNavigationButtonsWithExcessOnBothSides()
	{
		// we show at most 5 previous buttons and 5 next buttons utnil we start skipping
		$this->buttonsTestGeneric(8, [1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15], [1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15]);
	}

	public function testNavigationButtonsWithNotEnoughOnLeftCausingMoreOnTheRightStartingOnTheEdge()
	{
		// the lack of buttons on the left adds buttons to the right, starting at the edge
		$this->buttonsTestGeneric(1, [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 60], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 60]);
	}

	public function testNavigationButtonsWithNotEnoughOnLeftCausingMoreOnTheRightStartingNextToTheEdge()
	{
		// the lack of buttons on the left adds buttons to the right, starting next to the edge
		$this->buttonsTestGeneric(2, [1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 60], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 60]);
	}

	public function testNavigationButtonsWithNotEnoughOnRightCausingMoreOnTheLeftStartingOnTheEdge()
	{
		// the lack of buttons on the right adds buttons to the left, starting from edge
		$this->buttonsTestGeneric(60, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16], [1, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 60]);
	}

	public function testNavigationButtonsWithNotEnoughOnRightCausingMoreOnTheLeftStartingNextToTheEdge()
	{
		// the lack of buttons on the right adds buttons to the left, starting next to the edge
		$this->buttonsTestGeneric(16, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 60], [1, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 60]);
	}
}
