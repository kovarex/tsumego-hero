<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('Util', 'Utility');
App::uses('TimeModeUtil', 'Utility');

/**
 * Browser tests for the play page's interaction with the result AJAX endpoint:
 * result banners ("Correct!"/"Incorrect"/"locked until"), board locking, the
 * reset button, hearts/potion UI and the XP display.
 *
 * The server-side result processing itself is covered by
 * PlayResultProcessorComponentTest (pure AJAX POSTs).
 */
class PlayResultBrowserTest extends TestCaseWithAuth
{
	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	public function testPostSolveMistakesAreNotPenalized(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Prove competence
		$browser->playWithResult('S');
		$this->assertTrue(
			$browser->driver->executeScript('return problemSolved;'));

		// Post-solve exploration (reset + mistake) - must be harmless
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('F');

		$this->assertGreaterThan($originalRating,
			(float) $context->reloadUser()['rating'],
			'Rating must increase from solve; post-solve errors must not drag it down');

		$this->assertSame($originalDamage,
			(int) $context->user['damage'],
			'Hearts must not be lost for mistakes made after solving');

		$attempts = ClassRegistry::init('TsumegoAttempt')->find('all', [
			'conditions' => [
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
			],
		]);
		$this->assertCount(1, $attempts,
			'Must produce exactly one attempt record');
		$this->assertTrue($attempts[0]['TsumegoAttempt']['solved'],
			'Attempt must be marked solved');
		$this->assertSame(0, (int) $attempts[0]['TsumegoAttempt']['misplays'],
			'Post-solve mistakes must not appear in attempt history');
	}

	public function testResetAfterSolveDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Solve the puzzle
		$browser->driver->executeScript("displayResult('S');");
		$browser->waitForSubmitResult();

		// Reset the puzzle
		$browser->clickId('besogo-reset-button');

