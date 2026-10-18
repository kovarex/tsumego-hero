<?php

use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;

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

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testFailedFirstAttemptEntersTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

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

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'S');

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testCleanSolveClimbsRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung']);
	}

	public function testSolveWithMistakeKeepsTheRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(2, (int) $row['rung'], 'A solve that needed a retry does not count as knowing it');
		$this->assertDaysAhead(1, $row, 'A solve that needed a retry comes back tomorrow');
	}

	public function testLapsesGiveBackARung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung'], 'A lapse gives back the rung the solve after it earned');
		$this->assertDaysAhead(1, $row, 'A lapsed problem is reviewed tomorrow');

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'Lapses do not go below the bottom rung');
		$this->assertDaysAhead(1, $row, 'A problem that still has a rung to give back is reviewed tomorrow');
	}

	public function testFailingAtTheBottomCoolsOff(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$this->assertDaysAhead(1, $this->poolRow($userId, $tsumegoId), 'Entering training comes back tomorrow');

		// Nothing left to give back, so the problem stops owning a daily slot: a
		// problem the player cannot solve yet is offered roughly twice a week.
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertDaysAhead(3, $row, 'Failing at the bottom waits three days');

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$this->assertDaysAhead(3, $this->poolRow($userId, $tsumegoId), 'It keeps the cool-off while it keeps failing');
	}

	public function testEveryRungWaitsItsInterval(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		$this->assertDaysAhead(1, $this->poolRow($userId, $tsumegoId), 'Entering training waits one day');

		foreach ([1 => 3, 2 => 7, 3 => 14, 4 => 30, 5 => 60] as $rung => $days)
		{
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
			$row = $this->poolRow($userId, $tsumegoId);
			$this->assertSame($rung, (int) $row['rung']);
			$this->assertDaysAhead($days, $row, "A clean solve at rung $rung waits $days days");
		}
	}

	public function testMistakeAtTheTopRungComesBackTomorrow(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		for ($i = 0; $i < 5; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		$this->assertSame(5, (int) $this->poolRow($userId, $tsumegoId)['rung']);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(4, (int) $row['rung'], 'A mistake at the top rung gives one back');
		$this->assertDaysAhead(1, $row, 'A mistake at the top rung comes back tomorrow, not after two months');
	}

	private function assertDaysAhead(int $expectedDays, array $row, string $message): void
	{
		$daysAhead = (int) round((strtotime($row['next_due']) - time()) / 86400);
		$this->assertSame($expectedDays, $daysAhead, $message);
	}

	public function testGraduationAtTopRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

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

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$this->assertSame(0, MistakeTraining::dueCount($userId));
	}
}
