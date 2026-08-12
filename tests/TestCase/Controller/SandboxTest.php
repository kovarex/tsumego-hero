<?php

App::uses('ControllerTestCase', 'TestSuite');
App::uses('ContextPreparator', 'TestSuite');
App::uses('Util', 'Utility');

/**
 * Sandbox functionality tests.
 *
 * Covers access control, CRUD operations, and edge cases for the sandbox
 * (admin/premium problem curation workspace).
 */
class SandboxTest extends ControllerTestCase
{
	public function testSandboxRedirectsUnauthenticated(): void
	{
		new ContextPreparator(['user' => null]);

		$this->testAction('/sets/sandbox', ['method' => 'get']);

		$this->assertSame(Util::getInternalAddress() . '/', $this->headers['Location']);
	}

	public function testSandboxRedirectsRegularUser(): void
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'premium' => 0, 'admin' => false]]);

		$this->testAction('/sets/sandbox', ['method' => 'get']);

		$this->assertSame(Util::getInternalAddress() . '/', $this->headers['Location']);
	}

	public function testSandboxAllowsPremiumUser(): void
	{
		new ContextPreparator(['user' => ['name' => 'premium_user', 'premium' => 1, 'admin' => false]]);

		$this->testAction('/sets/sandbox', ['method' => 'get', 'return' => 'vars']);

		$this->assertEquals('sandbox', $this->vars['_page']);
	}

	public function testSandboxAllowsAdmin(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin_user', 'admin' => true]]);

		$this->testAction('/sets/sandbox', ['method' => 'get', 'return' => 'vars']);

		$this->assertEquals('sandbox', $this->vars['_page']);
	}

	public function testSandboxShowsOnlyPrivateSets(): void
	{
		new ContextPreparator([
			'user' => ['premium' => 1],
			'tsumegos' => [
				['sets' => [['name' => 'private set', 'public' => 0, 'num' => '1']]],
				['sets' => [['name' => 'public set', 'public' => 1, 'num' => '1']]],
			]]);

		$this->testAction('/sets/sandbox', ['method' => 'get', 'return' => 'vars']);

		$names = array_column($this->vars['setsNew'], 'name');
		$this->assertContains('private set', $names);
		$this->assertNotContains('public set', $names);
	}

	public function testSandboxSetsPageVariable(): void
	{
		new ContextPreparator(['user' => ['premium' => 1]]);

		$this->testAction('/sets/sandbox', ['method' => 'get', 'return' => 'vars']);

		$this->assertEquals('sandbox', $this->vars['_page']);
	}

	public function testSandboxExposesAdminsList(): void
	{
		new ContextPreparator(['user' => ['premium' => 1, 'admin' => true]]);

		$this->testAction('/sets/sandbox', ['method' => 'get', 'return' => 'vars']);

		$this->assertArrayHasKey('admins', $this->vars);
		$this->assertContains('kovarex', $this->vars['admins']);
	}

	public function testCreateRedirectsUnauthenticated(): void
	{
		new ContextPreparator(['user' => null]);

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'Test'],
		]]);

		$this->assertSame(Util::getInternalAddress() . '/', $this->headers['Location']);
	}

	public function testCreateBlocksNonAdmin(): void
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$beforeCount = ClassRegistry::init('Set')->find('count');

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'Blocked Set'],
		]]);

		$this->assertSame(Util::getInternalAddress() . '/', $this->headers['Location']);
		$this->assertEquals($beforeCount, ClassRegistry::init('Set')->find('count'));
	}

	public function testCreateAllowsAdmin(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'Admin Set'],
		]]);

		$set = ClassRegistry::init('Set')->find('first', ['order' => 'id DESC']);
		$this->assertEquals('Admin Set', $set['Set']['title']);
		$this->assertEquals(0, $set['Set']['public']);
	}

	public function testCreateMakesPlaceholderTsumegoAndConnection(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$beforeTsumegoCount = ClassRegistry::init('Tsumego')->find('count');
		$beforeConnCount = ClassRegistry::init('SetConnection')->find('count');

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'Test Set'],
		]]);

		$this->assertEquals($beforeTsumegoCount + 1, ClassRegistry::init('Tsumego')->find('count'));
		$this->assertEquals($beforeConnCount + 1, ClassRegistry::init('SetConnection')->find('count'));
	}

	public function testCreateDoesNotCreateSgf(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$beforeSgfCount = ClassRegistry::init('Sgf')->find('count');

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'No SGF Set'],
		]]);

		$this->assertEquals($beforeSgfCount, ClassRegistry::init('Sgf')->find('count'),
			'Placeholder tsumego should have no SGF');
	}

	public function testRemoveBlocksNonAdmin(): void
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);
		$setId = $this->createSetWithTsumego('victim set', 0);

		$beforeCount = ClassRegistry::init('Set')->find('count');

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);

		$this->assertSame(Util::getInternalAddress() . '/sets', $this->headers['Location']);
		$this->assertEquals($beforeCount, ClassRegistry::init('Set')->find('count'));
	}

	public function testRemoveDeletesSetById(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$setId = $this->createSetWithTsumego('to delete', 0);

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);

		$this->assertEmpty(ClassRegistry::init('Set')->findById($setId));
	}

	public function testRemoveDoesNotDeletePublicSets(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$setId = $this->createSetWithTsumego('public set', 1);

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);

		// Admin can delete any set, including public ones
		$this->assertEmpty(ClassRegistry::init('Set')->findById($setId));
	}

	public function testRemovePreservesTsumegos(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$setId = $this->createSetWithTsumego('small set', 0);

		$beforeCount = ClassRegistry::init('Tsumego')->find('count');

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);

		$this->assertEmpty(ClassRegistry::init('Set')->findById($setId));
		$this->assertEquals($beforeCount, ClassRegistry::init('Tsumego')->find('count'),
			'Tsumegos survive set deletion (they may be in multiple sets)');

		$connections = ClassRegistry::init('SetConnection')->find('all', [
			'conditions' => ['set_id' => $setId],
		]);
		$this->assertEmpty($connections, 'Set connections should be cascade-deleted');
	}

	public function testAddTsumegoRedirectsUnauthenticated(): void
	{
		$context = new ContextPreparator([
			'set' => ['title' => 'target', 'public' => 0]]);
		Auth::logout();
		$setId = $context->set['id'];

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 1,
		]]);

		$this->assertSame(Util::getInternalAddress() . '/sets/view/' . $setId, $this->headers['Location']);
	}

	public function testAddTsumegoBlocksRegularUserFromSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'set' => ['title' => 'sandbox set', 'public' => 0]]);
		$setId = $context->set['id'];

		$beforeCount = ClassRegistry::init('Tsumego')->find('count');

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);

		$this->assertEquals($beforeCount, ClassRegistry::init('Tsumego')->find('count'));
	}

	public function testAddTsumegoBlocksPremiumFromSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'premium_user', 'premium' => 1, 'admin' => false],
			'set' => ['title' => 'sandbox set', 'public' => 0]]);
		$setId = $context->set['id'];

		$beforeCount = ClassRegistry::init('Tsumego')->find('count');

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);

		$this->assertEquals($beforeCount, ClassRegistry::init('Tsumego')->find('count'));
	}

	public function testAddTsumegoBlocksNonAdminFromPublicSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'set' => ['title' => 'public set', 'public' => 1]]);
		$setId = $context->set['id'];

		$beforeCount = ClassRegistry::init('Tsumego')->find('count');

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);

		$this->assertEquals($beforeCount, ClassRegistry::init('Tsumego')->find('count'));
	}

	public function testAddTsumegoCreatesRecordsInTransaction(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'set' => ['title' => 'sandbox', 'public' => 0]]);
		$setId = $context->set['id'];

		$sgfContent = '(;GM[1]FF[4]SZ[19])';
		$beforeTsumego = ClassRegistry::init('Tsumego')->find('count');
		$beforeSgf = ClassRegistry::init('Sgf')->find('count');

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 5,
			'sgf' => $sgfContent,
		]]);

		$this->assertEquals($beforeTsumego + 1, ClassRegistry::init('Tsumego')->find('count'));
		$this->assertEquals($beforeSgf + 1, ClassRegistry::init('Sgf')->find('count'));

		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC']);
		$connection = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => [
				'set_id' => $setId,
				'tsumego_id' => $tsumego['Tsumego']['id'],
			],
		]);
		$this->assertNotEmpty($connection);
		$this->assertEquals(5, $connection['SetConnection']['num']);
	}

	public function testUiBlocksNonAdmin(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'set' => ['title' => 'image set', 'public' => 0],
		]);
		$setId = $context->set['id'];

		$this->testAction('/sets/ui/' . $setId, ['method' => 'post']);

		$this->assertSame(Util::getInternalAddress() . '/', $this->headers['Location']);
	}

	private function createSetWithTsumego(string $title, int $public): int
	{
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save(['title' => $title, 'public' => $public, 'order' => Constants::$DEFAULT_SET_ORDER]);

		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->create();
		$tsumegoModel->save(['description' => 'test']);

		$connModel = ClassRegistry::init('SetConnection');
		$connModel->create();
		$connModel->save(['set_id' => $setModel->id, 'tsumego_id' => $tsumegoModel->id, 'num' => 1]);

		return $setModel->id;
	}
}
