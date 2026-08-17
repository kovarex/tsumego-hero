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
	public function testSandboxRequiresLogin(): void
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/sandbox', ['method' => 'get']);
	}

	public function testSandboxRequiresPremiumOrAdmin(): void
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'premium' => 0, 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/sandbox', ['method' => 'get']);
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

	public function testCreateRequiresLogin(): void
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/create', ['method' => 'post', 'data' => [
			'Set' => ['title' => 'Test'],
		]]);
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
		$this->assertNotEmpty($set['Set']['user_id']);
	}

	public function testCreateMakesPlaceholderTsumegoAndConnection(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$beforeTsumegoCount = ClassRegistry::init('Tsumego')->find('count');
		$beforeConnCount = ClassRegistry::init('SetConnection')->find('count');

		$this->testAction('/sets/create?sandbox=1', ['method' => 'post', 'data' => [
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

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);
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

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/delete', ['method' => 'post', 'data' => [
			'Set' => ['id' => $setId],
		]]);
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

	public function testAddTsumegoRequiresLogin(): void
	{
		$context = new ContextPreparator([
			'user' => null,
			'set' => ['title' => 'target', 'public' => 0]]);
		$setId = $context->set['id'];

		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->create();
		$tsumegoModel->save(['Tsumego' => ['difficulty' => 4, 'variance' => 100]]);
		$tsumegoId = $tsumegoModel->id;

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'tsumego_id' => $tsumegoId,
		]]);
	}

	public function testAddTsumegoBlocksRegularUserFromSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'set' => ['title' => 'sandbox set', 'public' => 0]]);
		$setId = $context->set['id'];

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);
	}

	public function testAddTsumegoBlocksPremiumFromSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'premium_user', 'premium' => 1, 'admin' => false],
			'set' => ['title' => 'sandbox set', 'public' => 0]]);
		$setId = $context->set['id'];

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);
	}

	public function testAddTsumegoBlocksNonAdminFromPublicSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'set' => ['title' => 'public set', 'public' => 1]]);
		$setId = $context->set['id'];

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/addTsumego/' . $setId, ['method' => 'post', 'data' => [
			'order' => 2,
		]]);
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

		$this->testAction('/sets/createAndAddTsumego/' . $setId, ['method' => 'post', 'data' => [
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
