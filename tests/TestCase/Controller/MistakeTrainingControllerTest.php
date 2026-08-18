<?php

App::uses('Constants', 'Utility');

class MistakeTrainingControllerTest extends TestCaseWithAuth
{
	public function testRequiresLogin()
	{
		new ContextPreparator(['user' => null]);
		$this->testAction('/mistake-training');
		$this->assertNotNull($this->headers['Location'] ?? null,
			'Should redirect to login');
	}

	public function testAllCaughtUpWhenNoDueProblems()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);
		$this->testAction('/mistake-training', ['return' => 'contents']);
		$this->assertStringContainsString('All caught up', $this->view);
	}

	public function testRedirectsToNextDueProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1],
		]);

		// Visit the tsumego to create a tsumego_status record
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$_COOKIE['previousTsumegoID'] = $context->tsumegos[0]['id'];
		$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Set mt_due in the past so it's due
		$dueDate = date('Y-m-d H:i:s', strtotime('-1 day'));
		$pdo = ClassRegistry::init('TsumegoStatus')->getDataSource()->getConnection();
		$stmt = $pdo->prepare("UPDATE tsumego_status SET mt_due = ? WHERE user_id = ? AND tsumego_id = ?");
		$stmt->execute([$dueDate, $context->user['id'], $context->tsumegos[0]['id']]);

		$this->testAction('/mistake-training');
		// Should redirect to the tsumego's set connection
		$redirectUrl = $this->headers['Location'] ?? ($this->controller->response->header()['Location'] ?? '');
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$this->assertStringContainsString('/' . $setConnectionId, $redirectUrl);
	}

	public function testSkipsDeletedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1],
		]);

		// Visit to create tsumego_status record
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$_COOKIE['previousTsumegoID'] = $context->tsumegos[0]['id'];
		$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Set mt_due but delete the tsumego
		$dueDate = date('Y-m-d H:i:s', strtotime('-1 day'));
		$pdo = ClassRegistry::init('TsumegoStatus')->getDataSource()->getConnection();
		$stmt = $pdo->prepare("UPDATE tsumego_status SET mt_due = ? WHERE user_id = ? AND tsumego_id = ?");
		$stmt->execute([$dueDate, $context->user['id'], $context->tsumegos[0]['id']]);
		ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Should show "all caught up" since the tsumego was deleted
		$this->assertStringContainsString('All caught up', $this->view);
	}
}
