<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Exception\TimeoutException;

App::uses('NotFoundException', 'Routing/Error');
App::uses('User', 'Model');
App::uses('Constants', 'Utility');

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

	public function testMergeFormShowsOnlyPublicSetConnections()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				[
					'sgf' => '(;GM[1]FF[4]SZ[19];B[aa])',
					'sets' => [
						['name' => 'masterSetA', 'num' => 1],
						['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0, 'default' => true],
					],
				],
				[
					'sgf' => '(;GM[1]FF[4]SZ[19];B[bb])',
					'sets' => [
						['name' => 'slaveSet', 'num' => 1],
						['name' => 'Favorites', 'num' => 2, 'user_id' => 'self', 'public' => 0],
					],
				],
			],
		]);

		$favSet = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'Favorites', 'user_id' => $context->user['id']]]);
		$this->assertNotEmpty($favSet, 'Favorites set should exist from test setup');
		$favoritesSetId = $favSet['Set']['id'];

		$publicConnectionIds = [];
		$favoriteConnectionIds = [];
		foreach (array_merge($context->tsumegos[0]['set-connections'], $context->tsumegos[1]['set-connections']) as $sc)
		{
			if ($sc['set_id'] == $favoritesSetId)
				$favoriteConnectionIds[] = $sc['id'];
			else
				$publicConnectionIds[] = $sc['id'];
		}
		$this->assertNotEmpty($publicConnectionIds, 'Test setup must include public connections');
		$this->assertNotEmpty($favoriteConnectionIds, 'Test setup must include favorites connections');

		$result = $this->testAction('/tsumegos/mergeFinalForm', [
			'data' => [
				'master-id' => $context->tsumegos[0]['set-connections'][0]['id'],
				'slave-id' => $context->tsumegos[1]['set-connections'][0]['id'],
			],
			'return' => 'view',
		]);

		foreach ($publicConnectionIds as $id)
			$this->assertStringContainsString('href="/' . $id . '"', $result, 'Public occurrence should be shown');
		foreach ($favoriteConnectionIds as $id)
			$this->assertStringNotContainsString('href="/' . $id . '"', $result, 'Favorites occurrence should be hidden');
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

	public function testDescriptionColorSwap(): void
	{
		$blackFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		// ORIGINAL preference: the player follows the SGF first move, so the board
		// is never inverted and the description is shown exactly as stored (true-color).
		$context = new ContextPreparator([
			'user' => ['name' => 'descColorSwapB'],
			'tsumego' => ['set_order' => 1, 'description' => "Black's stones attack White's group.", 'sgf' => $blackFirstSgf],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);
		$decoded = htmlspecialchars_decode(strip_tags($this->view));
		$this->assertStringContainsString("Black's stones attack White's group.", $decoded);

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descColorSwapW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play. Attack the black group.', 'sgf' => $whiteFirstSgf],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context2->tsumegos[0]['id'], ['return' => 'view']);
		$decoded = htmlspecialchars_decode(strip_tags($this->view));
		$this->assertStringContainsString('White to play. Attack the black group.', $decoded);
	}

	/**
	 * Controller test: When the edit form is shown with an inverted board it
	 * submits color_swapped=1, so the description is swapped Black<->White
	 * back to true-color on save.
	 */
	public function testDescriptionEditWithSwap(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'White to attack the black stones.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])',
			],
		]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->testAction('/tsumegos/edit/' . $tsumegoId, [
			'method' => 'post',
			'data' => [
				'delete' => '',
				'description' => 'Black to play here.',
				'color_swapped' => '1',
				'rating' => '1500',
				'minimum-rating' => '',
				'maximum-rating' => '',
				'hint' => '',
				'author' => '',
				'redirect' => '/',
			],
			'return' => 'contents',
		]);

		$saved = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertSame('White to play here.', $saved['Tsumego']['description']);
	}

	/**
	 * Controller test: When the edit form is shown without an inverted board it
	 * submits color_swapped=0, so the description is stored as-is (true-color).
	 */
	public function testDescriptionEditNoSwap(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to attack the white stones.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
			],
		]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->testAction('/tsumegos/edit/' . $tsumegoId, [
			'method' => 'post',
			'data' => [
				'delete' => '',
				'description' => 'Black to play first.',
				'color_swapped' => '0',
				'rating' => '1500',
				'minimum-rating' => '',
				'maximum-rating' => '',
				'hint' => '',
				'author' => '',
				'redirect' => '/',
			],
			'return' => 'contents',
		]);

		$saved = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertSame('Black to play first.', $saved['Tsumego']['description']);
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

	/**
	 * Custom multiple-choice variant answers are stored in true colors. When the
	 * board is inverted (?playercolor=white), the answer text is swapped so it
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
				'variants' => [[
					'type' => 'score_estimating',
					'answer1' => '6.5',
					'answer2' => '3',
					'answer3' => '6',
					'numAnswer' => '0',
				]],
			],
		]);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);
		$this->assertStringContainsString('Black captures: 3', $this->view);
		$this->assertStringContainsString('White captures: 6', $this->view);

		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White captures: 3', $this->view);
		$this->assertStringContainsString('Black captures: 6', $this->view);
	}

	/**
	 * When the player's color opposes the SGF's first move the board is inverted and
	 * the description swaps Black<->White so the player sees their own color as the
	 * side to move. (?playercolor sets up the scenario.)
	 */
	public function testDescriptionSwapsWhenPlayerColorOpposesFirstMove(): void
	{
		$blackFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		$context = new ContextPreparator([
			'user' => ['name' => 'descOpposeB'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play.', 'sgf' => $blackFirst],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White to play.', $this->view);
		$this->assertStringNotContainsString('Black to play.', $this->view);

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descOpposeW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play.', 'sgf' => $whiteFirst],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context2->tsumegos[0]['id'] . '?playercolor=black', ['return' => 'view']);
		$this->assertStringContainsString('Black to play.', $this->view);
	}

	/**
	 * When the player's color matches the SGF's first move the board is not inverted
	 * and the description is shown exactly as stored. (?playercolor sets up the scenario.)
	 */
	public function testDescriptionDoesNotSwapWhenPlayerColorMatchesFirstMove(): void
	{
		$blackFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		$context = new ContextPreparator([
			'user' => ['name' => 'descMatchB'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play.', 'sgf' => $blackFirst],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=black', ['return' => 'view']);
		$this->assertStringContainsString('Black to play.', $this->view);

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descMatchW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play.', 'sgf' => $whiteFirst],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context2->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White to play.', $this->view);
	}

	/**
	 * When the board is inverted, every color word in a description swaps, so a
	 * description that names both colours stays internally consistent.
	 */
	public function testDescriptionSwapsAllColorWords(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descAllWords'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play. Find the way to kill the white group.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White to play. Find the way to kill the black group.', $this->view);
	}

	/**
	 * Color swaps match whole words only, so a colour word that is part of a larger
	 * word (like "Blackbird") is left untouched.
	 */
	public function testDescriptionSwapRespectsWordBoundaries(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descBoundary'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play. The Blackbird group is white.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('White to play. The Blackbird group is black.', $this->view);
	}

	/**
	 * Color swaps preserve the original casing of each word.
	 */
	public function testDescriptionSwapPreservesCase(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descCase'],
			'tsumego' => ['set_order' => 1, 'description' => 'black stones attack White stones.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white', ['return' => 'view']);
		$this->assertStringContainsString('white stones attack Black stones.', $this->view);
	}

	/**
	 * A White-first problem in a collection that forces the player to black (small
	 * board) inverts the board and swaps the description automatically, without any
	 * URL override. This is the reported "Problems from Professional Games" case.
	 */
	public function testForcedBlackWhiteFirstSwapsDescription(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descForcedBlack'],
			'tsumego' => [
				'sets' => [['name' => '9x9 Test Set', 'num' => 1]],
				'description' => 'White to play. Find the way to kill the black group.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[9];W[aa];B[ab];W[ba]C[+])',
			],
		]);
		Auth::saveUserField('pref_player_color', User::PREF_PLAYER_COLOR_ORIGINAL);
		$this->testAction('tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'view']);
		$this->assertStringContainsString('Black to play. Find the way to kill the white group.', $this->view);
	}
}
