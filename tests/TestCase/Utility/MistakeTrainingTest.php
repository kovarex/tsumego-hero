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
		$graduated = MistakeTraining::recordResult($userId, $tsumegoId, true, null);

		$this->assertTrue($graduated);
		$this->assertNull($this->poolRow($userId, $tsumegoId));
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
