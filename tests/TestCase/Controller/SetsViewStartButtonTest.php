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
		// when $statusToPick is 'W' (solved once more than a week ago)
		// I'm testing the case when everything is solved, so I want to pick first 'W' problem
		foreach (['V', 'W'] as $statusToPick)
		{
			$browser = Browser::instance();
			// Create test context with multiple tsumegos in a test set
			// First 2 are solved, 3rd is unsolved (target), 4th-5th are also unsolved
			// Start button should link to #3 (first unsolved in middle of set)
			$context = new ContextPreparator([
				'tsumegos' => [
					['set_order' => 1, 'status' => 'S'], // Solved
					['set_order' => 2, 'status' => 'S'], // Solved
					['set_order' => 3, 'status' => $statusToPick], // Not solved - this should be where Start button links to
					['set_order' => 4, 'status' => $statusToPick], // Also unsolved
					['set_order' => 5, 'status' => $statusToPick], // Also unsolved
				]]);

			// 1. Open the set view page using the created set ID
			$setId = $context->tsumegos[0]['sets'][0]['id'];
			$browser->get('sets/view/' . $setId);

			// 2. Verify we're on the set view page
			$this->assertSame(Util::getMyAddress() . '/sets/view/' . $setId, $browser->driver->getCurrentURL());

			// Wait for page to fully load
			$browser->waitUntilCssSelectorExists('a.new-button.new-buttonx');

			// 3. Find and click the Start button (using CSS selector since linkText didn't work)
			$startButton = $browser->getCssSelect('a.new-button.new-buttonx')[0];
			$this->assertTrue($startButton->isDisplayed(), 'Start button should be visible');
			$startButton->click();

			// 4. Verify we navigated to the FIRST UNSOLVED puzzle (problem #3, not #1)
			// The URL is /{setConnectionId} (short form)
			$setConnectionId = $context->tsumegos[2]['set-connections'][0]['id'];
			$expectedUrl = Util::getMyAddress() . '/' . $setConnectionId;
			$this->assertSame($expectedUrl, $browser->driver->getCurrentURL(), 'Should navigate to first UNSOLVED puzzle (#3 in middle of set), not first puzzle (#1)');
		}

		// I have no idea why, but this test, for some reason makes test coming after this test hang on Browser::init
		// I tried different things, but nothing worked, but completely shutting down the browser
		Browser::shutdown();
	}
}
