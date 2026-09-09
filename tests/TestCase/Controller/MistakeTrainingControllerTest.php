<?php

App::uses('Constants', 'Utility');
App::uses('MistakeTraining', 'Utility');

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

	public function testRendersNextDueProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Renders the next due problem's play page directly (no redirect), and
		// puts the user into mistake-training mode.
		$this->assertStringContainsString('Mistake Training', $this->view);
		$this->assertTrue(Auth::isInMistakeTrainingMode());
	}

	public function testSkipsDeletedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
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
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				'deleted' => date('Y-m-d H:i:s'),
			],
		]);

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Soft-deleted tsumego should be skipped, and with nothing else due it
		// shows "All caught up".
		$this->assertStringContainsString('All caught up', $this->view);

		// And it should be removed from the pool so it drops out of the queue
		$this->assertNull(MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']));
	}
}