		// Verify no damage - puzzle was already solved, noXP guard protects
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
	}

	public function testResetAtStartDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Reset immediately without making any moves
		$browser->clickId('besogo-reset-button');

		// Verify no damage
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting at start should not cause damage');
	}

	public function testMultipleFailsThenResetThenSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail twice (second fail blocked by failAlreadyReported)
		$browser->playWithResult('F');
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Only first fail should cause damage');

		// Reset - should not cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset should not cause additional damage');

		// Solve
		$browser->playWithResult('S');

		// Verify final state
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Damage should only be from the first fail');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame('S', $status['TsumegoStatus']['status'],
			'Status should be solved');
	}

	public function testSolveShowsCorrectAndUpdatesXP(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');

		$this->assertStringContainsString('Correct!', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.problemSolved;'));
		$this->assertTrue($browser->driver->executeScript('return window.noXP;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));

		// Account widget should reflect server state
		$this->assertGreaterThan(0, $browser->driver->executeScript('return window.accountWidget.xp;'));
		$this->assertNotNull($browser->driver->executeScript('return window.accountWidget.rating;'));
	}

	public function testFailShowsIncorrectAndLosesHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		$this->assertStringContainsString('Incorrect', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.failAlreadyReported;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.misplays;'));
		$this->assertSame(1, $context->reloadUser()['damage']);
	}

	public function testRunOutOfHeartsShowsLockedMessage(): void
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['health' => 0]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		$this->assertStringContainsString('locked until', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.tryAgainTomorrow;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));
	}

	public function testResetAfterFailDoesNotCostExtraHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');
		$this->assertSame(1, $context->reloadUser()['damage']);

		$browser->clickId('besogo-reset-button');
		$this->assertSame(1, $context->reloadUser()['damage'],
			'Reset should not cause additional damage');
		$this->assertFalse($browser->driver->executeScript('return window.failAlreadyReported;'));
	}

	public function testSolvedPuzzleCannotBeFailedOrReSolved(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');
		$originalDamage = (int) $context->reloadUser()['damage'];
		$originalXp = $context->reloadUser()['xp'];

		// Try to fail - should be harmless
		$browser->playWithResult('F');
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Fail on solved puzzle should not cause damage');

		// Try to solve again - should be harmless
		$browser->playWithResult('S');
		$this->assertSame($originalXp, $context->reloadUser()['xp'],
			'Re-solving should not grant more XP');
	}

	public function testSolveUpdatesXPDisplay(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');

		// XP display should show solved state
		$this->assertTrue($browser->driver->executeScript('return window.xpStatus !== undefined;'));
		$xpText = $browser->driver->findElement(WebDriverBy::id('xpDisplayText'))->getText();
		$this->assertNotEmpty($xpText, 'XP display should show XP gained');
	}

	public function testPotionTriggerRestoresHeartsAndShowsAlert(): void
	{
		$maxHealth = Util::getHealthBasedOnLevel(50);
		$context = new ContextPreparator([
			'user' => ['level' => 50, 'damage' => $maxHealth + 200],
			'tsumego' => ['set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		// Potion should trigger: hearts restored, alert visible
		$this->assertSame(0, $browser->driver->executeScript('return window.misplays;'),
			'Potion should reset misplays to 0');
		$this->assertSame($maxHealth, $browser->driver->executeScript('return window.remainingHealth;'),
			'Potion should restore remainingHealth to max');
		$this->assertTrue($browser->driver->executeScript(
			'return document.getElementById("potionAlerts").style.display !== "none";'),
			'Potion alert should be visible');
	}

	private function waitForEditor(Browser $browser): void
	{
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"return typeof window.besogo !== 'undefined' && !!besogo.editor && !!besogo.editor.getCurrent();");
		});
	}

	private const WRONG_VARIATION_SGF = '(;GM[1]FF[4]ST[2]SZ[19](;B[aa];W[ab];B[ba]C[+])(;B[ab];W[aa];B[ba]))';

	// Returns the move of the in-tree wrong variation (root's CORRECT_BAD child),
	// corner-independent (the corner transform moves the coordinates).
	private function wrongVariationMove(Browser $browser): ?array
	{
		$json = $browser->driver->executeScript(
			"var c = besogo.editor.getRoot().children; var t = null; for (var i = 0; i < c.length; i++) "
			. "if (c[i].correct === 2 && c[i].move) { t = c[i].move; break; } "
			. "return t ? JSON.stringify({x: t.x, y: t.y, color: t.color}) : 'null';");
		return json_decode($json, true);
	}

	/**
	 * Refreshing while inside a wrong variation restores the board to the
	 * mid-variation position, and the restore itself applies no penalty;
	 * completing the variation still shows Incorrect and costs one heart, so
	 * leaving can never reset a wrong line for free.
	 */
	public function testRefreshDuringWrongVariationRestoresBoardAndStillPenalizesOnCompletion(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');

		// Move 1 enters the wrong variation but does NOT end it (the opponent's
		// response still leaves a solver continuation), so no fail is reported yet.
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");

		// Wait for the opponent's auto-response so we are mid-variation.
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});
		$this->assertFalse($browser->driver->executeScript('return window.failAlreadyReported;'),
			'The wrong variation must not be judged until it is completed');

		// Leave and return: the board must be restored, not reset.
		$browser->get($url);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length === 2;');
		});
		$this->assertSame(2, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'Refreshing must restore the mid-variation board instead of resetting it');
		$this->assertSame(0, (int) $context->reloadUser()['damage'],
			'Restoring the board must not apply a penalty by itself');

		// Continuing from the restored position and completing the wrong
		// variation must show Incorrect and cost exactly one heart.
		$failing = json_decode($browser->driver->executeScript(
			"var n = besogo.editor.getCurrent(); var c = n.children[0]; "
			. "return c && c.move ? JSON.stringify({x: c.move.x, y: c.move.y}) : 'null';"), true);
		$this->assertNotNull($failing, 'There must be a continuation move that fails the variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$failing['x']}, {$failing['y']}, false, false);");
		$browser->waitForSubmitResult();

		$this->assertStringContainsString('Incorrect',
			$browser->driver->findElement(WebDriverBy::id('status'))->getText(),
			'Completing the wrong variation after restore must show Incorrect');
		$this->assertSame(1, (int) $context->reloadUser()['damage'],
			'Completing the wrong variation after restore must cost one heart');
	}

	/**
	 * A solved puzzle stays open for inspection, and moves made there persist
	 * and restore on return just like any other position.
	 */
	public function testMovesOnSolvedPuzzleAreRestored(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');

		// On a solved puzzle the board is open for inspection - make a move.
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");

		// The move must be persisted even though the puzzle is solved (the save
		// lands on the next tick, after the interaction settles).
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"return !!localStorage.getItem('tsumegoHero.playState.1.' + window.tsumegoID);");
		});

		// Refresh: the board must be restored.
		$browser->get($url);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 1;');
		});
		$this->assertGreaterThanOrEqual(1,
			(int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'A move made on a solved puzzle must be restored after refresh');
	}

	/**
	 * Resetting out of a restored wrong variation is not free - the user has
	 * made progress, so the reset costs one heart, same as resetting after any
	 * played move.
	 */
	public function testResetAfterRefreshToEscapeCostsHeart(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');

		// Enter the wrong variation and leave before it is completed.
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});
		$browser->get($url);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length === 2;');
		});

		// Resetting to escape the restored wrong line must cost a heart.
		$browser->clickId('besogo-reset-button');
		$browser->waitForSubmitResult();
		$this->assertSame(1, (int) $context->reloadUser()['damage'],
			'Resetting out of a restored wrong variation must not be free');
	}

	/**
	 * Once a wrong variation has been paid for, a refresh starts fresh instead
	 * of restoring the old line, so the same mistake can never cost two hearts.
	 */
	public function testPaidFailClearsSavedStateAndRefreshStartsFresh(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});

		// Complete the wrong variation: one heart, and the saved state is gone.
		$failing = json_decode($browser->driver->executeScript(
			"var n = besogo.editor.getCurrent(); var c = n.children[0]; "
			. "return c && c.move ? JSON.stringify({x: c.move.x, y: c.move.y}) : 'null';"), true);
		$this->assertNotNull($failing, 'There must be a continuation move that fails the variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$failing['x']}, {$failing['y']}, false, false);");
		$browser->waitForSubmitResult();

		$this->assertSame(1, (int) $context->reloadUser()['damage'],
			'Completing the wrong variation must cost one heart');
		$this->assertNull($browser->driver->executeScript(
			"return localStorage.getItem('tsumegoHero.playState.1.' + window.tsumegoID);"),
			'A paid fail must clear the saved play state');

		// Refresh: fresh board, no restored line, and no second heart.
		$browser->get($url);
		$this->waitForEditor($browser);
		$this->assertSame(0, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'After a paid fail, refreshing must start fresh instead of restoring the old line');
		$this->assertSame(1, (int) $context->reloadUser()['damage'],
			'Refreshing after a paid fail must not charge a second heart');
	}

	/**
	 * Returning when the state was saved inside the opponent's autoplay delay
	 * window resumes the opponent's response, so the board settles like normal
	 * play instead of staying stuck on the solver's move.
	 */
	public function testRefreshMidAutoplayWindowResumesOpponentResponse(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});

		// Simulate leaving inside the autoplay delay window: the saved line ends on
		// the solver's move only, with the opponent response still pending.
		$browser->driver->executeScript(
			"var key = 'tsumegoHero.playState.1.' + tsumegoID;"
			. "var s = JSON.parse(localStorage.getItem(key));"
			. "s.path = s.path.slice(0, 1);"
			. "localStorage.setItem(key, JSON.stringify(s));");

		// Return: the board restores to the solver's move and the opponent responds.
		$browser->get($url);
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"return typeof besogo !== 'undefined' && !!besogo.editor && besogo.editor.getCurrentPath().length >= 2;");
		});
		$this->assertSame(2, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'A mid-window leave must resume the opponent response on return');
		$this->assertSame(0, (int) $context->reloadUser()['damage'],
			'Resuming the opponent response must not penalize by itself');
	}

	/**
	 * The saved move line maps back onto the board only under the same corner it
	 * was played with, so returning reuses that corner.
	 */
	public function testCornerUsedForSavedLineIsReusedOnReturn(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$corner = $browser->driver->executeScript('return besogo.editor.getCorner();');
		$this->assertIsString($corner, 'The board must have a corner');

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");

		$browser->get($url);
		$this->waitForEditor($browser);
		$this->assertSame($corner, $browser->driver->executeScript('return besogo.editor.getCorner();'),
			'The corner used for the saved line must be reused on return');
	}

	/**
	 * Off-tree moves made during post-solve exploration restore even when the
	 * page comes back under the opposite color orientation; they are replayed
	 * with the auto color so the line stays consistent with the loaded
	 * orientation.
	 */
	public function testOffTreeMoveRestoresUnderOppositeColorOrientation(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url . '?playercolor=black');
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');

		// Enter the in-tree wrong variation, then play an off-tree move (not in the SGF).
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});
		$browser->driver->executeScript('besogo.editor.click(3, 3, false, false);');
		$savedLength = (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;');
		$this->assertGreaterThanOrEqual(3, $savedLength,
			'The off-tree move must be part of the saved line');

		// Return under the opposite color orientation (the tree is color-inverted).
		$browser->get($url . '?playercolor=white');
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"return typeof besogo !== 'undefined' && !!besogo.editor && besogo.editor.getCurrentPath().length >= 3;");
		});

		// The whole line (in-tree + off-tree) must be restored, and moves must still
		// alternate colors in the inverted tree (auto-color replay).
		$this->assertSame($savedLength, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'The off-tree move must restore even when the color orientation flips');
		$this->assertTrue($browser->driver->executeScript(
			"var p = besogo.editor.getCurrentPath();"
			. "return p.length >= 3 && p[p.length - 1].color === -p[p.length - 2].color;"),
			'Off-tree moves must use the color consistent with the loaded orientation');
	}

	/**
	 * The board rotation chosen with the spin button is part of the persisted
	 * state: returning after a rotate replays the line in the same rotated
	 * frame, so the board comes back exactly as it was left.
	 */
	public function testRotationSurvivesRefresh(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]);
		$browser = Browser::instance();
		$url = $this->playUrl($context);
		$browser->get($url);
		$this->waitForEditor($browser);

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});

		// Rotate the board with the spin button; the state is re-saved with the
		// rotation and the line in the rotated frame.
		$browser->clickId('boardSpinClockwise');
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"var s = JSON.parse(localStorage.getItem('tsumegoHero.playState.1.' + tsumegoID));"
				. "return s && s.rotation !== -1;");
		});
		$rotation = (int) $browser->driver->executeScript('return besogo.editor.getRotation();');
		$this->assertNotSame(-1, $rotation, 'Rotating the board must change the rotation');

		// Return: the board comes back rotated, with the line still in-tree.
		$browser->get($url);
		$wait->until(function ($driver) {
			return $driver->executeScript(
				"return typeof besogo !== 'undefined' && !!besogo.editor && besogo.editor.getCurrentPath().length >= 2;");
		});
		$this->assertSame($rotation, (int) $browser->driver->executeScript('return besogo.editor.getRotation();'),
			'The board must return with the same rotation');
		$this->assertFalse($browser->driver->executeScript('return !!besogo.editor.getCurrent().localEdit;'),
			'The restored line must be part of the tree, not replayed as loose moves');
	}

	/**
	 * Rating mode hands out a new random problem on every load, so it never
	 * persists the board: playing moves saves nothing.
	 */
	public function testRatingModeDoesNotPersistBoardState(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumegos' => [['rating' => 1000, 'description' => 'rating tsumego', 'set_order' => 1, 'sgf' => self::WRONG_VARIATION_SGF]]]);
		$browser = Browser::instance();
		$browser->get('/ratingMode');
		$this->waitForEditor($browser);
		$tsumegoId = (int) $browser->driver->executeScript('return window.tsumegoID;');
		$this->assertGreaterThan(0, $tsumegoId, 'Rating mode must serve a problem');

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');

		// Playing a move must not persist anything.
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$this->assertNull($browser->driver->executeScript(
			"return localStorage.getItem('tsumegoHero.playState.2.' + tsumegoID);"),
			'Rating mode must not save any play state');
	}

	/**
	 * In time mode a refresh re-serves the same queued attempt, so the board
	 * restores mid-solve rather than resetting (which would also reset the
	 * elapsed-time counter used for points). The state is scoped to the current
	 * session only.
	 */
	public function testTimeModeRefreshRestoresBoardWithinSession(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => $this->timeModeTsumegos()]);
		$browser = Browser::instance();
		$browser->get('timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);
		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->waitForEditor($browser);

		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])['TimeModeSession'];

		// Play the first move of the wrong variation (no fail committed yet).
		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});

		// The state must be saved under the single time-mode slot and scoped to
		// the current session (the session id lives inside the state).
		$this->assertNotNull($browser->driver->executeScript(
			"return localStorage.getItem('tsumegoHero.playState.3');"),
			'Time mode must save the current puzzle state');
		$this->assertSame((int) $session['id'], (int) $browser->driver->executeScript(
			"return JSON.parse(localStorage.getItem('tsumegoHero.playState.3')).sessionId;"),
			'The saved state must be scoped to the current session');

		// Refresh: the same attempt is re-served, so the board must be restored.
		$browser->get('timeMode/play');
		$this->waitForEditor($browser);
		$this->assertGreaterThanOrEqual(2,
			(int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'Refreshing in time mode must restore the mid-variation board');
	}

	/**
	 * Time-mode persistence only survives a refresh of the current session: a
	 * later session that happens to contain the same problem starts fresh,
	 * because the saved state carries the session id and is never restored
	 * across sessions.
	 */
	public function testTimeModeNewSessionDoesNotRestoreOldState(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => $this->timeModeTsumegos()]);
		$browser = Browser::instance();
		$startUrl = 'timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id'];

		// Session 1: play a move so a real state is saved.
		$browser->get($startUrl);
		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->waitForEditor($browser);
		$oldSession = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])['TimeModeSession'];

		$wrong = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong, 'The SGF must contain an in-tree wrong variation');
		$browser->driver->executeScript(
			"besogo.editor.click({$wrong['x']}, {$wrong['y']}, false, false);");
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return besogo.editor.getCurrentPath().length >= 2;');
		});
		$this->assertSame((int) $oldSession['id'], (int) $browser->driver->executeScript(
			"return JSON.parse(localStorage.getItem('tsumegoHero.playState.3')).sessionId;"),
			'Session 1 must save the state scoped to itself');

		// Session 2: a brand-new session must start fresh.
		$browser->get($startUrl);
		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->waitForEditor($browser);
		$newSession = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])['TimeModeSession'];
		$this->assertNotSame($oldSession['id'], $newSession['id'], 'A new session must be created');
		$newTsumegoId = (int) $browser->driver->executeScript('return window.tsumegoID;');
		$this->assertSame(0, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'A new time-mode session must start fresh, not restore the old line');

		// Deterministic scoping check: a state that is fully valid for the
		// current problem (right tsumego, SGF, corner and path) is restored only
		// when it is scoped to the CURRENT session, never the old one.
		$wrong2 = $this->wrongVariationMove($browser);
		$this->assertNotNull($wrong2, 'The SGF must contain an in-tree wrong variation');
		$corner2 = $browser->driver->executeScript('return besogo.editor.getCorner();');
		$newSgfId = (int) ClassRegistry::init('Sgf')->find('first', [
			'conditions' => ['tsumego_id' => $newTsumegoId]])['Sgf']['id'];
		$this->assertGreaterThan(0, $newSgfId, 'The current problem must have an SGF');

		$plantState = function (int $sessionId) use ($browser, $newTsumegoId, $newSgfId, $corner2, $wrong2): void {
			$pathJson = json_encode([['x' => $wrong2['x'], 'y' => $wrong2['y'], 'color' => $wrong2['color']]]);
			$browser->driver->executeScript(
				"localStorage.setItem('tsumegoHero.playState.3', JSON.stringify({"
				. "tsumegoId: $newTsumegoId, mode: 3, sessionId: $sessionId, sgfId: $newSgfId, corner: " . json_encode($corner2)
				. ", path: " . $pathJson . "}));");
		};

		$plantState((int) $oldSession['id']);
		$browser->get('timeMode/play');
		$this->waitForEditor($browser);
		$this->assertSame(0, (int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'A state scoped to the old session must not be restored');

		$plantState((int) $newSession['id']);
		$browser->get('timeMode/play');
		$this->waitForEditor($browser);
		$this->assertGreaterThanOrEqual(1,
			(int) $browser->driver->executeScript('return besogo.editor.getCurrentPath().length;'),
			'The same state scoped to the current session must be restored');
	}

	private function timeModeTsumegos(): array
	{
		$tsumegos = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT; ++$i)
			$tsumegos[] = ['set_order' => $i + 1, 'sgf' => self::WRONG_VARIATION_SGF];
		return $tsumegos;
	}
}
