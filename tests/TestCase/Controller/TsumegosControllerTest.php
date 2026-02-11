<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

class TsumegosControllerTest extends TestCaseWithAuth
{
	public function testSetNameAndNumIsVisible()
	{
		foreach ([false, true] as $openBySetConnectionID)
		{
			$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);
			$this->testAction(
				$openBySetConnectionID
				? ('/' . $context->tsumegos[0]['set-connections'][0]['id'])
				: ('tsumegos/play/' . $context->tsumegos[0]['id']),
				['return' => 'view']);
			$this->assertTextContains("test set", $this->view);

			$dom = $this->getStringDom();
			$href = $dom->querySelector('#playTitleA');
			$this->assertTextContains('test set', $href->textContent);
			$this->assertTextContains('666', $href->textContent);
		}
	}

	public function testViewingTsumegoInMoreSets()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);
		$tsumegoID = $context->tsumegos[0]['id'];
		$this->testAction('tsumegos/play/' . $tsumegoID, ['return' => 'view']);

		// The first one was selected into the title
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);

		$duplicateTable = $dom->querySelector('.duplicateTable');
		$links = $duplicateTable->getElementsByTagName('a');
		$this->assertSame(count($links), 2);
		$this->assertTextContains('/' . $context->tsumegos[0]['set-connections'][0]['id'], $links[0]->getAttribute('href'));
		$this->assertTextContains('tsumego set 1', $links[0]->textContent);
		$this->assertTextContains('666', $links[0]->textContent);
		$this->assertTextContains('/' . $context->tsumegos[0]['set-connections'][1]['id'], $links[1]->getAttribute('href'));
		$this->assertTextContains('tsumego set 2', $links[1]->textContent);
		$this->assertTextContains('777', $links[1]->textContent);
	}

	public function testViewingTsumegoInMoreSetsAndSpecifyingWhichOneIsTheMainOne()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?sid=' . $context->tsumegos[0]['sets'][1]['id'], ['return' => 'view']);

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

	public function testViewingTsumegoWithoutAnySGF()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('test set', $href->textContent);
		$this->assertTextContains('666', $href->textContent);
	}

	// testing the same things as testViewingTsumegoInMoreSets, but using the web driver to do so
	// if this test fails, it probably means something is wrong with the web driver configuration
	public function testViewingTsumegoInMoreSetsUsingWebDriver()
	{
		$context = new ContextPreparator(['tsumego' => [
			'sets' => [['name' => 'tsumego set 1', 'num' => '666'], ['name' => 'tsumego set 2', 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$href = $browser->driver->findElement(WebDriverBy::cssSelector('#playTitleA'));
		$this->assertTextContains('set 1', $href->getText());
		$this->assertTextContains('666', $href->getText());
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

	public function testCommentCoordinatesHaveHoverSpans()
	{
		// Create a tsumego with a comment containing coordinates
		// Admin so comments are visible
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => 1]);

		// Add a comment with coordinates
		$comment = ClassRegistry::init('TsumegoComment');
		$comment->save([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'message' => 'Try playing at R19 or S18, they both work.',
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		// Check that coordinate spans exist in the HTML
		$this->assertTextContains('go-coord', $this->view);
		$this->assertTextContains('onmouseover="ccIn', $this->view);

		// Check the JavaScript functions are generated
		$this->assertTextContains('function ccIn', $this->view);
		$this->assertTextContains('showCoordPopup', $this->view);
	}

	public function testFailingWithLastHeartLocksBoardAndShowsTryAgainTomorrow()
	{
		// Create a tsumego with a comment containing coordinates
		$context = new ContextPreparator([
			'user' => ['premium' => true, 'health' => 1], // Admin so comments are visible
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$context->unlockAchievementsWithoutEffect(); // avoiding premium achievement increasing level and health
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$this->assertTextContains("Try again tomorrow", $browser->driver->getPageSource());
		$this->assertSame(true, $browser->driver->executeScript("return window.tryAgainTomorrow;"));
		$this->assertSame(1, $browser->driver->executeScript("return window.boardLockValue;"));
		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) { return 0; }, function ($index) { return 1;}, 0, 'F');
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
			$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
			$wait->until(function ($driver) {
				return $driver->executeScript('return window.besogo && besogo.editor.getCurrent().moveNumber >= 2;');
			});
			$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			$browser->clickBoard(2, 1);
			$wait->until(function ($driver) {
				return $driver->executeScript('return window.problemSolved === true;');
			});
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
				$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
				$wait->until(function ($driver) {
					return $driver->executeScript('return window.besogo && besogo.editor.getCurrent().moveNumber >= 2;');
				});
				if ($testCase != 'already-solved')
					$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			}

			// reset without the result being shown
			$browser->clickId('besogo-reset-button');

			// check that rating on account widget was immediatelly updated
			$expectedRatingChange = ($testCase == '') ? Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER) : 0;
			$browser->driver->executeScript("window.scrollTo(0, 0);");
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(round(1000 + $expectedRatingChange)), $browser->find('#account-bar-xp')->getText());

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
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10);
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript("return typeof window.besogo !== 'undefined';");
		});

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$wait->until(function () use ($browser) {
			$status = $browser->driver->executeScript("return document.getElementById('status').innerHTML;");
			return str_contains($status, "Incorrect");
		});

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
		$wait->until(function () use ($browser) { return $browser->driver->executeScript("return document.readyState === 'complete';"); });

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
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10);
		$wait->until(function () use ($browser) {return $browser->driver->executeScript("return typeof window.besogo !== 'undefined';"); });

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$wait->until(function () use ($browser) { return str_contains($browser->find('#status')->getText(), "Incorrect"); });

		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->clickId('besogo-reset-button');
		$this->assertStringContainsString("", $browser->find('#status')->getText());
		$browser->clickBoard(2, 1);
		$wait->until(function () use ($browser) { return str_contains($browser->find('#status')->getText(), "Incorrect"); });
		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
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
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return window.besogo && besogo.editor.getCurrent().moveNumber >= 2;');
		});
		$browser->clickBoard(2, 1); // Second move that solves it
		$wait->until(function ($driver) {
			return $driver->executeScript('return window.problemSolved === true;');
		});

		// Verify puzzle is solved
		$problemSolved = $browser->driver->executeScript("return window.problemSolved;");
		$this->assertTrue($problemSolved, "problemSolved should be true");

		// Verify boardLockValue is set
		$boardLockValue = $browser->driver->executeScript("return window.boardLockValue;");
		$this->assertEquals(1, $boardLockValue, "Board should be locked after success");

		// Click on board to navigate to next puzzle (use position near existing stones)
		$browser->clickBoard(1, 2); // Click near the solved area
		// Wait for navigation to next puzzle
		$wait->until(function ($driver) use ($secondTsumegoUrl) {
			return str_contains($driver->getCurrentURL(), $secondTsumegoUrl);
		});

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

	public static function descriptionColorSwapProvider(): array
	{
		$blackFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		return [
			'no swap: Black visual + Black-first SGF' => [
				"Black's stones attack White's group. black wins!",
				$blackFirstSgf,
				'black',
				"Black's stones attack White's group. black wins!",
			],
			'swap: White visual + Black-first SGF, preserves word boundaries' => [
				"Black's stones. Kill the white group near the Blackbird. Watch whitespace.",
				$blackFirstSgf,
				'white',
				"White's stones. Kill the black group near the Blackbird. Watch whitespace.",
			],
			'swap: Black visual + White-first SGF' => [
				"White to attack Black's group.",
				$whiteFirstSgf,
				'black',
				"Black to attack White's group.",
			],
			'no swap: White visual + White-first SGF' => [
				"White to attack Black's group.",
				$whiteFirstSgf,
				'white',
				"White to attack Black's group.",
			],
		];
	}

	/**
	 * @dataProvider descriptionColorSwapProvider
	 */
	public function testDescriptionColorSwap(string $description, string $sgf, string $playerColor, string $expected): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'description' => $description,
				'sgf' => $sgf,
			]
		]);

		$this->testAction(
			'tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=' . $playerColor,
			['return' => 'view']
		);

		$this->assertTextContains($expected, $this->view);
	}

	/**
	 * Edit form shows ORIGINAL description (never swapped).
	 */
	public function testDescriptionEditFormShowsOriginal(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to attack the white stones.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'
			]
		]);

		$this->testAction(
			'tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white',
			['return' => 'view']
		);

		$this->assertTextContains('>Black to attack the white stones.</textarea>', $this->view);
	}

	public static function startingPlayerProvider(): array
	{
		return [
			'Black first' => ['(;GM[1]FF[4]SZ[19];B[aa];W[ab])', 0],
			'White first' => ['(;GM[1]FF[4]SZ[19];W[aa];B[ab])', 1],
			'Black only' => ['(;GM[1]FF[4]SZ[19];B[aa])', 0],
			'White only' => ['(;GM[1]FF[4]SZ[19];W[aa])', 1],
		];
	}

	/**
	 * @dataProvider startingPlayerProvider
	 */
	public function testGetStartingPlayer(string $sgf, int $expected): void
	{
		$this->assertSame($expected, TsumegosController::getStartingPlayer($sgf));
	}
}
