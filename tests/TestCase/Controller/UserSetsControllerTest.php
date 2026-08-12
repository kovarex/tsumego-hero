<?php

App::uses('TestCaseWithAuth', 'TestSuite');
App::uses('ContextPreparator', 'TestSuite');

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

		$data = ['Set' => ['title' => 'My New Set']];
		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'My New Set'],
		]);
		$this->assertNotEmpty($set);
		$this->assertEquals($context->user['id'], $set['Set']['user_id']);
		$this->assertEquals(0, $set['Set']['public']);
	}

	public function testCreateSandboxSet(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->login('admin');

		$data = ['Set' => ['title' => 'New Sandbox', 'sandbox' => '1']];
		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);

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
		$this->testAction('/sets/create', ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'Guest Set'],
		]);
		$this->assertEmpty($set);
	}

	// ── /sets/edit/:id ──────────────────────────────────────────────────

	public function testEditOwnSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$setId = $this->_createSet('My Set', $context->user['id'], 0);
		$this->login('alice');

		// Metadata editing is handled by the view page (view.ctp admin panel)
		$data = ['Set' => ['title' => 'Renamed', 'description' => 'New desc']];
		$this->testAction("/sets/view/{$setId}", ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertEquals('Renamed', $set['Set']['title']);
		$this->assertEquals('New desc', $set['Set']['description']);
	}

	public function testEditOtherUserSetFails(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$setId = $this->_createSet('Alice Set', $context->user['id'], 0);
		$this->login('bob');

		// Non-owner gets 404 when trying to view a private set
		try
		{
			$this->testAction("/sets/view/{$setId}", ['data' => ['Set' => ['title' => 'Hacked']], 'method' => 'POST']);
			$this->fail('Expected NotFoundException');
		}
		catch (NotFoundException $e)
		{
			$this->assertStringContainsString('Set not found', $e->getMessage());
		}

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertEquals('Alice Set', $set['Set']['title']);
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
		$this->testAction("/sets/view/{$setId}", ['data' => $data, 'method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertEquals('Fixed by Admin', $set['Set']['title']);
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
		catch (NotFoundException $e)
		{
			// Expected: non-owner gets 404 for private set
			$this->assertStringContainsString('Set not found', $e->getMessage());
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

		$this->testAction("/sets/delete/{$setId}", ['method' => 'POST']);

		$set = ClassRegistry::init('Set')->findById($setId);
		$this->assertNotEmpty($set);
		$this->assertEquals(0, $set['Set']['public']);
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
		$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setId, 'tsumego_id' => 1],
		]);
		$this->assertEmpty($sc);
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

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $set['Set']['id'], 'tsumego_id' => $tsumegoId],
		]);
		$this->assertNotEmpty($sc);
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
		$this->testAction("/sets/addTsumego/{$setId}", ['data' => $data, 'method' => 'POST']);

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
}
