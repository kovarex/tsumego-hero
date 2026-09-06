<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Exception\TimeoutException;
use PHPUnitRetry\RetryTrait;

App::uses('Util', 'Utility');
App::uses('Constants', 'Utility');

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class PlayGameplayBrowserTest extends TestCaseWithAuth
{
	use RetryTrait;

	/** SGF with a 19x19 board and 3 clearly positioned setup stones. */
	private const SGF_19 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc][dd]AW[ee];B[aa];W[ab];B[ba]C[+])';

	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	private function waitForBoard(Browser $browser): void
	{
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor && typeof besogo.editor.getCurrent === "function"');
	}

	/**
	 * Read the current node's stones as [x, y, color] triplets in *logical*
	 * board coordinates. Child nodes inherit getStone/getSize via
	 * Object.create(this), so this is reliable on the play page.
	 */
	private function logicalStones(Browser $browser): array
	{
		$json = $browser->driver->executeScript(
			'var node = besogo.editor.getCurrent();'
			. 'var size = node.getSize();'
			. 'var stones = [];'
			. 'for (var y = 1; y <= size.y; y++) {'
			. '  for (var x = 1; x <= size.x; x++) {'
			. '    var c = node.getStone(x, y);'
			. '    if (c) stones.push([x, y, c]);'
			. '  }'
			. '}'
			. 'return stones;'
		);

		return (array) $json;
	}

	public function testBoardRendersExpectedSizeAndSetupStones(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'renderUser',
				// Pin the player color and board orientation so the board is never
				// swapped or rotated (deterministic stone coordinates and colors).
				'pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL,
				'pref_board_orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL,
			],
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		// Wait until the SGF has actually been parsed and the setup stones placed.
		$browser->waitUntilJs('besogo.editor.getCurrent().getStone(3, 3) !== 0');

		$size = $browser->driver->executeScript('return besogo.editor.getCurrent().getSize();');

		// The puzzle board must be 19x19 (the board the SGF declares).
		$this->assertSame(19, (int) $size['x'], 'Board width should be 19');
		$this->assertSame(19, (int) $size['y'], 'Board height should be 19');

		// The three setup stones must be present at their authored coordinates.
		$stones = $this->logicalStones($browser);
		$this->assertCount(3, $stones, 'Three setup stones should be rendered');

		$keyed = [];
		foreach ($stones as $stone)
			$keyed[$stone[0] . ',' . $stone[1]] = $stone[2];

		// cc -> (3,3) black(-1), dd -> (4,4) black(-1), ee -> (5,5) white(1)
		$this->assertSame(-1, $keyed['3,3'], 'Black stone at cc');
		$this->assertSame(-1, $keyed['4,4'], 'Black stone at dd');
		$this->assertSame(1, $keyed['5,5'], 'White stone at ee');
	}

	public function testPassButtonAppearsWhenEnabledAndAdvancesMove(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'passUser'],
			'tsumego' => ['set_order' => 1, 'pass' => 1, 'sgf' => self::SGF_19],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$this->assertTrue($browser->idExists('besogo-pass-button'),
			'Pass button should be rendered when pass is enabled');

		$moveBefore = (int) $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');

		$browser->clickId('besogo-pass-button');

		$moveAfter = (int) $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');
		$this->assertGreaterThan($moveBefore, $moveAfter,
			'Passing should advance the move number');

		$this->assertSame(0, $browser->driver->executeScript('return window.boardLockValue;'),
			'Passing should not lock the board');
	}

	public function testPassButtonHiddenByDefault(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'noPassUser'],
			'tsumego' => ['set_order' => 1, 'pass' => 0, 'sgf' => self::SGF_19],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$this->assertFalse($browser->idExists('besogo-pass-button'),
			'Pass button should not be rendered when pass is disabled');
	}

	public function testNextNavigationButtonMovesToNextPuzzle(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'navUser'],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => self::SGF_19],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa]C[+])'],
			],
		]);
		$browser = Browser::instance();
		$firstUrl = $this->playUrl($context);
		$secondUrl = '/' . $context->tsumegos[1]['set-connections'][0]['id'];
		$browser->get($firstUrl);
		$this->waitForBoard($browser);

		// Navigate to the next problem via the besogo Next button.
		$browser->clickId('besogo-next-button');

		$browser->waitUntilJs('location.href.includes("' . $secondUrl . '")');

		$this->assertStringContainsString($secondUrl, $browser->driver->getCurrentURL(),
			'Clicking Next should navigate to the next puzzle');
	}

	public function testPreviousNavigationButtonMovesToPreviousPuzzle(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'prevUser'],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => self::SGF_19],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa]C[+])'],
			],
		]);
		$browser = Browser::instance();
		$firstUrl = $this->playUrl($context);
		$secondUrl = '/' . $context->tsumegos[1]['set-connections'][0]['id'];
		$browser->get($secondUrl);
		$this->waitForBoard($browser);

		$browser->clickId('besogo-back-button');

		$browser->waitUntilJs('location.href.includes("' . $firstUrl . '")');

		$this->assertStringContainsString($firstUrl, $browser->driver->getCurrentURL(),
			'Clicking Back should navigate to the previous puzzle');
	}

	public function testMultipleChoiceAnswerClickShowsSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'mcUser'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF_19,
				'variants' => [[
					'type' => 'multiple_choice',
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'numAnswer' => '3',
				]],
			],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$browser->waitUntilJs('document.getElementById("besogo-multipleChoice3") !== null');

		// numAnswer=3, so choosing the third answer is correct.
		$browser->clickId('besogo-multipleChoice3');

		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Correct")');

		$this->assertStringContainsString('Correct', $browser->find('#status')->getText(),
			'Choosing the correct answer should show a Correct banner');
	}

	public function testScoreEstimatingAnswerClickShowsSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'seUser', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF_19,
				'variants' => [[
					'type' => 'score_estimating',
					'answer1' => '6.5',
					'answer2' => '3',
					'answer3' => '6',
					'winner' => '0',
					'numAnswer' => '0',
				]],
			],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$browser->waitUntilJs('document.getElementById("besogo-se-black") !== null');

		// Pick Black wins (sets the estimate to B+0), then submit it. The winning
		// answer of '0' (jigo) matches, so the result should be Correct.
		$browser->clickId('besogo-se-black');
		$browser->clickId('submitScoreEstimatingSE');

		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Correct")');

		$this->assertStringContainsString('Correct', $browser->find('#status')->getText(),
			'Submitting a correct score estimate should show a Correct banner');
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

	public function testResetAfterFailDoesntCauseDuplicateDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail once
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'First fail should cause damage');

		// Reset - should NOT cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset after fail should not cause duplicate damage');
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

	public function testResetAfterSolveDoesNotCostHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');
		$browser->clickId('besogo-reset-button');

		$this->assertSame(0, $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
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

	public function testSolveByClicking()
	{
		foreach ([false, true] as $isGuest)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => $isGuest ? ['name' => 'testuser'] : ['mode' => Constants::$LEVEL_MODE],
				'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])']]);

			if ($isGuest)
			{
				$this->logout();
				$this->assertFalse(Auth::isLoggedIn(), 'Should not be logged in for guest test');
			}
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$browser->clickBoard(1, 1);
			// Wait for white's auto-response (move number advances to 2)
			$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
			$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			$browser->clickBoard(2, 1);
			$browser->waitUntilJs('window.problemSolved === true');
			$this->assertSame(true, $browser->driver->executeScript('return window.problemSolved;'));
		}
	}

	public function testResetAddsFailWhenSomethingWasPlayed()
	{
		foreach (['', 'no-move', 'already-solved'] as $testCase)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
				'tsumego' => [
					'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])',
					'rating' => 1000,
					'set_order' => 1,
					'status' => ($testCase == 'already-solved' ? 'S' : 'V')]]);
			$browser = Browser::instance();
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

			if ($testCase != 'no-move')
			{
				// click one move
				$browser->clickBoard(1, 1);
				// Wait for white's auto-response (move number advances to 2)
				$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
				if ($testCase != 'already-solved')
					$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			}

			// reset without the result being shown
			$browser->clickId('besogo-reset-button');

			$expectedRatingChange = ($testCase == '') ? Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER) : 0;
			$expectedRating = round(1000 + $expectedRatingChange);
			$displayedRating = $browser->driver->executeScript('return window.accountWidget ? accountWidget.rating : null;');
			$this->assertEquals($expectedRating, round($displayedRating));

			// changes are applied after refresh
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertSame(($testCase == '') ? 1 : 0, $context->reloadUser()['damage']);
			$this->assertLessThan(0.1, abs($context->reloadUser()['rating'] - (1000 + $expectedRatingChange)));
		}
	}

	/**
	 * When user fails a problem, the board should NOT lock.
	 * User should be able to continue clicking (though they won't solve it after first failure).
	 */
	public function testBoardDoesntLockAfterFailAllowsContinuedAttempts()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa];W[ab];B[ca]C[+])']]);

		$browser = Browser::instance();
		$tsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get($tsumegoUrl);

		// Wait for board to initialize (window.besogo exists)
		$browser->waitUntilJs('typeof window.besogo !== "undefined"');

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$browser->waitUntilJs('document.getElementById("status").innerHTML.includes("Incorrect")');

		// Verify board shows failure state
		$statusAfterWrong = $browser->driver->executeScript("return document.getElementById('status').innerHTML;");
		$this->assertStringContainsString("Incorrect", $statusAfterWrong, "Should show 'Incorrect' after wrong move");

		// Verify board is NOT locked (boardLockValue should be 0)
		$boardLockValue = $browser->driver->executeScript("return window.boardLockValue;");
		$this->assertEquals(0, $boardLockValue, "Board should NOT be locked after wrong move");

		// Verify user can still click (board stays interactive)
		// We don't expect to solve the puzzle after failure, just verify clicks still work
		$browser->clickBoard(1, 1);

		// Brief wait to ensure click was processed
		$browser->waitUntilJs('document.readyState === "complete"');

		// Verify still on same problem (didn't reset or advance)
		$this->assertStringContainsString($tsumegoUrl, $browser->driver->getCurrentURL(), "Should stay on same problem");

		// refresh on the tsumego and check just one health was removed
		$browser->get($tsumegoUrl);
		$this->assertSame(1, $context->reloadUser()['damage']);
	}

	public function testBoardStatusIsProperlyUpdatedAfterFailResetAndFail()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa];W[ab];B[ca]C[+])']]);

		$tsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get($tsumegoUrl);

		// Wait for board to initialize (window.besogo exists)
		$browser->waitUntilJs('typeof window.besogo !== "undefined"');

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Incorrect")');

		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->waitForSubmitResult();
		$browser->clickId('besogo-reset-button');
		$this->assertStringContainsString("", $browser->find('#status')->getText());
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Incorrect")');
		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->waitForSubmitResult();
		$browser->get($tsumegoUrl);
		$this->assertSame(2, $context->reloadUser()['damage']); // 2 errors done
	}

	// When user solves a problem, clicking the board should navigate to next problem.
	public function testClickingBoardAfterSuccessNavigatesToNextPuzzle()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])'],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa]C[+])']]]);

		$browser = Browser::instance();
		$firstTsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$secondTsumegoUrl = $context->tsumegos[1]['set-connections'][0]['id'];
		$browser->get($firstTsumegoUrl);

		// Solve the puzzle by making correct moves (this SGF requires 2 clicks)
		$browser->clickBoard(1, 1); // First move
		// Wait for white's auto-response (move number advances to 2)
		$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(2, 1); // Second move that solves it
		$browser->waitUntilJs('window.problemSolved === true');

		// Verify puzzle is solved
		$problemSolved = $browser->driver->executeScript("return window.problemSolved;");
		$this->assertTrue($problemSolved, "problemSolved should be true");

		// Verify boardLockValue is set
		$boardLockValue = $browser->driver->executeScript("return window.boardLockValue;");
		$this->assertEquals(1, $boardLockValue, "Board should be locked after success");

		// Click on board to navigate to next puzzle (use position near existing stones)
		$browser->clickBoard(1, 2); // Click near the solved area
		// Wait for navigation to next puzzle
		$browser->waitUntilJs('location.href.includes("' . $secondTsumegoUrl . '")');

		// Verify we navigated to the next puzzle
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString($secondTsumegoUrl, $currentUrl, "Should navigate to next puzzle");
	}

	/**
	 * When in tags query mode but the lastSet cookie contains a value that
	 * isn't a valid tag name, the play page should fall back to topics
	 * view instead of crashing.
	 */
	public function testPlayPageFallsBackToTopicsWhenLastSetCookieIsNotAValidTag(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => 1]]],
		]);
		$browser = Browser::instance();
		$browser->setCookie('lastSet', 'nonexistent-tag');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$playTitle = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $playTitle, 'Play page should render with playTitle element');
	}

	public function testRootCommentIsDisplayedOnInitialLoad()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]SZ[19]C[Go Seigen 4p vs Kubomatsu 6p]AB[cc];B[aa];W[ab];B[ba]C[+])',
			],
		]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$theComment = $browser->driver->findElement(WebDriverBy::id('theComment'));
		$this->assertSame('Go Seigen 4p vs Kubomatsu 6p', $theComment->getText());
		$this->assertTrue($theComment->isDisplayed(), 'Root comment should be visible on initial page load');

		$xpDisplayDiv = $browser->driver->findElement(WebDriverBy::id('xpDisplayDiv'));
		$this->assertTrue($xpDisplayDiv->isDisplayed(), 'xpDisplayDiv must be visible on initial page load');
	}

	public function testTheNextAndBackButtonLinksWhenBothPointToOtherTsumegos()
	{
		$context = new ContextPreparator(['tsumegos' => [1, 2, 3]]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[1]['set-connections'][0]['id']);
		$backButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-back-button'));
		$this->assertSame($backButton->getAttribute('href'), '/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$nextButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'));
		$this->assertSame($nextButton->getAttribute('href'), '/' . $context->tsumegos[2]['set-connections'][0]['id']);
	}

	public function testShowFullHearts()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(0, $emptyHearts);
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']), $fullHearts);
	}

	public function testShowFullPartialHearts()
	{
		$context = new ContextPreparator(['user' => ['damage' => '1'], 'tsumego' => 1]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(1, $emptyHearts);
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']) - 1, $fullHearts);
	}

	public function testShowHeartsWithDamageHigherThanHealth()
	{
		$context = new ContextPreparator(['user' => ['damage' => '10000'], 'tsumego' => 1]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']), $emptyHearts);
		$this->assertCount(0, $fullHearts);
	}

	public function testFavoritesHeartTogglesAddAndRemove(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

		// Heart is rendered for logged-in users
		$this->assertTrue($browser->idExists('favButton'));

		// First click adds the tsumego to the lazily-created Favorites set.
		// Wait until the client has registered the new Favorites set, which only
		// happens after the add request completes - this also guarantees the next
		// click toggles it back off instead of re-adding (409).
		$browser->clickId('favButton');
		$this->waitForClientFavoritesState($browser);

		$favoritesSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
		]);
		$this->assertNotEmpty($favoritesSet, 'First heart click should create the Favorites set');
		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $favoritesSet['Set']['id'], 'tsumego_id' => $tsumegoId],
		]), 'First heart click should add the tsumego to Favorites');

		// Second click removes it again
		$browser->clickId('favButton');
		$this->waitForConnectionCount($browser, $favoritesSet['Set']['id'], $tsumegoId, 0,
			'Second heart click should remove the tsumego from Favorites');
	}

	private function waitForClientFavoritesState(Browser $browser): void
	{
		try
		{
			$browser->waitUntilJs('!!window.userSets && window.userSets.some(s => s.contains)', 5);
		}
		catch (TimeoutException $e)
		{
			$this->fail('First heart click should add the tsumego to Favorites');
		}
	}

	private function waitForConnectionCount(Browser $browser, int $setId, int $tsumegoId, int $expected, string $message): void
	{
		try
		{
			new WebDriverWait($browser->driver, 5, 200)->until(function () use ($setId, $tsumegoId, $expected) {
				return ClassRegistry::init('SetConnection')->find('count', [
					'conditions' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId],
				]) === $expected;
			});
		}
		catch (TimeoutException $e)
		{
			$this->fail($message);
		}
	}

	public function testCommentCoordinatesHaveHoverSpans()
	{		// Create a tsumego with a comment containing coordinates
		// Admin so comments are visible
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => 1]);

		// Add a comment with coordinates
		$comment = ClassRegistry::init('TsumegoComment');
		$comment->save([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'message' => 'Try playing at R19 or S18, they both work.',
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

		$browser->expandComments();

		// Check that coordinate spans exist in the HTML
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('go-coord', $pageSource);

	}

	public function testFailingWithLastHeartLocksBoardAndShowsLockMessage()
	{
		// Create a tsumego with a comment containing coordinates
		$context = new ContextPreparator([
			'user' => ['premium' => true, 'health' => 0], // 0 hearts left - fail should lock
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$context->unlockAchievementsWithoutEffect(); // avoiding premium achievement increasing level and health
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$this->assertTextContains("This problem is locked until", $browser->driver->getPageSource());
		$this->assertSame(true, $browser->driver->executeScript("return window.tryAgainTomorrow;"));
		$this->assertSame(1, $browser->driver->executeScript("return window.boardLockValue;"));
		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) {
			return 0;
		}, function ($index) {
			return 1;
		}, 0, 'F');
	}

	/**
	 * Browser test: clicking the color-orientation button inverts the board and
	 * swaps the multiple-choice answer text so it matches the visual stones.
	 */
	public function testOrientationButtonSwapsVariantAnswers(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play. What is the result?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AW[dd];B[aa];W[ab];B[ba]C[+])',
				'variants' => [[
					'type' => 'multiple_choice',
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'numAnswer' => '3',
				]],
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("besogo-multipleChoice1") !== null');

		$this->assertSame('White is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('Black is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));
		$this->assertSame('White to play. What is the result?', $browser->find('#descriptionText')->getText());
	}

	public function testOrientationButtonSwapsScoreButtons(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Who wins?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
				'variants' => [[
					'type' => 'score_estimating',
					'answer1' => '6.5',
					'answer2' => '3',
					'answer3' => '6',
					'numAnswer' => '0',
				]],
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("besogo-se-black") !== null');

		$this->assertSame('Black wins', $browser->find('#besogo-se-black')->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('White wins', $browser->find('#besogo-se-black')->getAttribute('value'));
		$this->assertSame('Black wins', $browser->find('#besogo-se-white')->getAttribute('value'));
	}

	/**
	 * Browser test: the orientation button swaps the semeai "Black is dead" /
	 * "White is dead" buttons so they match the inverted board.
	 */
	public function testOrientationButtonSwapsSemeaiButtons(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'semeai_type' => 1,
				'min_lib' => 0,
				'max_lib' => 2,
				'liberty_count' => 6,
				'variance' => 2,
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[hm][im][gn][jn][go][jo][gp][jp][eq][gq][jq][fr][jr][gs][js]AB[jm][km][in][ln][io][lo][ip][lp];B[aa];W[ab];B[ba]C[+])',
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("besogo-multipleChoice1") !== null');

		$button1 = $browser->find('#besogo-multipleChoice1');
		$button2 = $browser->find('#besogo-multipleChoice2');
		$this->assertSame('Black is dead', $button1->getAttribute('value'));
		$this->assertSame('White is dead', $button2->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('White is dead', $button1->getAttribute('value'));
		$this->assertSame('Black is dead', $button2->getAttribute('value'));
	}

	/**
	 * Browser test: the orientation button swaps the SGF comment text so it matches
	 * the inverted board.
	 */
	public function testOrientationButtonSwapsSgfComment(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]C[Black to play. Save the black group.];B[aa];W[ab];B[ba]C[+])',
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("theComment") !== null');

		$comment = $browser->find('#theComment');
		$this->assertStringContainsString('Black to play', $comment->getText());

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertStringContainsString('White to play', $comment->getText());
	}
}
