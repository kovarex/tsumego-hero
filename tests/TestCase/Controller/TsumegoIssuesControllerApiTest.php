<?php

App::uses('BadRequestException', 'Routing/Error');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('NotFoundException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');
App::uses('TsumegoIssue', 'Model');

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

	public function testReopenOwnIssueSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => false],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'my issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
		]);

		$this->testAction('/tsumego-issues/reopen/' . $context->issues[0]['id'], ['method' => 'post']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$issue = ClassRegistry::init('TsumegoIssue')->findById($context->issues[0]['id']);
		$this->assertSame(TsumegoIssue::$OPENED_STATUS, (int) $issue['TsumegoIssue']['tsumego_issue_status_id']);
	}

	public function testReopenForbiddenForOtherUsersIssue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => false],
			'other-users' => [['name' => 'Ivan Detkov']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'someone elses issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
		]);

		// The issue belongs to the acting user by default; hand it to another user
		ClassRegistry::init('TsumegoIssue')->updateAll(
			['TsumegoIssue.user_id' => $context->otherUsers[0]['id']],
			['TsumegoIssue.id' => $context->issues[0]['id']]
		);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumego-issues/reopen/' . $context->issues[0]['id'], ['method' => 'post']);
	}

	public function testMoveCommentRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'admin' => false]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumego-issues/move-comment/1', ['method' => 'post']);
	}
}
