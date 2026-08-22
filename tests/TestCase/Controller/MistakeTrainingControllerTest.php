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
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

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
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Should show "all caught up" since the tsumego was deleted
		$this->assertStringContainsString('All caught up', $this->view);
	}

	public function testSkipsSoftDeletedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				'deleted' => date('Y-m-d H:i:s'),
			],
		]);

		$this->testAction('/mistake-training');
		// Soft-deleted tsumego should be skipped (redirect to pick the next one)
		$redirectUrl = $this->headers['Location'] ?? ($this->controller->response->header()['Location'] ?? '');
		$this->assertStringContainsString('/mistake-training', $redirectUrl);

		// And its mt_due should be cleared so it drops out of the queue
		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertNull($status['TsumegoStatus']['mt_due']);
	}
}
