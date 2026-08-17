<?php

App::uses('BadRequestException', 'Routing/Error');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('NotFoundException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');

/**
 * Controller-level tests for the TsumegoIssuesController JSON API.
 */
class TsumegoIssuesControllerApiTest extends ControllerTestCase
{
	public function testCreateRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tsumego-issues/create', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => 1, 'text' => 'broken move']),
		]);
	}

	public function testCreateRequiresTsumegoIdAndMessage()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->expectException(BadRequestException::class);

		$this->testAction('/tsumego-issues/create', [
			'method' => 'post',
			'data' => json_encode([]),
		]);
	}

	public function testCreateSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->testAction('/tsumego-issues/create', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'wrong answer']),
		]);

		$this->assertSame(200, $this->controller->response->statusCode());
		$body = json_decode($this->controller->response->body(), true);
		$this->assertTrue($body['success']);
		$this->assertNotEmpty($body['issue']['id']);
	}

	public function testCloseNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/tsumego-issues/close/999999', ['method' => 'post']);
	}

	public function testReopenRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumego-issues/reopen/1', ['method' => 'post']);
	}

	public function testMoveCommentRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumego-issues/move-comment/1', ['method' => 'post']);
	}
}
