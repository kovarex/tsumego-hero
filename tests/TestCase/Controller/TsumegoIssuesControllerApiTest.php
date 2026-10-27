<?php

//Controller-level tests for the TsumegoIssuesController JSON API.
class TsumegoIssuesControllerApiTest extends IntegrationTestCase
{
	public function testCreateRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tsumego-issues/create', json_encode(['tsumego_id' => 1, 'text' => 'broken move']));

		$this->assertResponseCode(401);
	}

	public function testCreateRequiresTsumegoIdAndMessage()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->post('/tsumego-issues/create', json_encode([]));

		$this->assertResponseCode(400);
	}

	public function testCreateSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->post('/tsumego-issues/create', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'wrong answer']));

		$this->assertResponseCode(200);
		$body = json_decode($this->view, true);
		$this->assertTrue($body['success']);
		$this->assertNotEmpty($body['issue']['id']);
	}

	public function testCloseNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->post('/tsumego-issues/close/999999');

		$this->assertResponseCode(404);
	}

	public function testReopenOwnIssueSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => false],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'my issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
		]);

		$this->post('/tsumego-issues/reopen/' . $context->issues[0]['id']);

		$this->assertResponseCode(200);
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

		$this->post('/tsumego-issues/reopen/' . $context->issues[0]['id']);

		$this->assertResponseCode(403);
	}

	public function testMoveCommentRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'admin' => false]]);

		$this->post('/tsumego-issues/move-comment/1');

		$this->assertResponseCode(403);
	}
}
