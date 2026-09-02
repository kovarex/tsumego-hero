<?php

App::uses('ForbiddenException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');
App::uses('Constants', 'Utility');

/**
 * Server-side auth guards: anonymous -> 401, logged-in-without-permission -> 403.
 */
class AdminGuardTest extends ControllerTestCase
{
	// ── UsersController admin pages ──

	public function testAdminstatsRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/adminstats', ['method' => 'get']);
	}

	public function testAdminstatsRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/users/adminstats', ['method' => 'get']);
	}

	public function testUploadsRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/uploads', ['method' => 'get']);
	}

	public function testUploadsRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/users/uploads', ['method' => 'get']);
	}

	public function testUserstatsRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/users/userstats', ['method' => 'get']);
	}

	public function testUserstats3RequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/users/userstats3', ['method' => 'get']);
	}

	public function testAdminstatsAllowsAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$this->testAction('/users/adminstats', ['method' => 'get']);

		$this->assertSame(200, $this->controller->response->statusCode());
	}

	// ── SgfsController::view ──

	public function testSgfViewRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sgfs/view/1', ['method' => 'get']);
	}

	public function testSgfViewRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['set_order' => 1],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sgfs/view/' . $context->tsumegos[0]['id'], ['method' => 'get']);
	}

	// ── TagsController::delete ──

	public function testTagDeleteRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tags/delete/1', ['method' => 'post']);
	}

	public function testTagDeleteRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tags/delete/' . $context->tags[0]['id'], ['method' => 'post']);
	}

	// ── SetsController::sandbox ──

	public function testSandboxRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/sandbox', ['method' => 'get']);
	}

	public function testSandboxRequiresPremiumOrAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sets/sandbox', ['method' => 'get']);
	}

	// ── SetsController::view private sets ──

	public function testPrivateSetViewRequiresLogin()
	{
		new ContextPreparator([
			'user' => null,
			'tsumegos' => [['sets' => [['name' => 'private set', 'public' => 0, 'num' => '1']]]],
		]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sets/view/' . $this->privateSetId(), ['method' => 'get']);
	}

	private function privateSetId()
	{
		return ClassRegistry::init('Set')->find('first', ['conditions' => ['public' => 0]])['Set']['id'];
	}

	// ── TsumegosController::performMerge ──

	public function testPerformMergeRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['set_order' => 1],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumegos/performMerge', [
			'data' => [
				'master-tsumego-id' => $context->tsumegos[0]['id'],
				'slave-tsumego-id' => $context->tsumegos[0]['id'],
			],
			'method' => 'post',
		]);
	}

	// ── ScheduleController::index ──

	public function testScheduleIndexRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/schedule', ['method' => 'get']);
	}

	// ── TagsController::edit / editAction ──

	public function testTagEditRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tags/edit/' . $context->tags[0]['id'], ['method' => 'get']);
	}

	public function testTagEditActionRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tags/editAction/' . $context->tags[0]['id'], [
			'data' => ['tag_description' => 'test'],
			'method' => 'post',
		]);
	}

	// ── TagsController::add / addAction ──

	public function testTagAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tags/add', ['method' => 'get']);
	}

	public function testTagAddRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tags/add', ['method' => 'get']);
	}

	public function testTagAddActionRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tags/addAction', [
			'data' => ['tag_name' => 'foo', 'tag_description' => 'bar'],
			'method' => 'post',
		]);
	}

	public function testTagAddActionRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tags/addAction', [
			'data' => ['tag_name' => 'foo', 'tag_description' => 'bar'],
			'method' => 'post',
		]);
	}

	public function testTagAddActionSucceedsForContributor()
	{
		new ContextPreparator(['user' => ['name' => 'contributor', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->testAction('/tags/addAction', [
			'data' => ['tag_name' => 'contribution tag', 'tag_description' => 'desc'],
			'method' => 'post',
		]);

		$tag = ClassRegistry::init('Tag')->findByName('contribution tag');
		$this->assertNotEmpty($tag);
		$this->assertSame(0, (int) $tag['Tag']['approved']);
	}

	// ── SGF proposals require the contribution capability ──

	public function testSgfUploadRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sgf/upload/1', [
			'data' => ['sgfForBesogo' => '(;GM[1])'],
			'method' => 'post',
		]);
	}

	public function testSetupNewSgfStep2RequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumegos/setupNewSgfStep2', [
			'data' => ['setConnectionID' => 1],
			'method' => 'post',
		]);
	}
}
