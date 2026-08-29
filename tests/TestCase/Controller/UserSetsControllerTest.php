<?php

App::uses('TestCaseWithAuth', 'TestSuite');
App::uses('ContextPreparator', 'TestSuite');
App::uses('ForbiddenException', 'Routing/Error');

class UserSetsControllerTest extends TestCaseWithAuth
{
	private function _createSet(string $title, ?int $userId, int $public): int
	{
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save([
			'Set' => [
				'title' => $title,
				'user_id' => $userId,
				'public' => $public,
				'order' => Constants::$DEFAULT_SET_ORDER,
			],
		]);
		return $setModel->id;
	}

	// ── /sets/mysets ────────────────────────────────────────────────────

	public function testUserSetsShowsOwnPrivateSets(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userId = $context->user['id'];
		$this->_createSet('My Puzzles', $userId, 0);
		$this->login('alice');

		$this->testAction("/sets/mine", ['return' => 'view']);
		$this->assertTextContains('My Puzzles', $this->view);
	}

	// ── /sets/create ────────────────────────────────────────────────────

	public function testCreateUserSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->login('alice');

		$data = ['Set' => ['title' => 'My New Set', 'description' => 'My collection', 'color' => '#74d14c']];
		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'My New Set'],
		]);
		$this->assertNotEmpty($set);
		$this->assertEquals($context->user['id'], $set['Set']['user_id']);
		$this->assertEquals(0, $set['Set']['public']);
		$this->assertEquals('My collection', $set['Set']['description']);
		$this->assertEquals('#74d14c', $set['Set']['color']);
		$this->assertStringContainsString('/sets/edit/', $this->headers['Location']);
	}

	public function testCreateSandboxSet(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->login('admin');

		$data = ['Set' => ['title' => 'New Sandbox']];
		$this->testAction('/sets/create?sandbox=1', ['data' => $data, 'method' => 'POST']);

		$this->assertStringContainsString('/sets/edit/', $this->headers['Location']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'New Sandbox'],
		]);
		$this->assertNotEmpty($set);
		$this->assertNull($set['Set']['user_id']);

		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($tsumego);
		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $set['Set']['id'], 'tsumego_id' => $tsumego['Tsumego']['id']],
		]);
		$this->assertNotEmpty($sc);
	}

	public function testCreateSandboxRequiresAdmin(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->login('alice');

		$data = ['Set' => ['title' => 'Sneaky Sandbox', 'sandbox' => '1']];
		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'Sneaky Sandbox'],
		]);
		$this->assertNotEmpty($set);
		$this->assertEquals($context->user['id'], $set['Set']['user_id']);
	}

	public function testCreateRequiresLogin(): void
	{
		$data = ['Set' => ['title' => 'Guest Set']];

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);
	}

	// ── /sets/edit/:id ──────────────────────────────────────────────────

	public function testEditOwnSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$data = ['Set' => ['title' => 'Renamed', 'description' => 'New desc', 'color' => '#3366cc', 'order' => '7']];
		$this->testAction("/sets/edit/{$setId}", ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertEquals('Renamed', $set['Set']['title']);
		$this->assertEquals('New desc', $set['Set']['description']);
		$this->assertEquals('#3366cc', $set['Set']['color']);
		$this->assertEquals(7, $set['Set']['order']);
	}

	public function testEditPageRenders(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->testAction("/sets/edit/{$setId}", ['return' => 'view']);
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
		$set = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']]);
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);
		$setId = $set['Set']['id'];

		$this->login('alice');

		$this->testAction("/sets/edit/{$setId}", ['return' => 'view']);
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

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/edit/{$setId}", ['return' => 'view']);
	}

	public function testEditOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('bob');

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['title' => 'Hacked']], 'method' => 'POST']);
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
		$this->testAction("/sets/edit/{$setId}", ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertEquals('Fixed by Admin', $set['Set']['title']);
	}

	public function testEditPageHidesDeleteForPublicSiteSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'admin', 'admin' => true]],
		]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('admin');

		$this->testAction("/sets/edit/{$setId}", ['return' => 'view']);
		$this->assertTextNotContains('Delete Collection', $this->view);
	}

	public function testAnonymousCannotEditPublicSetTitle(): void
	{
		new ContextPreparator(['user' => null]);
		$setId = $this->_createSet('Public Set', null, 1);

		$this->expectException(UnauthorizedException::class);

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['title' => 'Hacked']], 'method' => 'POST']);
	}

	public function testNonOwnerCannotEditPublicSetTitle(): void
	{
		new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Public Set', null, 1);
		$this->login('alice');

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['title' => 'Hacked']], 'method' => 'POST']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Settings' => ['r43' => 'yes']], 'method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertSame(0, (int) $tsumego['Tsumego']['pass']);
	}

	// ── /sets/view/:id ──────────────────────────────────────────────────

	public function testViewOwnPrivateSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Private Set', $context->user['id'], 0);
		$this->login('alice');

		$this->testAction("/sets/view/{$setId}", ['return' => 'view']);
		$this->assertTextContains('My Private Set', $this->view);
	}

	public function testViewOtherUserPrivateSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Secret', $context->user['id'], 0);
		$this->login('bob');

		try
		{
			$this->testAction("/sets/view/{$setId}", ['return' => 'view']);
			$this->assertTextNotContains('Alice Secret', $this->view);
		}
		catch (ForbiddenException $e)
		{
			// Expected: non-owner gets 403 for private set
			$this->assertTrue(true, 'Non-owner blocked from viewing private set');
		}
	}

	// ── /sets/delete/:id ────────────────────────────────────────────────

	public function testDeleteUserSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('To Delete', $context->user['id'], 0);
		$this->login('alice');

		$this->testAction("/sets/delete/{$setId}", ['method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
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

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/delete/{$setId}", ['method' => 'POST']);
	}

	// ── Sandbox excludes user sets ──────────────────────────────────────

	public function testSandboxExcludesUserSets(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$adminId = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'admin']])['User']['id'];
		$this->_createSet('Sandbox Set', null, 0);
		$this->_createSet('User Set', $adminId, 0);
		$this->login('admin');

		$this->testAction('/sets/sandbox', ['return' => 'view']);
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
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headers['Location']);

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId],
		]);
		$this->assertNotEmpty($sc);
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

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);
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
		$set = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']]);
		$setId = $set['Set']['id'];
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);

		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->testAction("/sets/removeTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headers['Location']);

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId],
		]);
		$this->assertEmpty($sc);
	}

	// ── Default "Favorites" set: lazy creation ──────────────────────────

	public function testDefaultSetCreatedLazilyOnFirstAdd(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];
		$this->login('alice');

		$before = ClassRegistry::init('Set')->find('count', [
			'conditions' => ['user_id' => $context->user['id']],
		]);
		$this->assertEquals(0, $before);

		$data = ['tsumego_id' => $tsumegoId];
		$this->testAction('/sets/addTsumego/favorites', ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
		]);
		$this->assertNotEmpty($set);
		$this->assertEquals(0, $set['Set']['public']);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals($set['Set']['id'], $user['User']['default_set_id']);

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $set['Set']['id'], 'tsumego_id' => $tsumegoId],
		]);
		$this->assertNotEmpty($sc);
	}

	public function testAddTsumegoToFavoritesReturnsSetId(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];
		$this->login('alice');

		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		try
		{
			$this->testAction('/sets/addTsumego/favorites', ['data' => ['tsumego_id' => $tsumegoId], 'method' => 'POST']);
		}
		finally
		{
			unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		}

		$this->assertSame(200, $this->controller->response->statusCode());
		$body = json_decode($this->controller->response->body(), true);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
		]);
		$this->assertNotEmpty($set);
		$this->assertTrue($body['contains']);
		$this->assertSame((int) $set['Set']['id'], (int) $body['set_id']);
		$this->assertSame('Favorites', $body['title']);
	}

	public function testHeartAddDoesNotDuplicateSetConnections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Favorites', $context->user['id'], 0);
		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->create();
		$tsumegoModel->save(['Tsumego' => ['difficulty' => 4, 'variance' => 100]]);
		$tsumegoId = $tsumegoModel->id;
		$this->login('alice');

		$data = ['tsumego_id' => $tsumegoId];
		$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);

		try
		{
			$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);
			$this->fail('Expected ConflictException');
		}
		catch (ConflictException $e)
		{
			// expected
		}

		$count = ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId],
		]);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$setId = (int) $setId;
		// Set user_id
		$set = ClassRegistry::init('Set')->findById($setId);
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);

		$secondTsumego = $context->tsumegos[1];
		$this->login('alice');

		// Move second problem up
		$this->testAction("/sets/reorderTsumego/{$setId}?tsumego_id={$secondTsumego['id']}&dir=up", ['method' => 'POST']);

		$this->assertStringContainsString("/sets/edit/{$setId}", $this->headers['Location']);

		// Verify nums swapped
		$scs = ClassRegistry::init('SetConnection')->find('all', [
			'conditions' => ['set_id' => $setId],
			'order' => 'num',
		]);
		$this->assertEquals($secondTsumego['id'], $scs[0]['SetConnection']['tsumego_id']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$set = ClassRegistry::init('Set')->findById($setId);
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);

		$firstTsumego = $context->tsumegos[0];
		$this->login('alice');

		$this->testAction("/sets/reorderTsumego/{$setId}?tsumego_id={$firstTsumego['id']}&dir=down", ['method' => 'POST']);

		$scs = ClassRegistry::init('SetConnection')->find('all', [
			'conditions' => ['set_id' => $setId],
			'order' => 'num',
		]);
		$this->assertEquals($firstTsumego['id'], $scs[1]['SetConnection']['tsumego_id']);
	}

	// ── User sets for others ────────────────────────────────────────────

	public function testUserSetsShowsOthersPublicSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$aliceId = $context->user['id'];
		$this->_createSet('Alice Public Set', $aliceId, 1);
		$this->_createSet('Alice Private Set', $aliceId, 0);
		$this->login('bob');

		$this->testAction("/sets/user/{$aliceId}", ['return' => 'view']);
		$this->assertTextContains("alice's Sets", $this->view);
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

		$result = $this->testAction('/sets/mine', ['return' => 'vars']);

		$this->assertCount(1, $result['setsNew']);
		$set = $result['setsNew'][0];
		$this->assertSame('My Set', $set['name']);
		$this->assertSame(3, $set['amount']);
		$this->assertSame(67, $set['solved']);
		$this->assertSame('11k', $set['difficulty']);
	}

	// ── Description sanitization ────────────────────────────────────────

	public function testDescriptionIsSanitizedOnSave(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$description = 'External <img src="https://evil.com/tracker.png"> Internal <img src="/img/ok.png"> '
			. '<b onmouseover="alert(1)">hi</b> <a href="javascript:alert(2)">click</a>';
		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['description' => $description]], 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertStringNotContainsString('evil.com', $set['Set']['description']);
		$this->assertStringContainsString('/img/ok.png', $set['Set']['description']);
		$this->assertStringNotContainsString('onmouseover', $set['Set']['description']);
		$this->assertStringNotContainsString('javascript:', $set['Set']['description']);
	}

	// ── Admin activity logging ──────────────────────────────────────────

	public function testEditingOwnSetDoesNotLogAdminActivity(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['description' => 'New desc']], 'method' => 'POST']);

		$this->assertSame(0, ClassRegistry::init('AdminActivity')->find('count'));
	}

	public function testAdminEditingOthersSetLogsActivity(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'other-users' => [['name' => 'bob']],
		]);
		$bobId = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'bob']])['User']['id'];
		$setId = $this->_createSet('Bob Set', $bobId, 0);
		$this->login('admin');

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['description' => 'New desc']], 'method' => 'POST']);

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($activity);
		$this->assertSame(AdminActivityType::SET_DESCRIPTION_EDIT, $activity['AdminActivity']['type']);
	}

	public function testCreateAndAddTsumegoLogsProblemAdd(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'set' => ['title' => 'Sandbox Set', 'public' => 0],
		]);
		$setId = $context->set['id'];
		$this->login('admin');

		$this->testAction("/sets/createAndAddTsumego/{$setId}", ['data' => ['order' => 1], 'method' => 'POST']);

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($activity);
		$this->assertSame(AdminActivityType::PROBLEM_ADD, $activity['AdminActivity']['type']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['setDifficulty' => 2000]], 'method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertEquals(1000, $tsumego['Tsumego']['rating']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'Sandbox Set']])['Set']['id'];
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['setDifficulty' => 2000]], 'method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertEquals(2000, $tsumego['Tsumego']['rating']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'Sandbox Set']])['Set']['id'];
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$this->testAction("/sets/edit/{$setId}", ['method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertEquals(1000, $tsumego['Tsumego']['rating']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'Sandbox Set']])['Set']['id'];
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Settings' => ['r39' => 'on']], 'method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertSame(1, (int) $tsumego['Tsumego']['alternative_response']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'Sandbox Set']])['Set']['id'];
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Settings' => ['r43' => 'yes']], 'method' => 'POST']);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoId);
		$this->assertSame(1, (int) $tsumego['Tsumego']['pass']);
	}

	// ── Authorization: mutate actions on someone else's set ─────────────

	public function testRemoveTsumegoFromOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->create();
		$tsumegoModel->save(['Tsumego' => ['difficulty' => 4, 'variance' => 100]]);
		$scModel = ClassRegistry::init('SetConnection');
		$scModel->create();
		$scModel->save(['SetConnection' => ['set_id' => $setId, 'tsumego_id' => $tsumegoModel->id, 'num' => 1]]);
		$this->login('bob');

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/removeTsumego/{$setId}", ['data' => ['tsumego_id' => $tsumegoModel->id], 'method' => 'POST']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$set = ClassRegistry::init('Set')->findById($setId);
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);
		$this->login('bob');

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/reorderTsumego/{$setId}?tsumego_id={$context->tsumegos[1]['id']}&dir=up", ['method' => 'POST']);
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
		$setId = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => 'My Set']])['Set']['id'];
		$set = ClassRegistry::init('Set')->findById($setId);
		$set['Set']['user_id'] = $context->user['id'];
		ClassRegistry::init('Set')->save($set);
		$this->login('alice');

		try
		{
			$this->testAction("/sets/reorderTsumego/{$setId}?tsumego_id={$context->tsumegos[0]['id']}&dir=up", ['method' => 'POST']);
			$this->fail('Expected ConflictException');
		}
		catch (ConflictException $e)
		{
			// expected
		}

		$scs = ClassRegistry::init('SetConnection')->find('all', [
			'conditions' => ['set_id' => $setId],
			'order' => 'num',
		]);
		$this->assertEquals($context->tsumegos[0]['id'], $scs[0]['SetConnection']['tsumego_id']);
		$this->assertEquals($context->tsumegos[1]['id'], $scs[1]['SetConnection']['tsumego_id']);
	}

	public function testCreateAndAddTsumegoRequiresAdmin(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('alice');

		$this->expectException(ForbiddenException::class);

		$this->testAction("/sets/createAndAddTsumego/{$setId}", ['data' => ['order' => 1], 'method' => 'POST']);
	}

	public function testAdminCanDeleteSandboxSet(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->login('admin');
		$setId = $this->_createSet('Sandbox Set', null, 0);

		$this->testAction("/sets/delete/{$setId}", ['method' => 'POST']);

		$this->assertEmpty(ClassRegistry::init('Set')->findById($setId));
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

		$this->testAction("/sets/edit/{$setId}", ['method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertMatchesRegularExpression('#^sets/' . $setId . '/[0-9a-f]{16}\.webp$#', $set['Set']['image']);

		$path = WWW_ROOT . 'img' . DS . str_replace('/', DS, $set['Set']['image']);
		$this->assertFileExists($path);
		$this->assertSame('WEBP', substr((string) file_get_contents($path), 8, 4));

		@unlink($tmp);
		@unlink($path);
		@rmdir(WWW_ROOT . 'img' . DS . 'sets' . DS . $setId);
	}

	public function testDeleteSetRemovesImageFolder(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$setDir = WWW_ROOT . 'img' . DS . 'sets' . DS . $setId;
		mkdir($setDir, 0775, true);
		file_put_contents($setDir . DS . 'abc.webp', 'fake');

		$this->testAction("/sets/delete/{$setId}", ['method' => 'POST']);

		$this->assertDirectoryDoesNotExist($setDir);
	}

	public function testUploadSetImageReplacesPreviousImage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		$this->_uploadImage($setId, 400, 200);
		$first = ClassRegistry::init('Set')->findById($setId)['Set']['image'];
		$firstPath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $first);

		$this->_uploadImage($setId, 500, 300);
		$second = ClassRegistry::init('Set')->findById($setId)['Set']['image'];

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
		$setModel = ClassRegistry::init('Set');
		$setModel->id = $setId;
		$setModel->saveField('image', $shared);

		$this->_uploadImage($setId, 400, 200);

		$this->assertFileExists(WWW_ROOT . 'img' . DS . $shared);

		$uploaded = ClassRegistry::init('Set')->findById($setId)['Set']['image'];
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
		$imagePath = WWW_ROOT . 'img' . DS . str_replace('/', DS, ClassRegistry::init('Set')->findById($setId)['Set']['image']);
		$this->assertFileExists($imagePath);

		$this->testAction("/sets/edit/{$setId}", ['data' => ['Set' => ['remove_image' => '1']], 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertSame('', $set['Set']['image']);
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
		$this->testAction("/sets/edit/{$setId}", ['method' => 'POST']);
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
