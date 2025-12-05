<?php

use Facebook\WebDriver\WebDriverBy;

/**
 * Tests the Start button on set view pages.
 *
 * This test verifies that clicking the Start button on /sets/view/{setId}
 * navigates to the FIRST UNSOLVED puzzle in the set (not just the first puzzle).
 */
class SetsViewStartButtonTest extends ControllerTestCase
{
	public function testStartButtonNavigatesToFirstUnsolvedPuzzle(): void
	{
		// Create test context with multiple tsumegos in a test set
		// First 2 are solved, 3rd is unsolved (target), 4th-5th are also unsolved
		// Start button should link to #3 (first unsolved in middle of set)
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [['name' => 'Test Start Button Set', 'num' => '3']],
				'status' => 'V' // Not solved - this should be where Start button links to
			],
			'other-tsumegos' => [
				['sets' => [['name' => 'Test Start Button Set', 'num' => '1']], 'status' => 'S'], // Solved
				['sets' => [['name' => 'Test Start Button Set', 'num' => '2']], 'status' => 'S'], // Solved
				['sets' => [['name' => 'Test Start Button Set', 'num' => '4']], 'status' => 'V'], // Also unsolved
				['sets' => [['name' => 'Test Start Button Set', 'num' => '5']], 'status' => 'V'], // Also unsolved
			]
		]);

		$browser = Browser::instance();

		// 1. Open the set view page using the created set ID
		$setId = $context->tsumego['sets'][0]['id'];
		try
		{
			$browser->get('sets/view/' . $setId);
		}
		catch (Exception $e)
		{
			// Ignore JS errors on this page (pre-existing issue)
		}

		// 2. Verify we're on the set view page
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $setId, $browser->driver->getCurrentURL());

		// Wait for page to fully load
		sleep(2);

		// 3. Find and click the Start button (using CSS selector since linkText didn't work)
		$startButton = $browser->driver->findElement(WebDriverBy::cssSelector('a.new-button.new-buttonx'));
		$this->assertTrue($startButton->isDisplayed(), 'Start button should be visible');
		$startButton->click();

		// 4. Verify we navigated to the FIRST UNSOLVED puzzle (problem #3, not #1)
		// The URL is /{setConnectionId} (short form)
		$setConnectionId = $context->tsumego['set-connections'][0]['id'];
		$expectedUrl = Util::getMyAddress() . '/' . $setConnectionId;
		$this->assertSame($expectedUrl, $browser->driver->getCurrentURL(), 'Should navigate to first UNSOLVED puzzle (#3 in middle of set), not first puzzle (#1)');

		// Wait for puzzle page to load
		sleep(2);

		// 5. Verify the puzzle play page loaded correctly with problem #3 (the first unsolved one in middle of set)
		$playTitle = $browser->driver->findElement(WebDriverBy::cssSelector('#playTitle'));
		$this->assertTrue($playTitle->isDisplayed(), 'Play title should be visible on puzzle page');

		// The title should contain the set name and problem number 3 (NOT 1 or 2)
		$this->assertTextContains('Test Start Button Set', $playTitle->getText());
		$this->assertTextContains('3', $playTitle->getText()); // Problem number should be 3 (first unsolved in middle)
	}
}
