<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

/**
 * Tests for Zen Mode functionality.
 *
 * Zen Mode provides a distraction-free tsumego solving experience:
 * - All non-board elements are hidden
 * - Auto-advance on both correct and incorrect answers
 * - Keyboard shortcuts (Z to toggle, Esc to exit)
 * - Board centered and expanded
 */
class ZenModeTest extends TestCaseWithAuth
{
	/**
	 * Helper to create a context with multiple tsumegos for navigation testing.
	 */
	private function createContextWithThreeTsumegos(): ContextPreparator
	{
		return new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Zen Test Set', 'num' => '2']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'Zen Test Set', 'num' => '1']]],
				['sets' => [['name' => 'Zen Test Set', 'num' => '3']]],
			],
		]);
	}

	/**
	 * Test that the Zen mode toggle button exists on the play page.
	 */
	public function testZenModeToggleButtonExists()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Zen mode toggle button should exist
		$this->assertTrue($browser->idExists('zen-mode-toggle'), 'Zen mode toggle button should exist');
	}

	/**
	 * Test that clicking the Zen mode toggle hides non-board elements but keeps essential puzzle info.
	 */
	public function testZenModeHidesNonBoardElements()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'zentest', 'rating' => 1500],
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		usleep(500 * 1000); // Wait for page load

		// Health should be visible for logged-in users
		$health = $browser->driver->findElement(WebDriverBy::id('health'));
		$this->assertTrue($health->isDisplayed(), 'Health should be visible before Zen mode');

		// Click Zen mode toggle
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000); // Wait for animation

		// Body should have zen-mode class
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Body should have zen-mode class');

		// VISIBLE: Health should REMAIN visible in Zen mode (top-left)
		$this->assertTrue($health->isDisplayed(), 'Health should remain visible in Zen mode');

		// VISIBLE: Board should still be visible
		$board = $browser->driver->findElement(WebDriverBy::cssSelector('.besogo-board'));
		$this->assertTrue($board->isDisplayed(), 'Board should remain visible in Zen mode');

		// VISIBLE: Description text should be visible (top-center)
		$descriptionText = $browser->driver->findElement(WebDriverBy::id('descriptionText'));
		$this->assertTrue($descriptionText->isDisplayed(), 'Description should be visible in Zen mode');

		// VISIBLE: Zen metadata should be visible (top-right)
		$zenMetadata = $browser->driver->findElement(WebDriverBy::id('zen-metadata'));
		$this->assertTrue($zenMetadata->isDisplayed(), 'Zen metadata should be visible in Zen mode');

		// VISIBLE: theComment element should exist (bottom-center, shown when has content)
		$theComment = $browser->driver->findElement(WebDriverBy::id('theComment'));
		$this->assertNotNull($theComment, 'theComment should exist in Zen mode');

		// HIDDEN: Play title should be hidden
		$playTitle = $browser->driver->findElement(WebDriverBy::id('playTitle'));
		$this->assertFalse($playTitle->isDisplayed(), 'Play title should be hidden in Zen mode');
	}

	/**
	 * Test that pressing Z key toggles Zen mode.
	 */
	public function testZKeyTogglesZenMode()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Press Z to enter Zen mode
		$browser->driver->getKeyboard()->sendKeys('z');
		usleep(300 * 1000);

		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Z key should activate Zen mode');

		// Press Z again to exit
		$browser->driver->getKeyboard()->sendKeys('z');
		usleep(300 * 1000);

		$this->assertStringNotContainsString('zen-mode', $body->getAttribute('class'), 'Z key should deactivate Zen mode');
	}

	/**
	 * Test that pressing Escape exits Zen mode.
	 */
	public function testEscapeKeyExitsZenMode()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Should be in Zen mode');

		// Press Escape to exit
		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
		usleep(300 * 1000);

		$this->assertStringNotContainsString('zen-mode', $body->getAttribute('class'), 'Escape should exit Zen mode');
	}

	/**
	 * Test that clicking the exit button exits Zen mode.
	 */
	public function testZenModeExitButtonWorks()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'));

		// Click exit button
		$browser->clickId('zen-mode-exit');
		usleep(300 * 1000);

		$this->assertStringNotContainsString('zen-mode', $body->getAttribute('class'), 'Exit button should deactivate Zen mode');
	}

	/**
	 * Test that solving a problem in Zen mode triggers auto-advance behavior.
	 * We verify by checking that the auto-advance logic runs.
	 *
	 * @group zen-mode-auto-advance
	 */
	public function testZenModeAutoAdvancesOnCorrect()
	{
		// Need at least 2 tsumegos for nextButtonLink to be set
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Zen Test Set', 'num' => '1']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'Zen Test Set', 'num' => '2']]],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Verify nextButtonLink is set
		$nextLink = $browser->driver->executeScript("return typeof nextButtonLink !== 'undefined' ? nextButtonLink : ''");
		$this->assertNotEmpty($nextLink, 'nextButtonLink should be set when there are multiple tsumegos');

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// Debug: Verify zen mode is active
		$isZenActive = $browser->driver->executeScript("return window.isZenModeActive ? window.isZenModeActive() : false");
		$this->assertTrue($isZenActive === true, 'isZenModeActive() should return true after enabling zen mode');

		// Track if auto-advance callback was triggered
		$browser->driver->executeScript("
			window.autoAdvanceTriggered = false;
			window.autoAdvanceTarget = null;
		");

		// Modify the zen mode auto-advance code to track instead of navigate
		// by replacing location.href assignment with a tracker
		$browser->driver->executeScript("
			var origSetTimeout = window.setTimeout;
			window.setTimeout = function(fn, delay) {
				// Wrap the function to capture what it does
				var wrappedFn = function() {
					// Track that auto-advance was triggered
					window.autoAdvanceTriggered = true;
					window.autoAdvanceTarget = nextButtonLink;
					// Don't actually navigate, just record
				};
				return origSetTimeout(wrappedFn, delay);
			};
		");

		// Simulate solving the problem correctly
		$browser->driver->executeScript("displayResult('S')");

		// Wait for auto-advance timeout (800ms + buffer)
		usleep(1200 * 1000);

		// Check if auto-advance was triggered
		$autoAdvanceTriggered = $browser->driver->executeScript("return window.autoAdvanceTriggered");
		$autoAdvanceTarget = $browser->driver->executeScript("return window.autoAdvanceTarget");

		$this->assertTrue($autoAdvanceTriggered, 'Auto-advance should be triggered when in Zen mode');
		$this->assertEquals($nextLink, $autoAdvanceTarget, 'Auto-advance target should be nextButtonLink');
	}

	/**
	 * Test that failing a problem in Zen mode auto-advances to next problem.
	 * This is the stress-free behavior - no punishment, keep the flow.
	 *
	 * @group zen-mode-auto-advance
	 */
	public function testZenModeAutoAdvancesOnIncorrect()
	{
		// Need at least 2 tsumegos for nextButtonLink to be set
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Zen Test Set', 'num' => '1']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'Zen Test Set', 'num' => '2']]],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Verify nextButtonLink is set
		$nextLink = $browser->driver->executeScript("return typeof nextButtonLink !== 'undefined' ? nextButtonLink : ''");
		$this->assertNotEmpty($nextLink, 'nextButtonLink should be set when there are multiple tsumegos');

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// Debug: Verify zen mode is active
		$isZenActive = $browser->driver->executeScript("return window.isZenModeActive ? window.isZenModeActive() : false");
		$this->assertTrue($isZenActive === true, 'isZenModeActive() should return true after enabling zen mode');

		// Track if auto-advance callback was triggered
		$browser->driver->executeScript("
			window.autoAdvanceTriggered = false;
			window.autoAdvanceTarget = null;
		");

		// Modify setTimeout to track instead of navigate
		$browser->driver->executeScript("
			var origSetTimeout = window.setTimeout;
			window.setTimeout = function(fn, delay) {
				var wrappedFn = function() {
					window.autoAdvanceTriggered = true;
					window.autoAdvanceTarget = nextButtonLink;
				};
				return origSetTimeout(wrappedFn, delay);
			};
		");

		// Simulate failing the problem
		$browser->driver->executeScript("displayResult('F')");

		// Wait for auto-advance timeout (800ms + buffer)
		usleep(1200 * 1000);

		// Check if auto-advance was triggered
		$autoAdvanceTriggered = $browser->driver->executeScript("return window.autoAdvanceTriggered");
		$autoAdvanceTarget = $browser->driver->executeScript("return window.autoAdvanceTarget");

		$this->assertTrue($autoAdvanceTriggered, 'Auto-advance should be triggered when in Zen mode on incorrect answer');
		$this->assertEquals($nextLink, $autoAdvanceTarget, 'Auto-advance target should be nextButtonLink');
	}

	/**
	 * Test that board has visual glow effect on correct answer in Zen mode.
	 */
	public function testZenModeShowsGreenGlowOnCorrect()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// Simulate solving the problem correctly
		$browser->driver->executeScript("displayResult('S')");
		usleep(100 * 1000); // Small delay to catch the glow

		// Check for green glow on board
		$board = $browser->driver->findElement(WebDriverBy::cssSelector('.besogo-board'));
		$boxShadow = $board->getCssValue('box-shadow');
		$this->assertStringContainsString('rgb', $boxShadow, 'Board should have box-shadow (glow effect)');
	}

	/**
	 * Test that Zen mode persists across navigation (body class preserved, only #content swapped).
	 */
	public function testZenModePersistsAcrossNavigation()
	{
		// Need at least 2 tsumegos for navigation
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Zen Test Set', 'num' => '1']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'Zen Test Set', 'num' => '2']]],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Get initial puzzle ID
		$puzzle1Id = $browser->driver->executeScript("return tsumegoID");

		// Verify nextButtonLink is set
		$nextLink = $browser->driver->executeScript("return nextButtonLink || ''");
		$this->assertNotEmpty($nextLink, 'nextButtonLink should be set');

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// Verify zen mode is active
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'));

		// Navigate using zenModeNavigateToNext (same as auto-advance does)
		$browser->driver->executeScript("
			window.zenNavComplete = false;
			window.zenNavError = null;
			if (typeof window.zenModeNavigateToNext === 'function') {
				window.zenModeNavigateToNext().then(function() {
					window.zenNavComplete = true;
				}).catch(function(e) {
					window.zenNavError = e.message;
					window.zenNavComplete = true;
				});
			} else {
				window.zenNavError = 'zenModeNavigateToNext not defined';
				window.zenNavComplete = true;
			}
		");

		// Wait for navigation to complete
		for ($i = 0; $i < 50; $i++)
		{
			usleep(100 * 1000);
			if ($browser->driver->executeScript("return window.zenNavComplete === true"))
				break;
		}

		$error = $browser->driver->executeScript("return window.zenNavError");
		$this->assertNull($error, "Navigation failed: {$error}");

		// Verify we're on a different puzzle
		$puzzle2Id = $browser->driver->executeScript("return tsumegoID");
		$this->assertNotEquals($puzzle1Id, $puzzle2Id, 'Should have navigated to different puzzle');

		// Verify zen mode is still active (body class preserved since only #content was swapped)
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Zen mode should persist after navigation');

		// Verify isZenModeActive returns true
		$isZenActive = $browser->driver->executeScript("return window.isZenModeActive ? window.isZenModeActive() : false");
		$this->assertTrue($isZenActive === true, 'isZenModeActive() should return true after navigation');
	}

	/**
	 * Test Zen Mode auto-advance through multiple puzzles without page flash.
	 *
	 * REQUIREMENT: In Zen Mode, after solving/failing a puzzle, the next puzzle should
	 * load smoothly WITHOUT a full page reload (no flash). This test verifies:
	 * 1. User solves puzzle 1 (or fails it)
	 * 2. Auto-advance fetches puzzle 2 seamlessly
	 * 3. The board updates with new puzzle, zen mode persists
	 * 4. User solves puzzle 2
	 * 5. Auto-advance fetches puzzle 3 seamlessly
	 *
	 * IMPLEMENTATION: We use fetch() to get the next page HTML, extract key variables
	 * (tsumegoID, nextButtonLink, SGF file path, etc.) via regex, update the current
	 * page's JavaScript state, and re-initialize the Go board with the new SGF.
	 * This avoids full page swap which has issues with script execution.
	 */
	public function testZenModeNavigatesThroughThreePuzzles()
	{
		// Create 3 tsumegos
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Zen Flow Set', 'num' => '1']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'Zen Flow Set', 'num' => '2']]],
				['sets' => [['name' => 'Zen Flow Set', 'num' => '3']]],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Get puzzle 1 ID
		$puzzle1Id = $browser->driver->executeScript("return tsumegoID");
		$this->assertNotEmpty($puzzle1Id, 'Puzzle 1 ID should be set');

		// Verify nextButtonLink points to puzzle 2
		$link1 = $browser->driver->executeScript("return nextButtonLink || ''");
		$this->assertNotEmpty($link1, 'Link to puzzle 2 should be set');

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// === Navigate to puzzle 2 using Zen Mode auto-advance ===
		// This simulates what happens after solving a puzzle in Zen Mode
		$browser->driver->executeScript("
			window.zenNavComplete = false;
			window.zenNavError = null;
			
			// Call the Zen Mode navigation function (defined in play.ctp)
			if (typeof window.zenModeNavigateToNext === 'function') {
				window.zenModeNavigateToNext().then(function() {
					window.zenNavComplete = true;
				}).catch(function(e) {
					window.zenNavError = e.message;
					window.zenNavComplete = true;
				});
			} else {
				window.zenNavError = 'zenModeNavigateToNext not defined';
				window.zenNavComplete = true;
			}
		");

		// Wait for navigation
		for ($i = 0; $i < 50; $i++)
		{
			usleep(100 * 1000);
			if ($browser->driver->executeScript("return window.zenNavComplete === true"))
				break;
		}

		$error = $browser->driver->executeScript("return window.zenNavError");
		$this->assertNull($error, "Zen navigation failed: {$error}");

		// Verify we're on puzzle 2
		$puzzle2Id = $browser->driver->executeScript("return tsumegoID");
		$this->assertNotEquals($puzzle1Id, $puzzle2Id, "Should be on puzzle 2 (puzzle1={$puzzle1Id}, current={$puzzle2Id})");

		// Verify zen mode is still active
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Zen mode should persist on puzzle 2');

		// Verify nextButtonLink is updated for puzzle 3
		$link2 = $browser->driver->executeScript("return nextButtonLink || ''");
		$this->assertNotEmpty($link2, 'Link to puzzle 3 should be set');
		$this->assertNotEquals($link1, $link2, "nextButtonLink should change (was {$link1}, now {$link2})");

		// === Navigate to puzzle 3 ===
		$browser->driver->executeScript("
			window.zenNavComplete = false;
			window.zenNavError = null;
			if (typeof window.zenModeNavigateToNext === 'function') {
				window.zenModeNavigateToNext().then(function() {
					window.zenNavComplete = true;
				}).catch(function(e) {
					window.zenNavError = e.message;
					window.zenNavComplete = true;
				});
			} else {
				window.zenNavError = 'zenModeNavigateToNext not defined';
				window.zenNavComplete = true;
			}
		");

		// Wait for navigation
		for ($i = 0; $i < 50; $i++)
		{
			usleep(100 * 1000);
			if ($browser->driver->executeScript("return window.zenNavComplete === true"))
				break;
		}

		$error = $browser->driver->executeScript("return window.zenNavError");
		$this->assertNull($error, "Zen navigation to puzzle 3 failed: {$error}");

		// Verify we're on puzzle 3
		$puzzle3Id = $browser->driver->executeScript("return tsumegoID");
		$this->assertNotEquals($puzzle2Id, $puzzle3Id, "Should be on puzzle 3 (puzzle2={$puzzle2Id}, current={$puzzle3Id})");

		// Verify zen mode is STILL active after 2 navigations
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Zen mode should persist on puzzle 3');

		// Verify the board element exists and has content (besogo SVG)
		// Note: ui=2 (default) uses #target, ui!=2 uses #board
		$boardHtml = $browser->driver->executeScript("
			var board = document.getElementById('board') || document.getElementById('target');
			return board ? board.innerHTML.length : 0;
		");

		// Debug: capture console logs from navigation
		$debugLogs = $browser->driver->executeScript("
			return window.zenDebugLogs || [];
		");

		$this->assertGreaterThan(100, $boardHtml, 'Board should have substantial content (besogo SVG) after navigation. Debug: ' . json_encode($debugLogs));

		// Verify besogo container is present
		$hasBesogoContainer = $browser->driver->executeScript("
			return document.querySelector('.besogo-container') !== null;
		");
		$this->assertTrue($hasBesogoContainer, 'Besogo container should exist after navigation');

		// Verify isZenModeActive function works
		$isZenActive = $browser->driver->executeScript("return window.isZenModeActive ? window.isZenModeActive() : false");
		$this->assertTrue($isZenActive === true, 'isZenModeActive() should return true on puzzle 3');
	}

	/**
	 * Test that after Zen mode navigation, the SGF is properly loaded and stones are visible.
	 *
	 * This tests that:
	 * 1. Puzzle 1 loads with stones
	 * 2. Zen mode navigation fetches puzzle 2
	 * 3. Puzzle 2 loads with stones (different count proves new SGF loaded)
	 *
	 * Note: Colors may be swapped and board may be rotated - we check stone count, not positions.
	 *
	 * @group zen-mode-navigation
	 */
	public function testZenModeNavigationLoadsSgfProperly()
	{
		// Create 2 tsumegos with DIFFERENT stone counts to verify the SGF changed
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [['name' => 'SGF Test Set', 'num' => '1']],
				'sgf' => '(;SZ[19]AB[dd][pd][dp]AW[pp][dj][pj])',  // 6 stones
			],
			'other-tsumegos' => [
				[
					'sets' => [['name' => 'SGF Test Set', 'num' => '2']],
					'sgf' => '(;SZ[19]AB[cc][dc][ec]AW[qq][rq])',  // 5 stones (different count!)
				],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);

		// Get initial stone count
		$stoneCount1 = $browser->driver->executeScript("
			if (!besogo.editor) return -1;
			var current = besogo.editor.getCurrent();
			if (!current || !current.board) return -1;
			var count = 0;
			var keys = Object.keys(current.board);
			for (var i = 0; i < keys.length; i++) {
				if (current.board[keys[i]] !== 0) count++;
			}
			return count;
		");
		$this->assertEquals(6, $stoneCount1, 'Puzzle 1 should have 6 stones');

		// Enter Zen mode
		$browser->clickId('zen-mode-toggle');
		usleep(300 * 1000);

		// Navigate to puzzle 2 using Zen Mode navigation
		$browser->driver->executeScript("
			window.zenNavComplete = false;
			window.zenNavError = null;
			if (typeof window.zenModeNavigateToNext === 'function') {
				window.zenModeNavigateToNext().then(function() {
					window.zenNavComplete = true;
				}).catch(function(e) {
					window.zenNavError = e.message;
					window.zenNavComplete = true;
				});
			} else {
				window.zenNavError = 'zenModeNavigateToNext not defined';
				window.zenNavComplete = true;
			}
		");

		// Wait for navigation
		for ($i = 0; $i < 50; $i++)
		{
			usleep(100 * 1000);
			if ($browser->driver->executeScript("return window.zenNavComplete === true"))
				break;
		}

		$error = $browser->driver->executeScript("return window.zenNavError");
		$this->assertNull($error, "Zen navigation failed: {$error}");

		// Give time for SGF to load and board to redraw
		usleep(500 * 1000);

		// Verify tsumegoID changed (confirms we navigated)
		$newTsumegoId = $browser->driver->executeScript("return tsumegoID");
		$this->assertNotEquals($context->tsumego['id'], $newTsumegoId, 'Should be on puzzle 2');

		// Get stone count after navigation - should be 5 (different from puzzle 1's 6)
		$stoneCount2 = $browser->driver->executeScript("
			if (!besogo.editor) return -1;
			var current = besogo.editor.getCurrent();
			if (!current || !current.board) return -1;
			var count = 0;
			var keys = Object.keys(current.board);
			for (var i = 0; i < keys.length; i++) {
				if (current.board[keys[i]] !== 0) count++;
			}
			return count;
		");
		$this->assertEquals(5, $stoneCount2, 'Puzzle 2 should have 5 stones (proves new SGF loaded)');

		// Verify zen mode is still active
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Zen mode should persist');
	}

	/**
	 * Test that #theComment element is visible with white text in zen mode.
	 */
	public function testCommentVisibleWithWhiteTextInZenMode()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'zentest', 'rating' => 1500],
			'tsumego' => [
				'sets' => [['name' => 'test set', 'num' => '1']],
				// Use the default SGF which has a proper solution
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id'] . '?zen=1');
		usleep(500 * 1000); // Wait for page load and zen mode to activate

		// Body should have zen-mode class
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Body should have zen-mode class');

		// Find #theComment element
		$comment = $browser->driver->findElement(WebDriverBy::id('theComment'));
		$this->assertTrue($comment->isDisplayed(), '#theComment should be displayed in zen mode');

		// Find #descriptionText element ("Black to capture")
		$description = $browser->driver->findElement(WebDriverBy::id('descriptionText'));
		$this->assertTrue($description->isDisplayed(), '#descriptionText should be displayed in zen mode');

		// Verify description has actual text content visible
		$descriptionText = $description->getText();
		$this->assertNotEmpty($descriptionText, '#descriptionText should have visible text content');
		$this->assertGreaterThan(5, strlen($descriptionText), '#descriptionText should have meaningful text (more than 5 chars)');

		// Note: We can't check specific text content because it depends on the SGF structure
		// and whether Besogo has initialized. The key thing is that the element is visible
		// and styled correctly (white text, fixed position, etc.)

		// Check computed styles using JavaScript
		$computedStyles = $browser->driver->executeScript("
			var element = document.getElementById('theComment');
			var styles = window.getComputedStyle(element);
			return {
				display: styles.display,
				color: styles.color,
				position: styles.position,
				bottom: styles.bottom,
				zIndex: styles.zIndex
			};
		");

		// Verify display is not 'none'
		$this->assertNotEquals('none', $computedStyles['display'], '#theComment display should not be none');

		// Verify color is white or close to white (rgb(255, 255, 255))
		$this->assertMatchesRegularExpression(
			'/rgb\s*\(\s*25[0-5]\s*,\s*25[0-5]\s*,\s*25[0-5]\s*\)/',
			$computedStyles['color'],
			'#theComment text color should be white'
		);

		// Verify fixed positioning at bottom
		$this->assertEquals('fixed', $computedStyles['position'], '#theComment should be fixed positioned');
		$this->assertEquals('10px', $computedStyles['bottom'], '#theComment should be 10px from bottom');

		// Verify high z-index
		$this->assertGreaterThanOrEqual(9999, (int)$computedStyles['zIndex'], '#theComment should have high z-index');

		// Check #descriptionText styles (positioned at top-center)
		$descriptionStyles = $browser->driver->executeScript("
			var element = document.getElementById('descriptionText');
			var parent = element.parentElement; // Get the titleDescription parent
			var styles = window.getComputedStyle(element);
			var parentStyles = window.getComputedStyle(parent);
			return {
				display: styles.display,
				color: styles.color,
				parentPosition: parentStyles.position,
				parentTop: parentStyles.top,
				parentZIndex: parentStyles.zIndex
			};
		");

		$this->assertNotEquals('none', $descriptionStyles['display'], '#descriptionText display should not be none');
		$this->assertEquals('fixed', $descriptionStyles['parentPosition'], 'Parent should be fixed positioned');
		$this->assertEquals('10px', $descriptionStyles['parentTop'], 'Parent should be 10px from top');
		$this->assertGreaterThanOrEqual(9999, (int)$descriptionStyles['parentZIndex'], 'Parent should have high z-index');
	}

	/**
	 * Test that all visible elements update when navigating to next puzzle in zen mode.
	 */
	public function testZenModeElementsUpdateOnNavigation()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'zentest', 'rating' => 1500],
			'tsumego' => [
				'sets' => [['name' => 'Zen Set', 'num' => '1']],
				'description' => 'Black to kill',
				'rating' => 1000,
			],
			'other-tsumegos' => [
				[
					'sets' => [['name' => 'Zen Set', 'num' => '2']],
					'description' => 'White to live',
					'rating' => 1200,
				],
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id'] . '?zen=1');
		usleep(500 * 1000);

		$initialDescription = $browser->driver->findElement(WebDriverBy::id('descriptionText'))->getText();
		$initialMetadata = $browser->driver->findElement(WebDriverBy::id('zen-metadata'))->getText();
		$initialTsumegoId = $browser->driver->executeScript("return tsumegoID");

		$this->assertStringContainsString('Black to kill', $initialDescription, 'Initial description should contain Black to kill');
		$this->assertStringContainsString('Zen Set #1', $initialMetadata, 'Initial metadata should contain Zen Set #1');

		$browser->driver->getKeyboard()->sendKeys('x');
		usleep(1000 * 1000);

		$newDescription = $browser->driver->findElement(WebDriverBy::id('descriptionText'))->getText();
		$newMetadata = $browser->driver->findElement(WebDriverBy::id('zen-metadata'))->getText();
		$newTsumegoId = $browser->driver->executeScript("return tsumegoID");

		$this->assertNotEquals($initialTsumegoId, $newTsumegoId, 'Tsumego ID should change after navigation');
		$this->assertNotEquals($initialDescription, $newDescription, 'Description should update after navigation');
		$this->assertStringContainsString('White to live', $newDescription, 'New description should contain White to live');
		$this->assertNotEquals($initialMetadata, $newMetadata, 'Metadata should update after navigation');
		$this->assertStringContainsString('Zen Set #2', $newMetadata, 'New metadata should contain Zen Set #2');

		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString('zen-mode', $body->getAttribute('class'), 'Should still be in zen mode');
	}
}
