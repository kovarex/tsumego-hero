<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

App::uses('NotFoundException', 'Routing/Error');

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

	public function testPageTitleShowsRealSetCount()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => '2']]],
				['sets' => [['name' => 'test set', 'num' => '3']]],
			],
		]);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'contents']);
		$this->assertTextContains('test set 1/3 on Tsumego Hero', $this->contents);
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

	public function testDuplicateGroupShowsOwnPrivateSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'myself'],
			'tsumego' => [
				'sets' => [
					['name' => 'public set', 'public' => 1, 'num' => 10],
					['name' => 'my favorites', 'public' => 0, 'user_id' => 'self', 'num' => 5],
				],
			],
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set', $links[0]->textContent);
		$this->assertTextContains('my favorites', $links[1]->textContent);
	}

	public function testDuplicateGroupShowsOnlyViewableSets()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'myself'],
			'other-users' => [['name' => 'alice']],
			'tsumego' => [
				'sets' => [
					['name' => 'public set 1', 'public' => 1, 'num' => 10],
					['name' => 'public set 2', 'public' => 1, 'num' => 20],
				],
			],
		]);
		$this->addSetForTsumego($context->tsumegos[0]['id'], 'alice favorites', $context->otherUsers[0]['id'], 0, 3);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set 1', $links[0]->textContent);
		$this->assertTextContains('public set 2', $links[1]->textContent);
	}

	public function testDuplicateGroupShowsOnlyPublicSetsForAnonymous()
	{
		$context = new ContextPreparator([
			'user' => null,
			'other-users' => [['name' => 'alice']],
			'tsumego' => [
				'sets' => [
					['name' => 'public set 1', 'public' => 1, 'num' => 10],
					['name' => 'public set 2', 'public' => 1, 'num' => 20],
				],
			],
		]);
		$this->addSetForTsumego($context->tsumegos[0]['id'], 'alice favorites', $context->otherUsers[0]['id'], 0, 3);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set 1', $links[0]->textContent);
		$this->assertTextContains('public set 2', $links[1]->textContent);
	}

	public function testDuplicateGroupShowsSandboxSetForPremiumUser()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'premiumuser', 'premium' => true],
			'tsumego' => [
				'sets' => [
					['name' => 'public set', 'public' => 1, 'num' => 10],
					['name' => 'sandbox set', 'public' => 0, 'num' => 1],
				],
			],
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set', $links[0]->textContent);
		$this->assertTextContains('sandbox set', $links[1]->textContent);
	}

	public function testDuplicateGroupShowsOnlyPublicSetsForRegularUser()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regularuser'],
			'tsumego' => [
				'sets' => [
					['name' => 'public set 1', 'public' => 1, 'num' => 10],
					['name' => 'public set 2', 'public' => 1, 'num' => 20],
					['name' => 'sandbox set', 'public' => 0, 'num' => 1],
				],
			],
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set 1', $links[0]->textContent);
		$this->assertTextContains('public set 2', $links[1]->textContent);
	}

	public function testDuplicateGroupShowsPublicSetsForAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'adminuser', 'admin' => true],
			'other-users' => [['name' => 'alice']],
			'tsumego' => [
				'sets' => [
					['name' => 'public set 1', 'public' => 1, 'num' => 10],
					['name' => 'public set 2', 'public' => 1, 'num' => 20],
				],
			],
		]);
		$this->addSetForTsumego($context->tsumegos[0]['id'], 'alice favorites', $context->otherUsers[0]['id'], 0, 3);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$links = $dom->querySelector('.duplicateTable')->getElementsByTagName('a');
		$this->assertSame(2, count($links));
		$this->assertTextContains('public set 1', $links[0]->textContent);
		$this->assertTextContains('public set 2', $links[1]->textContent);
	}

	public function testDuplicateGroupHidesUserOwnedPublicSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'alice']],
			'tsumego' => [
				'sets' => [
					['name' => 'official public', 'public' => 1, 'num' => 10],
					['name' => 'second public', 'public' => 1, 'num' => 20],
				],
			],
		]);

		$set = ClassRegistry::init('Set');
		$set->create();
		$this->assertNotFalse($set->save(['title' => 'alice public', 'public' => 1, 'user_id' => $context->otherUsers[0]['id'], 'order' => 1]));
		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 3]);

		$titles = array_map(fn($row) => $row['SetConnection']['title'], TsumegoUtil::getSetConnectionsWithTitles($context->tsumegos[0]['id']));
		$this->assertSame(['official public 10', 'second public 20'], $titles);
	}

	public function testPlayResolvesSidToOwnPrivateSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'myself'],
			'tsumego' => [
				'sets' => [
					['name' => 'public set', 'public' => 1, 'num' => 10],
					['name' => 'my favorites', 'public' => 0, 'user_id' => 'self', 'num' => 5],
				],
			],
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?sid=' . $context->tsumegos[0]['sets'][1]['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('my favorites', $href->textContent);
	}

	public function testPlayRequiresViewableSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'myself'],
			'other-users' => [['name' => 'alice']],
		]);

		$tsumego = ClassRegistry::init('Tsumego');
		$tsumego->create();
		$tsumego->save(['description' => 'private only']);
		$this->addSetForTsumego($tsumego->id, 'alice favorites', $context->otherUsers[0]['id'], 0, 1);

		$this->expectException(NotFoundException::class);
		$this->testAction('tsumegos/play/' . $tsumego->id);
	}

	private function addSetForTsumego(int $tsumegoId, string $title, ?int $userId, int $public, int $num): void
	{
		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => $title, 'public' => $public, 'user_id' => $userId, 'order' => 1]);

		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $tsumegoId, 'set_id' => $set->id, 'num' => $num]);
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

	public function testClearFiltersReloadsCurrentPlayPage(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('/tsumegos/play/' . $tsumegoId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k']);
		$browser->getAnonymous('/tsumegos/play/' . $tsumegoId);

		// open the filters panel so the active tiles (and clear button) are visible
		$browser->driver->findElement(WebDriverBy::cssSelector('#showFilters'))->click();
		$browser->waitUntilCssSelectorDisplayed('#unselect-active-tiles');
		$browser->driver->findElement(WebDriverBy::cssSelector('#unselect-active-tiles'))->click();

		$this->assertSame(Util::getMyAddress() . '/tsumegos/play/' . $tsumegoId, $browser->driver->getCurrentURL());
		$this->assertFalse($browser->idExists('unselect-active-tiles'));
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

	public function testFavoritesHeartTogglesAddAndRemove(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

		// Heart is rendered for logged-in users
		$this->assertTrue($browser->idExists('favButton'));

		// First click adds the tsumego to the lazily-created Favorites set
		$browser->clickId('favButton');

		$favoritesSet = null;
		$deadline = microtime(true) + 5;
		while (microtime(true) < $deadline)
		{
			$favoritesSet = ClassRegistry::init('Set')->find('first', [
				'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
			]);
			if ($favoritesSet)
				break;
			usleep(100000);
		}
		$this->assertNotEmpty($favoritesSet, 'First heart click should create the Favorites set');

		$scModel = ClassRegistry::init('SetConnection');
		$this->assertNotEmpty($scModel->find('first', [
			'conditions' => ['set_id' => $favoritesSet['Set']['id'], 'tsumego_id' => $tsumegoId],
		]), 'First heart click should add the tsumego to Favorites');

		// Let the client process the add response before toggling back off
		usleep(300000);

		// Second click removes it again
		$browser->clickId('favButton');

		$removed = false;
		$deadline = microtime(true) + 5;
		while (microtime(true) < $deadline)
		{
			if ($scModel->find('count', [
				'conditions' => ['set_id' => $favoritesSet['Set']['id'], 'tsumego_id' => $tsumegoId],
			]) === 0)
			{
				$removed = true;
				break;
			}
			usleep(100000);
		}
		$this->assertTrue($removed, 'Second heart click should remove the tsumego from Favorites');
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
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript("return document.readyState === 'complete';");
		});

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
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript("return typeof window.besogo !== 'undefined';");
		});

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$wait->until(function () use ($browser) {
			return str_contains($browser->find('#status')->getText(), "Incorrect");
		});

		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->waitForSubmitResult();
		$browser->clickId('besogo-reset-button');
		$this->assertStringContainsString("", $browser->find('#status')->getText());
		$browser->clickBoard(2, 1);
		$wait->until(function () use ($browser) {
			return str_contains($browser->find('#status')->getText(), "Incorrect");
		});
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

	public function testMergeFormShowsPreviewsForSiblingTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				[
					'set_order' => 1,
					'sgf' => '(;GM[1]FF[4]SZ[19];B[aa])',
					'sets' => [['name' => 'masterSetA', 'num' => 1], ['name' => 'masterSetB', 'num' => 1]],
				],
				[
					'set_order' => 2,
					'sgf' => '(;GM[1]FF[4]SZ[19];B[bb])',
					'sets' => [['name' => 'slaveSet', 'num' => 1]],
				],
			],
		]);

		$result = $this->testAction('/tsumegos/mergeFinalForm', [
			'data' => [
				'master-id' => $context->tsumegos[0]['set-connections'][0]['id'],
				'slave-id' => $context->tsumegos[1]['set-connections'][0]['id'],
			],
			'return' => 'view',
		]);

		$this->assertStringContainsString('data-sgf-preview', $result);
	}

	public function testSimilarSearchPreviewIncludesDiff()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][ff];B[aa];W[ab];B[ba]C[+])'],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][fe];B[aa];W[ab];B[ba]C[+])'],
			],
		]);

		$result = $this->testAction('/tsumegos/duplicatesearch/' . $context->tsumegos[0]['set-connections'][0]['id'], ['return' => 'view']);

		$this->assertMatchesRegularExpression('/"diff":"[a-z]+"/', $result);
	}
}
