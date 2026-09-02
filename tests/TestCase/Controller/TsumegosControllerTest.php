<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Exception\TimeoutException;

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
			new WebDriverWait($browser->driver, 5, 200)->until(function () use ($browser) {
				return (bool) $browser->driver->executeScript(
					'return !!window.userSets && window.userSets.some(s => s.contains);'
				);
			});
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

	public static function descriptionColorSwapProvider(): array
	{
		$blackFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		return [
			'no swap: Black visual (pl=0) + Black-first SGF, true-color matches' => [
				"Black's stones attack White's group. black wins!",
				$blackFirstSgf,
				'black',
				"Black's stones attack White's group. black wins!",
			],
			'swap: White visual (pl=1) + Black-first SGF, board inverted' => [
				"Black's stones. Kill the white group near the Blackbird. Watch whitespace.",
				$blackFirstSgf,
				'white',
				"White's stones. Kill the black group near the Blackbird. Watch whitespace.",
			],
			'no swap: Black visual (pl=0) + White-first SGF, true-color matches' => [
				"White to play. Attack the black group.",
				$whiteFirstSgf,
				'black',
				"White to play. Attack the black group.",
			],
			'swap: White visual (pl=1) + White-first SGF, board inverted' => [
				"White to play. Attack the black group.",
				$whiteFirstSgf,
				'white',
				"Black to play. Attack the white group.",
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

		$decoded = htmlspecialchars_decode(strip_tags($this->view));
		$this->assertStringContainsString($expected, $decoded);
	}

	/**
	 * Browser test: Admin edits description on a puzzle where board is inverted.
	 * W-first SGF + playercolor=white -> board inverted -> textarea shows swapped text.
	 * After editing and saving, the stored description is un-swapped back to true-color.
	 */
	public function testDescriptionEditWithSwap(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'White to attack the black stones.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])'
			]
		]);

		$browser = Browser::instance();
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		// W-first SGF + playercolor=white -> pl=1 -> shouldSwap=true
		$browser->get($setConnectionId . '?playercolor=white');

		// Click the (Edit) link to reveal the form
		$browser->clickId('modify-description');

		// Verify the textarea shows swapped text (White->Black for inverted display)
		$textarea = $browser->find('#description');
		$this->assertSame('Black to attack the white stones.', $textarea->getAttribute('value'));

		// Verify hidden color_swapped field is 1
		$colorSwapped = $browser->find('input[name="color_swapped"]');
		$this->assertSame('1', $colorSwapped->getAttribute('value'));

		// Edit the description to something new (still in swapped/display form)
		$textarea->clear();
		$textarea->sendKeys('Black to play here.');

		// Submit the form
		$browser->clickId('tsumego-edit-submit');

		// Verify the stored description was un-swapped (Black->White) back to true-color
		$saved = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);
		$this->assertSame('White to play here.', $saved['Tsumego']['description']);
	}

	/**
	 * Browser test: Admin edits description on a puzzle where board is NOT inverted.
	 * B-first SGF + playercolor=black -> no swap -> textarea shows true-color text.
	 * After editing and saving, the stored description is kept as-is.
	 */
	public function testDescriptionEditNoSwap(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to attack the white stones.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'
			]
		]);

		$browser = Browser::instance();
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		// B-first SGF + playercolor=black -> pl=0 -> shouldSwap=false
		$browser->get($setConnectionId . '?playercolor=black');

		// Click the (Edit) link to reveal the form
		$browser->clickId('modify-description');

		// Verify the textarea shows original text (no swap)
		$textarea = $browser->find('#description');
		$this->assertSame('Black to attack the white stones.', $textarea->getAttribute('value'));

		// Verify hidden color_swapped field is 0
		$colorSwapped = $browser->find('input[name="color_swapped"]');
		$this->assertSame('0', $colorSwapped->getAttribute('value'));

		// Edit the description
		$textarea->clear();
		$textarea->sendKeys('Black to play first.');

		// Submit the form
		$browser->clickId('tsumego-edit-submit');

		// Verify it was stored as-is (no normalization needed)
		$saved = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);
		$this->assertSame('Black to play first.', $saved['Tsumego']['description']);
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

	/**
	 * Browser test: For a White-first SGF, check what color the player actually places
	 * and whether the description matches that visual color.
	 *
	 * Key insight: besogoPlayerColor controls board INVERSION, not the player's stone color.
	 * The player's visual stone color is: besogo.editor.getCurrent().nextMove()
	 *   -1 = Black stone on screen, 1 = White stone on screen
	 *
	 * When besogoPlayerColor="white" (inversion active):
	 *   White-first SGF -> firstMove inverted to BLACK -> player places BLACK stones visually
	 * When besogoPlayerColor="black" (no inversion):
	 *   White-first SGF -> firstMove stays WHITE -> player places WHITE stones visually
	 *
	 * The description should match the VISUAL stone color the player places.
	 * Under true-color convention, description stores "White to play" for W-first SGFs.
	 * When board is inverted (pl=1), swap makes it "Black to play" to match visual.
	 */
	public function testDescriptionMatchesActualFirstMoveColor(): void
	{
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		// True-color convention: "White to play" stored for W-first SGFs
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'description' => 'White to play',
				'sgf' => $whiteFirstSgf,
			],
		]);

		$browser = Browser::instance();
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];

		for ($i = 0; $i < 10; $i++)
		{
			$browser->get((string) $setConnectionId);

			$descriptionText = $browser->find('#descriptionText')->getText();
			$besogoColor = $browser->driver->executeScript("return besogoPlayerColor;");

			// Wait for besogo to initialize
			$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10);
			$wait->until(function () use ($browser) {
				return $browser->driver->executeScript("return typeof besogo !== 'undefined' && besogo.editor !== undefined;");
			});

			// nextMove() returns -1 (BLACK) or 1 (WHITE) -- the ACTUAL visual stone color
			$nextMove = $browser->driver->executeScript("return besogo.editor.getCurrent().nextMove();");
			$visualStoneColor = ($nextMove === -1) ? 'Black' : 'White';

			$descriptionSaysBlack = str_contains($descriptionText, 'Black');
			$descriptionSaysWhite = str_contains($descriptionText, 'White');

			// The description color should match the visual stone color
			$this->assertTrue(
				($visualStoneColor === 'Black' && $descriptionSaysBlack)
				|| ($visualStoneColor === 'White' && $descriptionSaysWhite),
				"Iteration $i: description='$descriptionText', visual stone=$visualStoneColor, besogoPlayerColor=$besogoColor"
			);
		}
	}

	/**
	 * OG description uses true-color convention — no swap needed.
	 * The OG image renders actual SGF colors, and descriptions match the board.
	 */
	public function testOgDescriptionUsesTrueColor(): void
	{
		$blackFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		// Black-first: OG description shows true-color "Black"
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'description' => 'Black to capture the white group', 'sgf' => $blackFirstSgf],
		]);
		$result = $this->testAction(
			'tsumegos/play/' . $context->tsumegos[0]['id'],
			['return' => 'contents']
		);
		preg_match('/property="og:description"\s+content="([^"]*)"/', $result, $m);
		$this->assertNotEmpty($m, 'og:description should exist for Black-first SGF');
		$this->assertStringContainsString('Black to capture the white group', $m[1]);

		// White-first: OG description shows true-color "White"
		$context2 = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'description' => 'White to capture the black group', 'sgf' => $whiteFirstSgf],
		]);
		$result2 = $this->testAction(
			'tsumegos/play/' . $context2->tsumegos[0]['id'],
			['return' => 'contents']
		);
		preg_match('/property="og:description"\s+content="([^"]*)"/', $result2, $m2);
		$this->assertNotEmpty($m2, 'og:description should exist for White-first SGF');
		$this->assertStringContainsString('White to capture the black group', $m2[1]);
	}

	private function createTsumegoVariant(int $tsumegoId, array $variantData): void
	{
		$variant = ['TsumegoVariant' => array_merge(['tsumego_id' => $tsumegoId], $variantData)];
		ClassRegistry::init('TsumegoVariant')->create($variant);
		ClassRegistry::init('TsumegoVariant')->save($variant);
	}

	/**
	 * Custom multiple-choice variant answers are stored in true colors. When the
	 * board is inverted (playercolor=white), the answer text is swapped so it
	 * matches the stones the player actually sees.
	 */
	public function testMultipleChoiceVariantAnswersSwapWhenBoardInverted(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play. What is the result?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
			],
		]);
		$this->createTsumegoVariant($context->tsumegos[0]['id'], [
			'type' => 'multiple_choice',
			'answer1' => 'White is dead',
			'answer2' => 'Ko',
			'answer3' => 'Seki in sente',
			'answer4' => 'Seki in gote',
			'numAnswer' => '3',
		]);

		// No inversion: answers keep their true colors.
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);
		$this->assertStringContainsString('"White is dead"', $this->view);

		// Inverted board: the answer text is swapped to match the visual stones.
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('"Black is dead"', $this->view);
		$this->assertStringNotContainsString('"White is dead"', $this->view);
	}

	/**
	 * Score-estimating summary labels are stored in true colors. When the board
	 * is inverted, the "Black/White captures" labels swap while the numeric
	 * values stay in place.
	 */
	public function testScoreEstimatingLabelsSwapWhenBoardInverted(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Who wins?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
			],
		]);
		$this->createTsumegoVariant($context->tsumegos[0]['id'], [
			'type' => 'score_estimating',
			'answer1' => '6.5',
			'answer2' => '3',
			'answer3' => '6',
			'numAnswer' => '0',
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);
		$this->assertStringContainsString('Black captures: 3', $this->view);
		$this->assertStringContainsString('White captures: 6', $this->view);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White captures: 3', $this->view);
		$this->assertStringContainsString('Black captures: 6', $this->view);
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
			],
		]);
		$this->createTsumegoVariant($context->tsumegos[0]['id'], [
			'type' => 'multiple_choice',
			'answer1' => 'White is dead',
			'answer2' => 'Ko',
			'answer3' => 'Seki in sente',
			'answer4' => 'Seki in gote',
			'numAnswer' => '3',
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10);
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript(
				"return typeof besogo !== 'undefined' && document.getElementById('besogo-multipleChoice1') !== null;"
			);
		});

		$this->assertSame('White is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('Black is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));
		$this->assertSame('White to play. What is the result?', $browser->find('#descriptionText')->getText());
	}
}
