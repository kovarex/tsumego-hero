<?php

use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;

class MistakeTrainingTest extends TestCaseWithAuth
{
	private function poolRow(int $userId, int $tsumegoId): ?array
	{
		return MistakeTraining::getPoolRow($userId, $tsumegoId);
	}

	/**
	 * A problem enters the training by being solved, so that is how these tests get
	 * a row at the bottom of the ladder.
	 */
	private function enterTraining(int $userId, int $tsumegoId): void
	{
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, null);
	}

	public function testCleanSolveDoesNotEnterTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$this->assertNull($this->poolRow($userId, $tsumegoId));
	}

	public function testAFailedFirstAttemptDoesNotEnterTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$this->assertNull($this->poolRow($userId, $tsumegoId),
			'A problem the player has not solved belongs to practice, not to the training');
	}

	public function testAProblemSolvedAfterAMistakeEntersTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertNotNull($row, 'A problem solved after a mistake is what the training reviews');
		$this->assertSame(0, (int) $row['rung']);
		$this->assertDaysAhead(1, $row, 'A new training problem is reviewed tomorrow');
	}

	public function testAProblemThatKeepsFailingNeverEntersTraining(): void
	{
		foreach (['N', 'V', 'F'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$userId = $context->user['id'];
			$tsumegoId = $context->tsumegos[0]['id'];

			for ($attempt = 0; $attempt < 3; $attempt++)
				MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, $status);

			$this->assertNull($this->poolRow($userId, $tsumegoId),
				"Failing a problem in status $status again and again is not a training entry");
		}
	}

	public function testAFailOnASolvedProblemDoesNotEnter(): void
	{
		foreach (['S', 'W', 'C'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$userId = $context->user['id'];
			$tsumegoId = $context->tsumegos[0]['id'];

			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, $status);

			$this->assertNull($this->poolRow($userId, $tsumegoId),
				"A problem in status $status is handled by the review flow, not by the training");
		}
	}

	public function testForgottenProblemEntersOnFail(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'X');

		$this->assertNotNull($this->poolRow($userId, $tsumegoId),
			'A failed review is a sign of forgetting, so the problem comes back to the training');
	}

	public function testEntryWaitsForTheStartOfTheNextDay(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);

		$boundaryHour = str_pad((string) MistakeTraining::$DAY_START_HOUR, 2, '0', STR_PAD_LEFT) . ':00:00';
		$expected = date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')) . ' ' . $boundaryHour;
		$this->assertSame($expected, $this->poolRow($userId, $tsumegoId)['next_due'],
			'A review is a day, so it is due from the start of that day');
	}

	public function testCleanSolveClimbsRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung']);
	}

	public function testSolveWithMistakeKeepsTheRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(2, (int) $row['rung'], 'A solve that needed a retry does not count as knowing it');
		$this->assertDaysAhead(1, $row, 'A solve that needed a retry comes back tomorrow');
	}

	public function testAMistakeReturnsTheProblemToTheBottom(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		$this->assertSame(2, (int) $this->poolRow($userId, $tsumegoId)['rung']);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'A mistake takes back everything the problem had climbed');
		$this->assertDaysAhead(1, $row, 'A problem that was missed is reviewed tomorrow');
	}

	public function testFailingAtTheBottomComesBackTomorrow(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);

		for ($attempt = 0; $attempt < 3; $attempt++)
		{
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);
			$row = $this->poolRow($userId, $tsumegoId);
			$this->assertSame(0, (int) $row['rung']);
			$this->assertDaysAhead(1, $row, 'A problem that keeps failing is offered again tomorrow');
		}
	}

	public function testEveryRungWaitsItsInterval(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
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

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 5; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		$this->assertSame(5, (int) $this->poolRow($userId, $tsumegoId)['rung']);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'A mistake at the top rung takes the problem back to the bottom');
		$this->assertDaysAhead(1, $row, 'A mistake at the top rung comes back tomorrow, not after two months');
	}

	private function assertDaysAhead(int $expectedDays, array $row, string $message): void
	{
		$expectedDay = date('Y-m-d', strtotime(MistakeTraining::today() . ' +' . $expectedDays . ' days'));
		$this->assertSame($expectedDay, date('Y-m-d', strtotime($row['next_due'])), $message);
	}

	public function testGraduationAtTopRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertNotNull($row, 'The row stays, as the memory that the training knows the problem');
		$this->assertNull($row['next_due'], 'A graduated problem has no next review');
		$this->assertSame(0, MistakeTraining::totalInTraining($userId));
	}

	public function testAGraduatedProblemIsNotOfferedForImport(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]]]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 6; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$this->assertSame(0, MistakeTraining::importCandidates($userId),
			'A problem the training mastered is not a past mistake to import again');
	}

	public function testAGraduatedProblemIsNotDue(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 6; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId),
			'A graduated problem has no review to be due for');
	}

	public function testAFailedGraduatedProblemComesBackAtTheBottom(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 6; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'Forgetting a mastered problem starts the ladder over');
		$this->assertDaysAhead(1, $row, 'And it is reviewed tomorrow');
		$this->assertSame(1, MistakeTraining::totalInTraining($userId));
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

		$this->enterTraining($userId, $tsumegoId);

		$this->assertSame(0, MistakeTraining::dueCount($userId));
	}

	public function testImportCandidatesAreTheProblemsSolvedWithAMistake(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
			'tsumegos' => [
				['set_order' => 2, 'attempt' => ['solved' => true, 'misplays' => 0]],
				['set_order' => 3, 'attempt' => ['solved' => false, 'misplays' => 2]],
			],
		]);

		$this->assertSame(1, MistakeTraining::importCandidates($context->user['id']),
			'A clean solve and a problem that was never solved are not candidates');
	}

	public function testImportedProblemsAreDueAtOnce(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 3]]]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->assertSame(1, MistakeTraining::importPastMistakes($userId));

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId),
			'An imported problem is old, so it is due as soon as it arrives');
	}

	public function testAnImportedProblemIsNotOfferedAgain(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]]]);
		$userId = $context->user['id'];

		$this->assertSame(1, MistakeTraining::importCandidates($userId));

		MistakeTraining::importPastMistakes($userId);

		$this->assertSame(0, MistakeTraining::importCandidates($userId));
		$this->assertSame(0, MistakeTraining::importPastMistakes($userId), 'Running the import again adds nothing');
	}

	public function testImportTakesTheNewestMistakesFirst(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'attempt' => ['solved' => true, 'misplays' => 1, 'created' => date('Y-m-d H:i:s', strtotime('-1 year'))],
			],
			'tsumegos' => [
				['set_order' => 2, 'attempt' => ['solved' => true, 'misplays' => 1, 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]],
			],
		]);
		$userId = $context->user['id'];

		MistakeTraining::importPastMistakes($userId, 1);

		$this->assertNotNull($this->poolRow($userId, (int) $context->tsumegos[1]['id']),
			'The newer mistake is imported first');
		$this->assertNull($this->poolRow($userId, (int) $context->tsumegos[0]['id']));
	}

	public function testImportLeavesAProblemAlreadyInTheTrainingAlone(): void
	{
		$due = date('Y-m-d H:i:s', strtotime('+3 days'));
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'attempt' => ['solved' => true, 'misplays' => 1],
				'status' => ['name' => 'V', 'mistake_training_due' => $due],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->assertSame(0, MistakeTraining::importPastMistakes($userId));
		$this->assertSame($due, $this->poolRow($userId, $tsumegoId)['next_due'],
			'An import never moves a review that is already scheduled');
	}
}
