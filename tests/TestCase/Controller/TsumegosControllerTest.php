<?php

use Cake\Routing\Router;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\TsumegoVariantTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\TsumegoUtil;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

class TsumegosControllerTest extends IntegrationTestCase
{
	//The play page's script reads one object of plain values, the same a request for the next
	//problem will return
	public function testPlayPageDataIsPlainJson(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1]]);
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		//Numbers may come back as integers, nothing may come back changed otherwise
		$playPage = $this->viewVariable('playPage');
		$this->assertEquals($playPage, json_decode(json_encode($playPage), true));
		$this->assertEquals($playPage, $this->playPage());
		$this->assertSame((int) $context->tsumegos[0]['id'], $playPage['problem']['tsumegoID']);
		$this->assertSame((int) $context->tsumegos[0]['set-connections'][0]['id'], $playPage['problem']['setConnectionID']);
		$this->assertSame((int) $context->user['id'], $playPage['page']['userID']);
	}

	//A guest has no user ID in the page data, so the board's script doesn't send a result for them
	//and shows no signed-in controls
	public function testAGuestHasNoUserIDInThePlayPageData(): void
	{
		$context = new ContextPreparator(['user' => null, 'tsumego' => ['set_order' => 1]]);
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertNull($this->viewVariable('playPage')['page']['userID']);
	}

	public function testSetNameAndNumIsVisible()
	{
		foreach ([false, true] as $openBySetConnectionID)
		{
			$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);
			$this->get($openBySetConnectionID
				? ('/' . $context->tsumegos[0]['set-connections'][0]['id'])
				: ('tsumegos/play/' . $context->tsumegos[0]['id']));
			$this->assertTextContains("test set", $this->view);

			$dom = $this->getStringDom();
			$href = $dom->querySelector('#playTitleA');
			$this->assertTextContains('test set', $href->textContent);
			$this->assertSame('666', trim($dom->getElementById('currentNavigationButton')->textContent), 'The open problem button shows its place in the collection');
		}
	}

	//The SGF download is named after the collection and the number of the problem in it.
	//The admin's History link stands out once a person changed the problem, not while it has only an
	//automatic version, which has no user
	public function testTheHistoryLinkStandsOutOnlyForAVersionOfAPerson(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19];B[aa])']]);
		$setConnectionID = $context->tsumegos[0]['set-connections'][0]['id'];

		$this->get('/' . $setConnectionID);
		$this->assertStringContainsString('historyLink', $this->getStringDom()->getElementById('showx6')->getAttribute('class'));

		Util::execute('UPDATE sgf SET user_id = NULL WHERE tsumego_id = ?', [$context->tsumegos[0]['id']]);
		$this->get('/' . $setConnectionID);
		$this->assertStringNotContainsString('historyLink', $this->getStringDom()->getElementById('showx6')->getAttribute('class'));
	}

	public function testTheFirstProblemOfACollectionLeadsBackToTheCollectionPage(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'levelplayer'],
			'tsumego' => ['set_order' => 1],
			'tsumegos' => [['set_order' => 2]],
		]);
		$first = $context->tsumegos[0]['set-connections'][0];
		$second = $context->tsumegos[1]['set-connections'][0];

		$this->get('/tsumegos/play/' . $context->tsumegos[0]['id']);

		$this->assertSame('/sets/' . $first['set_id'], $this->playPage()['problem']['previousLink']);
		$this->assertSame('/' . $second['id'], $this->playPage()['problem']['nextLink']);
	}

	public function testProblemKeepsOneAddressInBothUrlShapes()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);
		$setConnectionID = $context->tsumegos[0]['set-connections'][0]['id'];
		$address = Router::url('/' . $setConnectionID, true);
		$urls = [
			'/' . $setConnectionID,
			'/' . $setConnectionID . '?mode=2',
			'tsumegos/play/' . $context->tsumegos[0]['id']
		];

		foreach ($urls as $url)
		{
			$this->get($url);
			$contents = $this->view;
			$this->assertStringContainsString('<link rel="canonical" href="' . $address . '">', $contents, $url);
			$this->assertStringContainsString('<meta property="og:url" content="' . $address . '">', $contents, $url);
		}
	}

	//The "ask the author for a solution" button is offered to the admins the policy allows
	public function testTheSolutionRequestButtonIsOfferedToAdminsOnly()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);
		$url = '/' . $context->tsumegos[0]['set-connections'][0]['id'];
		$this->get($url);
		$this->assertFalse($this->playPage()['page']['canRequestSolution']);

		$adminContext = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true], 'tsumego' => ['set_order' => 666]]);
		$adminUrl = '/' . $adminContext->tsumegos[0]['set-connections'][0]['id'];
		$this->get($adminUrl);
		$this->assertTrue($this->playPage()['page']['canRequestSolution']);
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
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$this->assertTextContains('test set 1/3 on Tsumego Hero', $this->view);
	}

	public function testViewingTsumegoInMoreSets()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);
		$tsumegoID = $context->tsumegos[0]['id'];
		$this->get('tsumegos/play/' . $tsumegoID);

		// The first one was selected into the title
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertSame('666', trim($dom->getElementById('currentNavigationButton')->textContent), 'The open problem button shows its place in the collection');

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

	public function testTheDuplicateGroupShowsTheTitlesOfTheSetsAsText()
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'first', 'num' => '1'], ['name' => '<img src=x onerror=alert(1)>', 'num' => '2']]]]);
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$duplicateTable = $this->getStringDom()->querySelector('.duplicateTable');
		$this->assertSame(0, $duplicateTable->getElementsByTagName('img')->length);
		$this->assertTextContains('<img src=x onerror=alert(1)>', $duplicateTable->getElementsByTagName('a')[1]->textContent);
	}

	public function testViewingTsumegoInMoreSetsAndSpecifyingWhichOneIsTheMainOne()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?sid=' . $context->tsumegos[0]['sets'][1]['id']);

		// The second one was selected by the sid parameter
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 2', $href->textContent);
		$this->assertSame('777', trim($dom->getElementById('currentNavigationButton')->textContent), 'The open problem button shows its place in the collection');

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

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

		$setTable = $this->fetchTable('Set');
		$this->assertNotFalse($set = $setTable->saveOrFail($setTable->newEntity(['title' => 'alice public', 'public' => 1, 'user_id' => $context->otherUsers[0]['id'], 'order' => 1])));
		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 3]));

		$titles = array_map(fn($row) => $row['title'], TsumegoUtil::getSetConnectionsWithTitles($context->tsumegos[0]['id']));
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

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?sid=' . $context->tsumegos[0]['sets'][1]['id']);

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

		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumego = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['description' => 'private only', 'author' => '']));
		$this->addSetForTsumego($tsumego->id, 'alice favorites', $context->otherUsers[0]['id'], 0, 1);

		$this->get('tsumegos/play/' . $tsumego->id);

		$this->assertResponseCode(404);
	}

	private function addSetForTsumego(int $tsumegoId, string $title, ?int $userId, int $public, int $num): void
	{
		$setTable = $this->fetchTable('Set');
		$set = $setTable->saveOrFail($setTable->newEntity(['title' => $title, 'public' => $public, 'user_id' => $userId, 'order' => 1]));

		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['tsumego_id' => $tsumegoId, 'set_id' => $set->id, 'num' => $num]));
	}

	public function testViewingTsumegoWithoutAnySGF()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666]]);

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('test set', $href->textContent);
		$this->assertSame('666', trim($dom->getElementById('currentNavigationButton')->textContent), 'The open problem button shows its place in the collection');
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
		$browser->find('#showFilters')->click();
		$browser->waitUntilCssSelectorDisplayed('#unselect-active-tiles');
		$browser->find('#unselect-active-tiles')->click();

		$this->assertSame(Util::getMyAddress() . '/tsumegos/play/' . $tsumegoId, $browser->driver->getCurrentURL());
		$this->assertFalse($browser->idExists('unselect-active-tiles'));
	}

	//A tile shows the name of its filter as text, and drops just that filter
	public function testAFilterTileDropsItsFilter(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 666, 'tags' => [['name' => '<b>bold</b>'], ['name' => 'atari']]]]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('/tsumegos/play/' . $tsumegoId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_tags', 'value' => '<b>bold</b>@atari']);
		$browser->getAnonymous('/tsumegos/play/' . $tsumegoId);

		$browser->find('#showFilters')->click();
		$browser->waitUntilCssSelectorDisplayed('.active-tiles-container .dropdown__tile--brown');
		$tiles = $browser->getElements('.active-tiles-container .dropdown__tile--brown');
		$this->assertSame(['<b>bold</b>', 'atari'], array_map(fn($tile) => $tile->getText(), $tiles));
		$this->assertSame(0, count($browser->getElements('.active-tiles-container b')));

		$tiles[0]->click();
		$browser->waitUntilJs('document.readyState === "complete" && window.eval("typeof activeTagTiles !== \'undefined\' && activeTagTiles.length === 1")');
		$this->assertSame(['atari'], $browser->driver->executeScript('return window.eval("activeTagTiles");'));
	}

	// testing the same things as testViewingTsumegoInMoreSets, but using the web driver to do so
	// if this test fails, it probably means something is wrong with the web driver configuration
	public function testViewingTsumegoInMoreSetsUsingWebDriver()
	{
		$context = new ContextPreparator(['tsumego' => [
			'sets' => [['name' => 'tsumego set 1', 'num' => '666'], ['name' => 'tsumego set 2', 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$href = $browser->find('#playTitleA');
		$this->assertTextContains('set 1', $href->getText());
		$this->assertSame('666', $browser->find('#currentNavigationButton')->getText(), 'The open problem button shows its place in the collection');
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

		$this->post('/tsumegos/mergeFinalForm', [
			'master-id' => $context->tsumegos[0]['set-connections'][0]['id'],
			'slave-id' => $context->tsumegos[1]['set-connections'][0]['id'],
		]);
		$result = $this->view;

		$this->assertStringContainsString('data-sgf-preview', $result);
	}

	public function testMergeOfAMissingMasterGoesBackToTheForm()
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [['set_order' => 1]]]);

		$this->post('/tsumegos/mergeFinalForm', [
			'master-id' => 999999,
			'slave-id' => $context->tsumegos[0]['set-connections'][0]['id'],
		]);

		$this->assertRedirect('/tsumegos/mergeForm');
		$this->assertSame('Master set connection does not exist.', CookieFlash::get());
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

		$favSet = $this->fetchTable('Set')->find()->where(['title' => 'Favorites', 'user_id' => $context->user['id']])->first();
		$this->assertNotEmpty($favSet, 'Favorites set should exist from test setup');
		$favoritesSetId = $favSet->id;

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

		$this->post('/tsumegos/mergeFinalForm', [
			'master-id' => $context->tsumegos[0]['set-connections'][0]['id'],
			'slave-id' => $context->tsumegos[1]['set-connections'][0]['id'],
		]);
		$result = $this->view;

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

		$this->get('/tsumegos/duplicatesearch/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$result = $this->view;

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
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$decoded = htmlspecialchars_decode(strip_tags($this->view));
		$this->assertStringContainsString("Black's stones attack White's group.", $decoded);

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descColorSwapW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play. Attack the black group.', 'sgf' => $whiteFirstSgf],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context2->tsumegos[0]['id']);
		$this->assertSame('White to play. Attack the black group.', $this->shownDescription());
	}

	//Controller test: When the edit form is shown with an inverted board it
	//submits color_swapped=1, so the description is swapped Black<->White
	//back to true-color on save.
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

		$this->post('/tsumegos/edit/' . $tsumegoId, [
			'delete' => '',
			'description' => 'Black to play here.',
			'color_swapped' => '1',
			'rating' => '1500',
			'minimum-rating' => '',
			'maximum-rating' => '',
			'hint' => '',
			'author' => '',
			'redirect' => '/',
		]);

		$saved = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertSame('White to play here.', $saved->description);
	}

	//Controller test: When the edit form is shown without an inverted board it
	//submits color_swapped=0, so the description is stored as-is (true-color).
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

		$this->post('/tsumegos/edit/' . $tsumegoId, [
			'delete' => '',
			'description' => 'Black to play first.',
			'color_swapped' => '0',
			'rating' => '1500',
			'minimum-rating' => '',
			'maximum-rating' => '',
			'hint' => '',
			'author' => '',
			'redirect' => '/',
		]);

		$saved = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertSame('Black to play first.', $saved->description);
	}

	//OG description uses true-color convention - no swap needed.
	//The OG image renders actual SGF colors, and descriptions match the board.
	public function testOgDescriptionUsesTrueColor(): void
	{
		$blackFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirstSgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		// Black-first: OG description shows true-color "Black"
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'description' => 'Black to capture the white group', 'sgf' => $blackFirstSgf],
		]);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$result = $this->view;
		preg_match('/property="og:description"\s+content="([^"]*)"/', $result, $matches);
		$this->assertNotEmpty($matches, 'og:description should exist for Black-first SGF');
		$this->assertStringContainsString('Black to capture the white group', $matches[1]);

		// White-first: OG description shows true-color "White"
		$context2 = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'description' => 'White to capture the black group', 'sgf' => $whiteFirstSgf],
		]);
		$this->get('tsumegos/play/' . $context2->tsumegos[0]['id']);
		$result2 = $this->view;
		preg_match('/property="og:description"\s+content="([^"]*)"/', $result2, $m2);
		$this->assertNotEmpty($m2, 'og:description should exist for White-first SGF');
		$this->assertStringContainsString('White to capture the black group', $m2[1]);
	}

	//Custom multiple-choice variant answers are stored in true colors. When the
	//board is inverted (?playercolor=white), the answer text is swapped so it
	//matches the stones the player actually sees.
	public function testMultipleChoiceVariantAnswersSwapWhenBoardInverted(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play. What is the result?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
				'variants' => [[
					'tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE,
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'correct_answer' => '3',
				]],
			],
		]);

		// No inversion: answers keep their true colors.
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$this->assertStringContainsString('"White is dead"', $this->view);

		// Inverted board: the answer text is swapped to match the visual stones.
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertStringContainsString('"Black is dead"', $this->view);
		$this->assertStringNotContainsString('"White is dead"', $this->view);
	}

	//An admin on a sandbox problem: a problem in a set of the site that is not published yet.
	private function prepareSandboxProblem(array $variants = []): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
				'sets' => [['name' => 'sandbox', 'public' => 0, 'num' => 1]],
				'variants' => $variants]]);
	}

	//Posts the problem's edit form as the play page sends it: every field with the problem's own value,
	//changed by $changes
	private function postEditForm(int $tsumegoID, array $changes): void
	{
		$this->post('tsumegos/edit/' . $tsumegoID, $this->editFormOf($tsumegoID, $changes));
	}

	private function editFormOf(int $tsumegoID, array $changes): array
	{
		$tsumego = $this->fetchTable('Tsumego')->get($tsumegoID);
		return $changes + [
			'redirect' => '/tsumegos/play/' . $tsumegoID,
			'description' => $tsumego->description,
			'hint' => $tsumego->hint,
			'rating' => (string) $tsumego->rating,
			'minimum-rating' => '',
			'maximum-rating' => '',
			'author' => $tsumego->author,
			'pass' => (string) (int) $tsumego->pass];
	}

	private function postProblemSettings(ContextPreparator $context, ?int $variantType): void
	{
		$this->postEditForm($context->tsumegos[0]['id'], ['variant_type' => (string) $variantType]);
		$this->assertResponseCode(302);
	}

	private static function variantsOf(int $tsumegoID): array
	{
		return TableRegistry::getTableLocator()->get('TsumegoVariant')->find()
			->select(['tsumego_variant_type_id', 'answer1'])->where(['tsumego_id' => $tsumegoID])->disableHydration()->toArray();
	}

	//The form comes with the problem's own question checked, saving it used to delete the variant.
	public function testProblemSettingsKeepTheVariantAndItsAnswers(): void
	{
		$context = $this->prepareSandboxProblem([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']]);

		$this->postProblemSettings($context, TsumegoVariantTable::TYPE_MULTIPLE_CHOICE);

		$this->assertSame([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']], self::variantsOf($context->tsumegos[0]['id']));
	}

	public function testProblemSettingsGiveAProblemAVariant(): void
	{
		$context = $this->prepareSandboxProblem();

		$this->postProblemSettings($context, TsumegoVariantTable::TYPE_MULTIPLE_CHOICE);

		$this->assertSame([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Black is dead']], self::variantsOf($context->tsumegos[0]['id']));
	}

	public function testProblemSettingsChangeTheVariant(): void
	{
		$context = $this->prepareSandboxProblem([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']]);

		$this->postProblemSettings($context, null);
		$this->assertSame([], self::variantsOf($context->tsumegos[0]['id']));
	}

	//Multiple choice is the only question type; the score estimating one (2) is gone, and an
	//unknown type changes nothing
	public function testProblemSettingsRejectAnUnknownQuestionType(): void
	{
		$context = $this->prepareSandboxProblem([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']]);
		$tsumegoID = $context->tsumegos[0]['id'];

		$this->postEditForm($tsumegoID, ['pass' => 1, 'variant_type' => '2']);

		$this->assertResponseCode(400);
		$this->assertSame(0, (int) $this->fetchTable('Tsumego')->get($tsumegoID)->pass);
		$this->assertSame([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']], self::variantsOf($tsumegoID));
	}

	//A problem starts with passing off; the alternative response mode is no setting any more, a sent
	//value of it changes nothing
	public function testProblemSettingsSavePassing(): void
	{
		$context = $this->prepareSandboxProblem();
		$tsumegoID = $context->tsumegos[0]['id'];

		$this->postEditForm($tsumegoID, ['alternative_response' => 0, 'pass' => 1]);

		$this->assertResponseCode(302);
		$this->assertSame(1, (int) $this->fetchTable('Tsumego')->get($tsumegoID)->pass);
		$this->assertSame([[AdminActivityTypeTable::PASS_MODE, '1']], array_map(
			fn($activity) => [(int) $activity['type'], $activity['new_value']],
			Util::query('SELECT type, new_value FROM admin_activity WHERE tsumego_id = ? ORDER BY id', [$tsumegoID])));
	}

	//The admin form on the play page saves the settings and comes back to the problem
	public function testAdminChangesTheProblemSettingsOnThePlayPage(): void
	{
		$context = $this->prepareSandboxProblem();
		$tsumegoID = $context->tsumegos[0]['id'];
		$playPath = '/' . $context->tsumegos[0]['set-connections'][0]['id'];

		$browser = Browser::instance();
		$browser->get($playPath);
		$browser->openPlayEditSection();
		$browser->clickId('pass-on');
		$browser->clickId('variant-type-multiple-choice');
		$browser->clickId('tsumego-edit-submit');
		//Back on the problem, which has a multiple choice question to edit now
		$browser->waitUntilAnyCssSelectorExists(['#variant-answer1']);

		$this->assertStringEndsWith($playPath, $browser->driver->getCurrentURL());
		$this->assertSame(1, (int) $this->fetchTable('Tsumego')->get($tsumegoID)->pass);
		$this->assertSame([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Black is dead']], self::variantsOf($tsumegoID));
	}

	public function testProblemSettingsAreForAdmins(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$this->post('tsumegos/edit/' . $context->tsumegos[0]['id'], ['pass' => 1]);

		$this->assertResponseCode(403);
	}

	public function testVariantSavesTheAnswersOfTheQuestion(): void
	{
		$context = $this->prepareSandboxProblem([['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Seki in sente']]);
		$tsumegoID = $context->tsumegos[0]['id'];

		$this->postEditForm($tsumegoID, ['variant_type' => (string) TsumegoVariantTable::TYPE_MULTIPLE_CHOICE,
			'answer1' => 'Ko', 'answer2' => 'Seki', 'answer3' => 'Dead', 'answer4' => 'Alive', 'explanation' => 'Look at the eye', 'correct_answer' => 2]);

		$this->assertResponseCode(302);
		$this->assertSame([['answer1' => 'Ko', 'answer2' => 'Seki', 'answer3' => 'Dead', 'answer4' => 'Alive', 'explanation' => 'Look at the eye', 'correct_answer' => 2]],
			$this->fetchTable('TsumegoVariant')->find()
				->select(['answer1', 'answer2', 'answer3', 'answer4', 'explanation', 'correct_answer'])->where(['tsumego_id' => $tsumegoID])->disableHydration()->toArray());
	}

	//The SGF file goes with the rest of the problem's form: what changed is saved, and the file goes on
	//to the page that sets up a new version
	public function testTheEditFormTakesAnSgfFileWithTheOtherChanges(): void
	{
		$context = $this->prepareSandboxProblem();
		$tsumegoID = $context->tsumegos[0]['id'];
		$sgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		$file = tempnam(sys_get_temp_dir(), 'sgf');
		file_put_contents($file, $sgf);
		$this->postWithUpload('tsumegos/edit/' . $tsumegoID, 'adminUpload',
			['name' => 'new.sgf', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => strlen($sgf)],
			$this->editFormOf($tsumegoID, ['hint' => 'look at the corner', 'set_connection_id' => $context->tsumegos[0]['set-connections'][0]['id'], 'sgf_description' => 'fixed the corner']));

		//saved right away, with the correct moves the server works out, and back to the problem
		$setConnectionID = $context->tsumegos[0]['set-connections'][0]['id'];
		$this->assertRedirect('/' . $setConnectionID);
		$saved = $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $tsumegoID])->orderByDesc('id')->first();
		$this->assertSame([$sgf, 'fixed the corner', 'B', 'aa'], [$saved->sgf, $saved->description, $saved->first_move_color, $saved->correct_moves]);
		$this->assertSame('look at the corner', $this->fetchTable('Tsumego')->get($tsumegoID)->hint);
	}

	//The browser sends the file field empty when no file was chosen: the SGF stays as it is
	public function testTheEditFormWithoutAnSgfFileKeepsTheSgf(): void
	{
		$context = $this->prepareSandboxProblem();
		$tsumegoID = $context->tsumegos[0]['id'];
		$sgfsBefore = $this->fetchTable('Sgf')->find()->select(['id', 'sgf'])->where(['tsumego_id' => $tsumegoID])->disableHydration()->toArray();

		$this->postWithUpload('tsumegos/edit/' . $tsumegoID, 'adminUpload',
			['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
			$this->editFormOf($tsumegoID, ['hint' => 'look at the corner', 'set_connection_id' => $context->tsumegos[0]['set-connections'][0]['id']]));

		$this->assertResponseCode(302);
		$this->assertSame('look at the corner', $this->fetchTable('Tsumego')->get($tsumegoID)->hint);
		$this->assertSame($sgfsBefore, $this->fetchTable('Sgf')->find()->select(['id', 'sgf'])->where(['tsumego_id' => $tsumegoID])->disableHydration()->toArray());
	}

	//A file that isn't an SGF stops the whole form, so nothing of it is saved
	public function testTheEditFormWithAnInvalidSgfSavesNothing(): void
	{
		$context = $this->prepareSandboxProblem();
		$tsumegoID = $context->tsumegos[0]['id'];
		$file = tempnam(sys_get_temp_dir(), 'sgf');
		file_put_contents($file, 'not an sgf');

		$this->postWithUpload('tsumegos/edit/' . $tsumegoID, 'adminUpload',
			['name' => 'new.sgf', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => 10],
			$this->editFormOf($tsumegoID, ['hint' => 'look at the corner', 'set_connection_id' => $context->tsumegos[0]['set-connections'][0]['id']]));

		$this->assertResponseCode(400);
		$this->assertNotSame('look at the corner', $this->fetchTable('Tsumego')->get($tsumegoID)->hint);
	}

	//When the player's color opposes the SGF's first move the board is inverted and
	//the description swaps Black<->White so the player sees their own color as the
	//side to move. (?playercolor sets up the scenario.)
	public function testDescriptionSwapsWhenPlayerColorOpposesFirstMove(): void
	{
		$blackFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		$context = new ContextPreparator([
			'user' => ['name' => 'descOpposeB'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play.', 'sgf' => $blackFirst],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertSame('White to play.', $this->shownDescription());

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descOpposeW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play.', 'sgf' => $whiteFirst],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context2->tsumegos[0]['id'] . '?playercolor=black');
		$this->assertSame('Black to play.', $this->shownDescription());
	}

	//The description the play page shows, which is the one the colour swap applies to: the color to move is a
	//stone (its label) before the rest of the goal
	private function shownDescription(): string
	{
		$dom = $this->getStringDom();
		$stone = $dom->querySelector('.play-goal__stone')?->getAttribute('aria-label');
		$text = trim((string) $dom->querySelector('#descriptionText')->textContent);
		return $stone !== null ? $stone . ' ' . $text : $text;
	}

	//When the player's color matches the SGF's first move the board is not inverted
	//and the description is shown exactly as stored. (?playercolor sets up the scenario.)
	public function testDescriptionDoesNotSwapWhenPlayerColorMatchesFirstMove(): void
	{
		$blackFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';

		$context = new ContextPreparator([
			'user' => ['name' => 'descMatchB'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play.', 'sgf' => $blackFirst],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=black');
		$this->assertStringContainsString('Black to play.', $this->view);

		$context2 = new ContextPreparator([
			'user' => ['name' => 'descMatchW'],
			'tsumego' => ['set_order' => 1, 'description' => 'White to play.', 'sgf' => $whiteFirst],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context2->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertStringContainsString('White to play.', $this->view);
	}

	//When the board is inverted, every color word in a description swaps, so a
	//description that names both colours stays internally consistent.
	public function testDescriptionSwapsAllColorWords(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descAllWords'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play. Find the way to kill the white group.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertStringContainsString('White to play. Find the way to kill the black group.', $this->view);
	}

	//Color swaps match whole words only, so a colour word that is part of a larger
	//word (like "Blackbird") is left untouched.
	public function testDescriptionSwapRespectsWordBoundaries(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descBoundary'],
			'tsumego' => ['set_order' => 1, 'description' => 'Black to play. The Blackbird group is white.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertStringContainsString('White to play. The Blackbird group is black.', $this->view);
	}

	//Color swaps preserve the original casing of each word.
	public function testDescriptionSwapPreservesCase(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descCase'],
			'tsumego' => ['set_order' => 1, 'description' => 'black stones attack White stones.', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])'],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$this->assertStringContainsString('white stones attack Black stones.', $this->view);
	}

	//A problem without a description has the default goal, "<color> to play" for the color to move: a stone of
	//that color and "to play", in the color the player moves with, so it follows the swap of the colors
	public function testAnEmptyDescriptionIsTheColorToPlay(): void
	{
		$blackFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
		$whiteFirst = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])';
		foreach ([[$blackFirst, 'black', 'Black to play'], [$blackFirst, 'white', 'White to play'], [$whiteFirst, 'white', 'White to play'], [$whiteFirst, 'black', 'Black to play']] as $i => [$sgf, $playerColor, $goal])
		{
			$context = new ContextPreparator(['user' => ['name' => 'emptyGoal' . $i], 'tsumego' => ['set_order' => 1, 'description' => '', 'sgf' => $sgf]]);
			Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
			$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=' . $playerColor);
			$this->assertSame($goal, $this->shownDescription(), 'An SGF case ' . $i . ' played as ' . $playerColor);
		}
	}

	//A multiple choice problem has no move to play, so it gets no default goal
	public function testAMultipleChoiceProblemWithoutADescriptionHasNoGoal(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'description' => '', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[aa];B[ba]C[+])',
			'variants' => [['tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE, 'answer1' => 'Black is dead', 'answer2' => 'White is dead', 'answer3' => 'Ko', 'answer4' => 'Seki', 'correct_answer' => 1]]]]);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$this->assertSame('', $this->shownDescription());
	}

	//The admin's description field is empty for a problem with the default goal, which it shows as its placeholder
	public function testTheEditFormShowsTheDefaultGoalAsThePlaceholder(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'description' => '', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])']]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id'] . '?playercolor=white');
		$field = $this->getStringDom()->querySelector('textarea#description');
		$this->assertSame('', $field->textContent);
		$this->assertSame('White to play', $field->getAttribute('placeholder'));
	}

	//Saving the default goal of the problem stores no description (NULL), which means it; any other goal is stored
	//as written. The form sends the colors the admin saw, so on a swapped board "White to play" is Black's default.
	public function testSavingTheDefaultGoalStoresNoDescription(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'description' => 'Black to kill',
			'sgf' => ['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])', 'first_move_color' => 'B']]]);
		$tsumegoID = $context->tsumegos[0]['id'];
		$saved = [];
		foreach ([['Black to play', '0'], ['black to play.', '0'], ['White to play', '1'], ['White to play', '0'], ['Black to play and live', '0']] as [$description, $swapped])
		{
			$this->postEditForm($tsumegoID, ['description' => $description, 'color_swapped' => $swapped]);
			$saved[] = $this->fetchTable('Tsumego')->get($tsumegoID)->description;
		}
		$this->assertSame([null, null, null, 'White to play', 'Black to play and live'], $saved);
	}

	//A White-first problem in a collection that forces the player to black (small
	//board) inverts the board and swaps the description automatically, without any
	//URL override. This is the reported "Problems from Professional Games" case.
	public function testForcedBlackWhiteFirstSwapsDescription(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'descForcedBlack'],
			'tsumego' => [
				'sets' => [['name' => 'Small Board Set', 'num' => 1]],
				'description' => 'White to play. Find the way to kill the black group.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[9];W[aa];B[ab];W[ba]C[+])',
			],
		]);
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);
		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);
		$this->assertStringContainsString('Black to play. Find the way to kill the white group.', $this->view);
	}
}
