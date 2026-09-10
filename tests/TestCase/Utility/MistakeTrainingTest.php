<?php

App::uses('MistakeTraining', 'Utility');

class MistakeTrainingTest extends TestCaseWithAuth
{
	private function poolRow(int $userId, int $tsumegoId): ?array
	{
		return MistakeTraining::getPoolRow($userId, $tsumegoId);
	}

	public function testCleanSolveDoesNotEnterTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, true, null);

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testFailedFirstAttemptEntersTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertNotNull($row);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertNotNull($row['next_due']);
	}

	public function testSolvedProblemDoesNotEnterOnFail(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, 'S');

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testCleanSolveClimbsRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung']);
	}

	public function testFailDropsRungButNotBelowDaily(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, false, null);
		MistakeTraining::recordResult($userId, $tsumegoId, false, null);
		MistakeTraining::recordResult($userId, $tsumegoId, false, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertNotNull($row['next_due']);
	}

	public function testGraduationAtTopRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);
		MistakeTraining::recordResult($userId, $tsumegoId, true, null);

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testIsDueOnlyForPoolProblemsScheduledForNow(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))],
				],
			],
		]);
		$userId = $context->user['id'];

		$this->assertTrue(MistakeTraining::isDue($userId, (int) $context->tsumegos[0]['id']));
		$this->assertFalse(MistakeTraining::isDue($userId, (int) $context->tsumegos[1]['id']),
			'A problem scheduled for later is not due');
		$this->assertFalse(MistakeTraining::isDue($userId, 999999), 'A problem outside the pool is never due');
	}

	public function testDueCount(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, false, null);

		$this->assertSame(0, MistakeTraining::dueCount($userId));
	}
}
