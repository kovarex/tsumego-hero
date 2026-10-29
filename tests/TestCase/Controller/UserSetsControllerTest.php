<?php

use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\Constants;

class UserSetsControllerTest extends IntegrationTestCase
{
	private function _createSet(string $title, ?int $userId, int $public): int
	{
		$setTable = $this->fetchTable('Set');
		$createdSet = $setTable->saveOrFail($setTable->newEntity([
			'Set' => [
				'title' => $title,
				'user_id' => $userId,
				'public' => $public,
				'order' => Constants::$DEFAULT_SET_ORDER,
			],
		]));
		return $createdSet->id;
	}

	// ── /sets/mysets ────────────────────────────────────────────────────

	public function testUserSetsShowsOwnPrivateSets(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userId = $context->user['id'];
		$this->_createSet('My Puzzles', $userId, 0);
		$this->login('alice');

		$this->get("/sets/mine");
		$this->assertTextContains('My Puzzles', $this->view);
	}

	// ── /sets/create ────────────────────────────────────────────────────

	public function testCreateUserSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->login('alice');

		$data = ['Set' => ['title' => 'My New Set', 'description' => 'My collection', 'color' => '#74d14c']];
		$this->post('/sets/create', $data);

		$set = $this->fetchTable('Set')->find()->where(['title' => 'My New Set'])->first();
		$this->assertNotEmpty($set);
		$this->assertEquals($context->user['id'], $set->user_id);
		$this->assertEquals(0, $set->public);
		$this->assertEquals('My collection', $set->description);
		$this->assertEquals('#74d14c', $set->color);
		$this->assertStringContainsString('/sets/edit/', $this->headerValue('Location'));
	}

	public function testCreateSandboxSet(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->login('admin');

		$data = ['Set' => ['title' => 'New Sandbox']];
		$this->post('/sets/create?sandbox=1', $data);

		$this->assertStringContainsString('/sets/edit/', $this->headerValue('Location'));

		$set = $this->fetchTable('Set')->find()->where(['title' => 'New Sandbox'])->first();
		$this->assertNotEmpty($set);
		$this->assertNull($set->user_id);

		$tsumego = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first();
		$this->assertNotEmpty($tsumego);
		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $set->id, 'tsumego_id' => $tsumego->id])->first();
		$this->assertNotEmpty($setConnection);
	}

	public function testCreateSandboxRequiresAdmin(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->login('alice');

		$data = ['Set' => ['title' => 'Sneaky Sandbox', 'sandbox' => '1']];
		$this->post('/sets/create', $data);

		$set = $this->fetchTable('Set')->find()->where(['title' => 'Sneaky Sandbox'])->first();
		$this->assertNotEmpty($set);
		$this->assertEquals($context->user['id'], $set->user_id);
	}

	public function testCreateRequiresLogin(): void
	{
		$data = ['Set' => ['title' => 'Guest Set']];

		$this->post('/sets/create', $data);

		$this->assertResponseCode(401);
	}

	// ── /sets/edit/:id ──────────────────────────────────────────────────

	public function testEditOwnSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$data = ['Set' => ['title' => 'Renamed', 'description' => 'New desc', 'color' => '#3366cc', 'order' => '7']];
		$this->post("/sets/edit/{$setId}", $data);

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertEquals('Renamed', $set->title);
		$this->assertEquals('New desc', $set->description);
		$this->assertEquals('#3366cc', $set->color);
		$this->assertEquals(7, $set->order);
	}

	public function testEditPageRenders(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/edit/{$setId}");
		$this->assertTextContains('Edit Set', $this->view);
		$this->assertTextContains('My Set', $this->view);
	}

	public function testEditPageListsProblems(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => [
				'sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]],
				'sgf' => '(;GM[1]FF[4]SZ[19])',
			],
		]);
		$set = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);
		$setId = $set->id;

		$this->login('alice');

		$this->post("/sets/edit/{$setId}");
		$this->assertTextContains('Edit Set', $this->view);
		$this->assertTextContains((string) $context->tsumegos[0]['id'], $this->view);
	}

	public function testEditPageForbiddenForNonOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('bob');

		$this->post("/sets/edit/{$setId}");

		$this->assertResponseCode(403);
	}

	public function testEditOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('bob');

		$this->post("/sets/edit/{$setId}", ['Set' => ['title' => 'Hacked']]);

		$this->assertResponseCode(403);
	}

	public function testAdminCanEditAnySet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'admin', 'admin' => true]],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('admin');

		$data = ['Set' => ['title' => 'Fixed by Admin']];
		$this->post("/sets/edit/{$setId}", $data);

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertEquals('Fixed by Admin', $set->title);
	}

	public function testEditPageAsksForTitleToDeletePublicSiteSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'admin', 'admin' => true]],
		]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('admin');

		$this->post("/sets/edit/{$setId}");
		$this->assertTextContains('Delete Collection', $this->view);
		$this->assertTextContains('id="set-delete-confirmation"', $this->view);
	}

	public function testAnonymousCannotEditPublicSetTitle(): void
	{
		new ContextPreparator(['user' => null]);
		$setId = $this->_createSet('Public Set', null, 1);

		$this->post("/sets/edit/{$setId}", ['Set' => ['title' => 'Hacked']]);

		$this->assertResponseCode(401);
	}

	public function testNonOwnerCannotEditPublicSetTitle(): void
	{
		new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('alice');

		$this->post("/sets/edit/{$setId}", ['Set' => ['title' => 'Hacked']]);

		$this->assertResponseCode(403);
	}

	public function testNonAdminCannotToggleSetSettings(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'My Set', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
			],
		]);
		$this->login('alice');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->post("/sets/edit/{$setId}", ['Settings' => ['r43' => 'yes']]);

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertSame(0, (int) $tsumego->pass);
	}

	// ── /sets/view/:id ──────────────────────────────────────────────────

	public function testViewOwnPrivateSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Private Set', $context->user['id'], 0);
		$this->login('alice');

		$this->get("/sets/view/{$setId}");
		$this->assertTextContains('My Private Set', $this->view);
	}

	//A collection without problems has nothing to start with: no Start that would lead to the home page
	public function testAnEmptyCollectionOffersNoStart(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Still Empty', $context->user['id'], 0);
		$this->login('alice');

		$this->get("/sets/view/{$setId}");
		$this->assertResponseOk();
		$this->assertNull($this->getStringDom()->querySelector('.set-view__start'));
	}

	public function testACollectionStartsWithItsProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'one problem', 'num' => 1]]]]);
		$setConnection = $context->tsumegos[0]['set-connections'][0];

		$this->get('/sets/view/' . $setConnection['set_id']);
		$this->assertSame('/' . $setConnection['id'], $this->getStringDom()->querySelector('.set-view__start')?->getAttribute('href'));
	}

	public function testViewOtherUserPrivateSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Secret', $context->user['id'], 0);
		$this->login('bob');

		$this->get("/sets/view/{$setId}");

		$this->assertResponseCode(403);
		$this->assertStringNotContainsString('Alice Secret', $this->view);
	}

	// ── /sets/delete/:id ────────────────────────────────────────────────

	public function testDeleteUserSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('To Delete', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/delete/{$setId}");

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertEmpty($set);
	}

	public function testDeleteOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('bob');

		$this->post("/sets/delete/{$setId}");

		$this->assertResponseCode(403);
	}

	// ── Sandbox excludes user sets ──────────────────────────────────────

	public function testSandboxExcludesUserSets(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$adminId = ContextPreparator::findUserRow(['name' => 'admin'])['id'];
		$this->_createSet('Sandbox Set', null, 0);
		$this->_createSet('User Set', $adminId, 0);
		$this->login('admin');

		$this->get('/sets/sandbox');
		$this->assertTextContains('Sandbox Set', $this->view);
		$this->assertTextNotContains('User Set', $this->view);
	}

	// ── addTsumego ──────────────────────────────────────────────────────

	public function testAddTsumegoWithExistingTsumegoId(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->post("/sets/addTsumego/{$setId}", $data);

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headerValue('Location'));

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId, 'tsumego_id' => $tsumegoId])->first();
		$this->assertNotEmpty($setConnection);
	}

	public function testAddTsumegoToOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('bob');

		$data = ['tsumego_id' => 1];

		$this->post("/sets/addTsumego/{$setId}", $data);

		$this->assertResponseCode(403);
	}

	// ── removeTsumego ───────────────────────────────────────────────────

	public function testRemoveTsumegoFromSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => [
				'sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]],
				'sgf' => '(;GM[1]FF[4]SZ[19])',
			],
		]);
		$set = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first();
		$setId = $set->id;
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);

		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->post("/sets/removeTsumego/{$setId}", $data);

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headerValue('Location'));

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId, 'tsumego_id' => $tsumegoId])->first();
		$this->assertEmpty($setConnection);
	}

	// ── Default "Favorites" set: lazy creation ──────────────────────────

	public function testDefaultSetCreatedLazilyOnFirstAdd(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;
		$this->login('alice');

		$before = $this->fetchTable('Set')->find()->where(['user_id' => $context->user['id']])->count();
		$this->assertEquals(0, $before);

		$data = ['tsumego_id' => $tsumegoId];
		$this->post('/sets/addTsumego/favorites', $data);

		$set = $this->fetchTable('Set')->find()->where(['user_id' => $context->user['id'], 'title' => 'Favorites'])->first();
		$this->assertNotEmpty($set);
		$this->assertEquals(0, $set->public);

		$user = UserTable::row((int) $context->user['id']);
		$this->assertEquals($set->id, $user['default_set_id']);

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $set->id, 'tsumego_id' => $tsumegoId])->first();
		$this->assertNotEmpty($setConnection);
	}

	public function testAddTsumegoToFavoritesReturnsSetId(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;
		$this->login('alice');

		// The heart answers a JSON body to the fetch the page makes, and a redirect to a form.
		$this->configRequest(['headers' => ['X-Requested-With' => 'XMLHttpRequest']]);
		$this->post('/sets/addTsumego/favorites', ['tsumego_id' => $tsumegoId]);

		$this->assertResponseCode(200);
		$body = json_decode($this->view, true);

		$set = $this->fetchTable('Set')->find()->where(['user_id' => $context->user['id'], 'title' => 'Favorites'])->first();
		$this->assertNotEmpty($set);
		$this->assertTrue($body['contains']);
		$this->assertSame((int) $set->id, (int) $body['set_id']);
		$this->assertSame('Favorites', $body['title']);
	}

	public function testHeartAddDoesNotDuplicateSetConnections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Favorites', $context->user['id'], 0);
		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumegoId = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['variance' => 100, 'author' => '']))->id;
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->post("/sets/addTsumego/{$setId}", $data);

		$this->post("/sets/addTsumego/{$setId}", $data);

		$this->assertResponseCode(409);

		$count = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId, 'tsumego_id' => $tsumegoId])->count();
		$this->assertEquals(1, $count);
	}

	// ── Reorder ─────────────────────────────────────────────────────────

	public function testReorderTsumegoUp(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
				['sets' => [['name' => 'My Set', 'num' => 2, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$setId = (int) $setId;
		// Set user_id
		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);

		$secondTsumego = $context->tsumegos[1];
		$this->login('alice');

		// Move second problem up
		$this->post("/sets/reorderTsumego/{$setId}?tsumego_id={$secondTsumego['id']}&dir=up");

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headerValue('Location'));

		// Verify nums swapped
		$setConnections = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->orderBy('num')->toArray();
		$this->assertEquals($secondTsumego['id'], $setConnections[0]->tsumego_id);
	}

	public function testReorderTsumegoDown(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
				['sets' => [['name' => 'My Set', 'num' => 2, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);

		$firstTsumego = $context->tsumegos[0];
		$this->login('alice');

		$this->post("/sets/reorderTsumego/{$setId}?tsumego_id={$firstTsumego['id']}&dir=down");

		$setConnections = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->orderBy('num')->toArray();
		$this->assertEquals($firstTsumego['id'], $setConnections[1]->tsumego_id);
	}

	// ── User sets for others ────────────────────────────────────────────

	public function testUserSetsShowsOthersPublicSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'display_name' => 'Alice Name'],
			'other-users' => [['name' => 'bob']],
		]);
		$aliceId = $context->user['id'];
		$this->_createSet('Alice Public Set', $aliceId, 1);
		$this->_createSet('Alice Private Set', $aliceId, 0);
		$this->login('bob');

		$this->get("/sets/user/{$aliceId}");
		$this->assertTextContains('Alice Name', $this->view);
		$this->assertTextContains('Alice Public Set', $this->view);
		$this->assertTextNotContains('Alice Private Set', $this->view);
	}

	// ── Aggregation (amount / solved / difficulty) ──────────────────────

	public function testMineComputesAmountSolvedAndDifficulty(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['status' => 'S', 'sets' => [['name' => 'My Set', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
				['status' => 'S', 'sets' => [['name' => 'My Set', 'num' => 2, 'user_id' => 'self', 'public' => 0]]],
				['sets' => [['name' => 'My Set', 'num' => 3, 'user_id' => 'self', 'public' => 0]]],
			],
		]);
		$this->login('alice');

		$this->get('/sets/mine');
		$result = $this->viewVariable('setsNew');

		$this->assertCount(1, $result);
		$set = $result[0];
		$this->assertSame('My Set', $set['name']);
		$this->assertSame(3, $set['amount']);
		$this->assertSame(67, $set['solved']);
		$this->assertSame('11k', $set['difficulty']);
	}

	public function testMineShowsVisibleProgressBar(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['status' => 'S', 'sets' => [['name' => 'My Set', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$browser = Browser::instance();
		$browser->get('sets/mine');

		// The colored fill must actually be visible (not a 0px transparent div)
		$fill = $browser->find('.progress__fill');
		$this->assertSame('5px', $fill->getCSSValue('height'));
		$this->assertTextContains('width: 100%', $fill->getAttribute('style'));
	}

	// ── Description sanitization ────────────────────────────────────────

	public function testDescriptionIsSanitizedOnSave(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$description = 'External <img src="https://evil.com/tracker.png"> Internal <img src="/img/ok.png"> '
			. '<b onmouseover="alert(1)">hi</b> <a href="javascript:alert(2)">click</a>';
		$this->post("/sets/edit/{$setId}", ['Set' => ['description' => $description]]);

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertStringNotContainsString('evil.com', $set->description);
		$this->assertStringContainsString('/img/ok.png', $set->description);
		$this->assertStringNotContainsString('onmouseover', $set->description);
		$this->assertStringNotContainsString('javascript:', $set->description);
	}

	// ── Admin activity logging ──────────────────────────────────────────

	public function testEditingOwnSetDoesNotLogAdminActivity(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/edit/{$setId}", ['Set' => ['description' => 'New desc']]);

		$this->assertSame(0, $this->fetchTable('AdminActivity')->find()->count());
	}

	public function testAdminEditingOthersSetLogsActivity(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'other-users' => [['name' => 'bob']],
		]);
		$bobId = ContextPreparator::findUserRow(['name' => 'bob'])['id'];
		$setId = $this->_createSet('Bob Set', $bobId, 0);
		$this->login('admin');

		$this->post("/sets/edit/{$setId}", ['Set' => ['description' => 'New desc']]);

		$activity = $this->fetchTable('AdminActivity')->find()->orderByDesc('id')->firstOrFail();
		$this->assertNotEmpty($activity);
		$this->assertSame(AdminActivityTypeTable::SET_DESCRIPTION_EDIT, $activity->type);
	}

	public function testCreateAndAddTsumegoLogsProblemAdd(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'set' => ['title' => 'Sandbox Set', 'public' => 0],
		]);
		$setId = $context->set['id'];
		$this->login('admin');

		$this->post("/sets/createAndAddTsumego/{$setId}", ['order' => 1]);

		$activity = $this->fetchTable('AdminActivity')->find()->orderByDesc('id')->firstOrFail();
		$this->assertNotEmpty($activity);
		$this->assertSame(AdminActivityTypeTable::PROBLEM_ADD, $activity->type);
	}

	// ── Re-rate problems (admin only) ───────────────────────────────────

	public function testNonAdminCannotReRateProblems(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'My Set', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
			],
		]);
		$this->login('alice');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->post("/sets/edit/{$setId}", ['Set' => ['setDifficulty' => 2000]]);

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertEquals(1000, $tsumego->rating);
	}

	public function testAdminCanReRateProblems(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'Sandbox Set', 'num' => 1, 'public' => 0]]],
			],
		]);
		$this->login('admin');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'Sandbox Set'])->first()->id;
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$this->post("/sets/edit/{$setId}", ['Set' => ['setDifficulty' => 2000]]);

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertEquals(2000, $tsumego->rating);
	}

	//The rating column is a float: the new rating keeps its fraction, nothing cuts it to an integer
	//(#474), and each problem stays within its own minimum and maximum.
	public function testReRatingKeepsTheFractionAndTheLimitsOfEachProblem(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'Sandbox Set', 'num' => 1, 'public' => 0]]],
				['rating' => 1000, 'maximum_rating' => 1500, 'sets' => [['name' => 'Sandbox Set', 'num' => 2, 'public' => 0]]],
				['rating' => 1000, 'minimum_rating' => 1700.25, 'sets' => [['name' => 'Sandbox Set', 'num' => 3, 'public' => 0]]],
			],
		]);
		$this->login('admin');
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$this->post("/sets/edit/{$setId}", ['Set' => ['setDifficulty' => '1600.5']]);

		$ratings = $this->fetchTable('Tsumego')->find('list', valueField: 'rating')->where(['id IN' => array_column($context->tsumegos, 'id')])->orderBy('id')->toArray();
		$this->assertSame([1600.5, 1500.0, 1700.25], array_values($ratings));
	}

	public function testEmptyRatingFieldDoesNotReRate(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'Sandbox Set', 'num' => 1, 'public' => 0]]],
			],
		]);
		$this->login('admin');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'Sandbox Set'])->first()->id;
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$this->post("/sets/edit/{$setId}");

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertEquals(1000, $tsumego->rating);
	}

	public function testAdminCanToggleAlternativeResponseOn(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['rating' => 1000, 'alternative_response' => 0, 'sets' => [['name' => 'Sandbox Set', 'num' => 1, 'public' => 0]]],
			],
		]);
		$this->login('admin');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'Sandbox Set'])->first()->id;
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$this->post("/sets/edit/{$setId}", ['Settings' => ['r39' => 'on']]);

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertSame(1, (int) $tsumego->alternative_response);
	}

	public function testAdminCanTogglePassingOn(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['rating' => 1000, 'pass' => 0, 'sets' => [['name' => 'Sandbox Set', 'num' => 1, 'public' => 0]]],
			],
		]);
		$this->login('admin');
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'Sandbox Set'])->first()->id;
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$this->post("/sets/edit/{$setId}", ['Settings' => ['r43' => 'yes']]);

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoId])->first();
		$this->assertSame(1, (int) $tsumego->pass);
	}

	// ── Authorization: mutate actions on someone else's set ─────────────

	public function testRemoveTsumegoFromOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumegoId = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['variance' => 100, 'author' => '']))->id;
		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => 1]));
		$this->login('bob');

		$this->post("/sets/removeTsumego/{$setId}", ['tsumego_id' => $tsumegoId]);

		$this->assertResponseCode(403);
	}

	public function testReorderTsumegoInOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [
				['sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
				['sets' => [['name' => 'My Set', 'num' => 2, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);
		$this->login('bob');

		$this->post("/sets/reorderTsumego/{$setId}?tsumego_id={$context->tsumegos[1]['id']}&dir=up");

		$this->assertResponseCode(403);
	}

	public function testReorderAtBoundaryReturnsConflict(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
				['sets' => [['name' => 'My Set', 'num' => 2, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);
		$setId = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->first()->id;
		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);
		$this->login('alice');

		$this->post("/sets/reorderTsumego/{$setId}?tsumego_id={$context->tsumegos[0]['id']}&dir=up");

		$this->assertResponseCode(409);

		$setConnections = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->orderBy('num')->toArray();
		$this->assertEquals($context->tsumegos[0]['id'], $setConnections[0]->tsumego_id);
		$this->assertEquals($context->tsumegos[1]['id'], $setConnections[1]->tsumego_id);
	}

	public function testCreateAndAddTsumegoRequiresAdmin(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/createAndAddTsumego/{$setId}", ['order' => 1]);

		$this->assertResponseCode(403);
	}

	public function testAdminCanDeleteSandboxSet(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->login('admin');
		$setId = $this->_createSet('Sandbox Set', null, 0);

		$this->post("/sets/delete/{$setId}", ['confirmation' => 'Sandbox Set']);

		$this->assertNotNull($this->fetchTable('Set')->get($setId)->deleted, 'It went to the trash');
	}

	// ── Sharing a collection with a link ────────────────────────────────

	public function testOwnerCanShareOwnCollection(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/share/{$setId}", ['shared' => '1']);

		$this->assertEquals(1, $this->fetchTable('Set')->get($setId)->public);
	}

	public function testOwnerCanStopSharingOwnCollection(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->post("/sets/share/{$setId}", ['shared' => '0']);

		$this->assertEquals(0, $this->fetchTable('Set')->get($setId)->public);
	}

	public function testChangingSharingReturnsToTheCollection(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 0);
		$this->login('alice');

		$this->post("/sets/share/{$setId}", ['shared' => '1']);

		$this->assertStringContainsString("/sets/{$setId}", $this->headerValue('Location'));
	}

	public function testTheAddressOfASharedLinkStillOpensTheCollection(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Shared Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->get("/sets/view/{$setId}");
		$page = $this->view;

		$this->assertStringContainsString('Shared Collection', $page);
	}

	public function testAnotherPlayerCannotChangeSharing(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 0);
		$this->login('bob');

		$this->post("/sets/share/{$setId}", ['shared' => '1']);

		$this->assertResponseCode(403);
	}

	public function testGuestCannotChangeSharing(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'owner']]]);
		$setId = $this->_createSet('Owner Collection', $context->otherUsers[0]['id'], 0);
		$this->logout();

		$this->post("/sets/share/{$setId}", ['shared' => '1']);

		$this->assertResponseCode(401);
	}

	public function testTheSiteKeepsTheVisibilityOfItsOwnCollections(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$setId = $this->_createSet('Site Collection', null, 1);
		$this->login('admin');

		$this->post("/sets/share/{$setId}", ['shared' => '0']);

		$this->assertResponseCode(403);

		$this->assertEquals(1, $this->fetchTable('Set')->get($setId)->public);
	}

	public function testEditFormNeverChangesTheVisibility(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->post("/sets/edit/{$setId}", ['Set' => ['public' => '0']]);

		$this->assertEquals(1, $this->fetchTable('Set')->get($setId)->public,
			'sharing is decided on the collection page, not by the edit form');
	}

	public function testCollectionPageOffersSharingToItsOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$ownSetId = $this->_createSet('Alice Collection', $context->user['id'], 0);
		$otherSetId = $this->_createSet('Bob Collection', $context->otherUsers[0]['id'], 1);
		$siteSetId = $this->_createSet('Site Collection', null, 1);
		$this->login('alice');

		$this->get("/sets/view/{$ownSetId}");
		$ownPage = $this->view;
		$this->get("/sets/view/{$otherSetId}");
		$otherPage = $this->view;
		$this->get("/sets/view/{$siteSetId}");
		$sitePage = $this->view;

		$this->assertStringContainsString('Anyone with the link can open this collection', $ownPage);
		$this->assertStringNotContainsString('Anyone with the link can open this collection', $otherPage);
		$this->assertStringNotContainsString('Anyone with the link can open this collection', $sitePage);
	}

	public function testCollectionPageShowsTheAddressOfASharedCollection(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$sharedSetId = $this->_createSet('Shared Collection', $context->user['id'], 1);
		$privateSetId = $this->_createSet('Private Collection', $context->user['id'], 0);
		$this->login('alice');

		$this->get("/sets/view/{$sharedSetId}");
		$sharedPage = $this->view;
		$this->get("/sets/view/{$privateSetId}");
		$privatePage = $this->view;

		$this->assertMatchesRegularExpression(
			'#<input[^>]*id="SetShareLink"[^>]*value="https?://[^"]*/sets/' . $sharedSetId . '"#',
			$sharedPage);
		$this->assertStringNotContainsString('id="SetShareLink"', $privatePage);
	}

	public function testCollectionPageNamesTheOwnerWithALinkToTheirProfile(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob', 'display_name' => 'Bobby']],
		]);
		$bobbySetId = $this->_createSet('Bobby Collection', $context->otherUsers[0]['id'], 1);
		$this->login('alice');

		$this->get("/sets/{$bobbySetId}");
		$page = $this->view;

		$this->assertMatchesRegularExpression(
			'#<a href="/users/view/' . $context->otherUsers[0]['id'] . '">.*?Bobby.*?</a>#',
			$page);
	}

	public function testCollectionPageNamesNoOwnerForTheSitesCollection(): void
	{
		new ContextPreparator(['user' => ['name' => 'alice']]);
		$siteSetId = $this->_createSet('Site Collection', null, 1);
		$this->login('alice');

		$this->get("/sets/{$siteSetId}");
		$page = $this->view;

		$this->assertStringNotContainsString('set-view__owner', $page);
	}

	public function testOwnCollectionNamesNoOwner(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->get("/sets/{$setId}");
		$page = $this->view;

		$this->assertStringNotContainsString('set-view__owner', $page);
	}

	public function testGuestCanOpenASharedCollection(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'owner']]]);
		$setId = $this->_createSet('Shared Collection', $context->otherUsers[0]['id'], 1);
		$this->logout();

		$this->get("/sets/view/{$setId}");

		$this->assertTextContains('Shared Collection', $this->view);
	}

	public function testGuestCannotOpenACollectionThatIsNotShared(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'owner']]]);
		$setId = $this->_createSet('Private Collection', $context->otherUsers[0]['id'], 0);
		$this->logout();

		$this->get("/sets/view/{$setId}");

		$this->assertResponseCode(401);
	}

	public function testPlayerCollectionPageIsNotIndexed(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Shared Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->get("/sets/view/{$setId}");
		$page = $this->view;

		$this->assertStringContainsString('name="robots" content="noindex"', $page);
	}

	public function testSiteCollectionPageIsIndexed(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Site Collection', null, 1);
		$this->login('alice');

		$this->get("/sets/view/{$setId}");
		$page = $this->view;

		$this->assertStringNotContainsString('name="robots" content="noindex"', $page);
	}

	// ── Set image upload ───────────────────────────────────────────────

	public function testUploadSetImageStoresWebp(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$tmp = $this->_createTempImage(400, 200);
		$_FILES['image'] = [
			'name' => 'cover.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($tmp),
		];

		$this->post("/sets/edit/{$setId}");

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertMatchesRegularExpression('#^sets/' . $setId . '/[0-9a-f]{16}\.webp$#', $set->image);

		$path = WWW_ROOT . 'img' . DS . str_replace('/', DS, $set->image);
		$this->assertFileExists($path);
		$this->assertSame('WEBP', substr((string) file_get_contents($path), 8, 4));

		@unlink($tmp);
		@unlink($path);
		@rmdir(WWW_ROOT . 'img' . DS . 'sets' . DS . $setId);
	}

	//The cover lives in a directory the web server has to be able to write to.
	//When it cannot, the player is told the upload failed and no image is saved.
	public function testUploadSetImageReportsWhenTheFileCannotBeWritten(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$tmp = $this->_createTempImage(400, 200);
		$_FILES['image'] = [
			'name' => 'cover.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($tmp),
		];

		$setsDir = WWW_ROOT . 'img' . DS . 'sets';
		$permissions = fileperms($setsDir) & 0777;
		chmod($setsDir, 0555);
		try
		{
			$this->post("/sets/edit/{$setId}");
		}
		finally
		{
			chmod($setsDir, $permissions);
			@unlink($tmp);
		}

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertEmpty($set->image, 'An image was saved although its file could not be written');
		// The edit form renders the flash it was given, so that is where the player reads it.
		$this->assertStringContainsString('The image could not be stored', $this->view);
	}

	public function testDeleteSetRemovesImageFolder(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$setDir = WWW_ROOT . 'img' . DS . 'sets' . DS . $setId;
		mkdir($setDir, 0775, true);
		file_put_contents($setDir . DS . 'abc.webp', 'fake');

		$this->post("/sets/delete/{$setId}");

		$this->assertDirectoryDoesNotExist($setDir);
	}

	public function testUploadSetImageReplacesPreviousImage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->_uploadImage($setId, 400, 200);
		$first = $this->fetchTable('Set')->get($setId)->image;
		$firstPath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $first);

		$this->_uploadImage($setId, 500, 300);
		$second = $this->fetchTable('Set')->get($setId)->image;

		$this->assertNotSame($first, $second);
		$this->assertFileDoesNotExist($firstPath);
		$this->assertFileExists(WWW_ROOT . 'img' . DS . str_replace('/', DS, $second));

		@unlink(WWW_ROOT . 'img' . DS . str_replace('/', DS, $second));
		@rmdir(WWW_ROOT . 'img' . DS . 'sets' . DS . $setId);
	}

	public function testUploadSetImagePreservesSharedImage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$shared = 'legacy-cover.png';
		file_put_contents(WWW_ROOT . 'img' . DS . $shared, 'x');
		$this->fetchTable('Set')->updateAll(['image' => $shared], ['id' => $setId]);

		$this->_uploadImage($setId, 400, 200);

		$this->assertFileExists(WWW_ROOT . 'img' . DS . $shared);

		$uploaded = $this->fetchTable('Set')->get($setId)->image;
		@unlink(WWW_ROOT . 'img' . DS . $shared);
		@unlink(WWW_ROOT . 'img' . DS . str_replace('/', DS, $uploaded));
		@rmdir(WWW_ROOT . 'img' . DS . 'sets' . DS . $setId);
	}

	public function testRemoveImageDeletesFileAndClearsField(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->_uploadImage($setId, 400, 200);
		$imagePath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $this->fetchTable('Set')->get($setId)->image);
		$this->assertFileExists($imagePath);

		$this->post("/sets/edit/{$setId}", ['Set' => ['remove_image' => '1']]);

		$set = $this->fetchTable('Set')->find()->where(['id' => $setId])->first();
		$this->assertSame('', $set->image);
		$this->assertFileDoesNotExist($imagePath);
	}

	private function _uploadImage(int $setId, int $width, int $height): void
	{
		$tmp = $this->_createTempImage($width, $height);
		$_FILES['image'] = [
			'name' => 'cover.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($tmp),
		];
		$this->post("/sets/edit/{$setId}");
		@unlink($tmp);
	}

	private function _createTempImage(int $width, int $height): string
	{
		$img = imagecreatetruecolor($width, $height);
		imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
		$path = tempnam(sys_get_temp_dir(), 'setimg');
		imagepng($img, $path);
		imagedestroy($img);
		return $path;
	}
}
