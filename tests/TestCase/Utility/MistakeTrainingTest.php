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

	public function testUpcomingByDayCountsWholeDaysAndLimitsDays(): void
	{
		$dueDays = [1, 1, 1, 2, 3, 3, 4, 5, 6, 7];
		$tsumegos = [];
		foreach ($dueDays as $index => $daysAhead)
		{
			$tsumegos[] = [
				'set_order' => $index + 1,
				'status' => [
					'name' => 'V',
					'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+' . $daysAhead . ' days')),
				],
			];
		}
		$context = new ContextPreparator(['tsumegos' => $tsumegos]);

		$upcoming = MistakeTraining::upcomingByDay($context->user['id'], 3);

		$this->assertCount(3, $upcoming['days'], 'Only the requested number of days is shown');
		$this->assertSame(3, $upcoming['days'][date('Y-m-d', strtotime('+1 day'))],
			'The count of a day covers every problem due that day, not a window of rows');
		$this->assertSame(2, $upcoming['days'][date('Y-m-d', strtotime('+3 days'))]);
		$this->assertSame(4, $upcoming['furtherDays'], 'The days beyond the shown ones are reported');
		$this->assertSame(date('Y-m-d', strtotime('+7 days')), $upcoming['lastDay']);
	}

	public function testDescribeDayNames(): void
	{
		$today = date('Y-m-d');

		$this->assertSame('today', MistakeTraining::describeDay($today, $today));
		$this->assertSame('tomorrow', MistakeTraining::describeDay(date('Y-m-d', strtotime('+1 day')), $today));
		$this->assertSame(date('l', strtotime('+3 day')), MistakeTraining::describeDay(date('Y-m-d', strtotime('+3 day')), $today));
		$this->assertSame(date('M j', strtotime('+30 days')), MistakeTraining::describeDay(date('Y-m-d', strtotime('+30 days')), $today));
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
