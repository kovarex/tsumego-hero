<?php

use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Util;

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

	/**
	 * The review day of a problem comes and it is solved cleanly, which is how it
	 * climbs: a clean solve before that day does not count.
	 */
	private function reviewCleanly(int $userId, int $tsumegoId): void
	{
		Util::execute(
			'UPDATE mistake_training_pool SET next_due = ?, last_review_day = NULL, last_review_result = NULL WHERE user_id = ? AND tsumego_id = ?',
			[date('Y-m-d H:i:s', strtotime('-1 minute')), $userId, $tsumegoId]
		);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null);
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

	public function testAFailOnAProblemWaitingForReviewOrForgottenEntersTraining(): void
	{
		foreach (['W', 'X'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$userId = $context->user['id'];
			$tsumegoId = $context->tsumegos[0]['id'];

			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, $status);

			$row = $this->poolRow($userId, $tsumegoId);
			$this->assertNotNull($row, "A problem in status $status was solved before, so a mistake on it is one to review");
			$this->assertSame(0, (int) $row['rung']);
			$this->assertDaysAhead(1, $row, 'It is reviewed tomorrow like any new training problem');
		}
	}

	/**
	 * A solved or mastered problem is the player's to play with freely in the level
	 * and rating modes.
	 */
	public function testASolvedProblemIsFreePlay(): void
	{
		foreach (['S', 'C'] as $status)
			foreach ([PlayOutcome::Failed, PlayOutcome::SolvedAfterMistake] as $outcome)
			{
				$context = new ContextPreparator(['tsumego' => 1]);
				$userId = $context->user['id'];
				$tsumegoId = $context->tsumegos[0]['id'];

				MistakeTraining::recordResult($userId, $tsumegoId, $outcome, $status);

				$this->assertNull($this->poolRow($userId, $tsumegoId),
					"A mistake on a problem in status $status is free play, not a training entry");
			}
	}

	public function testFreePlayLeavesATrainingProblemThatIsNotDueAlone(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];
		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$before = $this->poolRow($userId, $tsumegoId);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'S');
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, 'C');

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung'], 'A mistake in free play does not take the problem down');
		$this->assertSame(0, (int) $row['lapses']);
		$this->assertSame($before['next_due'], $row['next_due']);
	}

	public function testFreePlayOnADueProblemIsItsReview(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];
		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		MistakeTraining::makeDueNow($userId, $tsumegoId);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'S');

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'The review of a due problem counts wherever it is played');
		$this->assertSame(MistakeTraining::REVIEW_MISSED, $row['last_review_result']);
	}

	public function testAMistakeOnASolvedProblemInTheTimeModeEntersTraining(): void
	{
		foreach (['S', 'C', 'W', 'X'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$userId = $context->user['id'];
			$tsumegoId = $context->tsumegos[0]['id'];

			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, $status, false, true);

			$this->assertNotNull($this->poolRow($userId, $tsumegoId),
				"The time mode plays a problem in status $status against the clock, so a mistake there counts");
		}

		$context = new ContextPreparator(['tsumego' => 1]);
		MistakeTraining::recordResult($context->user['id'], $context->tsumegos[0]['id'], PlayOutcome::Failed, 'V', false, true);
		$this->assertNull($this->poolRow($context->user['id'], $context->tsumegos[0]['id']),
			'A problem never solved stays practice in the time mode too');
	}

	public function testATimeoutIsNotAMistake(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::TimedOut, 'S', false, true);
		$this->assertNull($this->poolRow($userId, $tsumegoId), 'Running out of time does not bring a solved problem in');

		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$before = $this->poolRow($userId, $tsumegoId);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::TimedOut, 'S', false, true);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung'], 'Running out of time says nothing about whether the problem is known');
		$this->assertSame(0, (int) $row['lapses']);
		$this->assertSame($before['next_due'], $row['next_due']);
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

		$expected = date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')) . ' 00:00:00';
		$this->assertSame($expected, $this->poolRow($userId, $tsumegoId)['next_due'],
			'A review is a day, so it is due from the start of that day');
	}

	public function testCleanSolveClimbsRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung']);
	}

	/**
	 * Playing a problem again before its review, in the level mode, is won by the
	 * short-term memory: Reset and solve again would otherwise graduate it in a minute.
	 */
	public function testACleanSolveBeforeTheReviewDayDoesNotClimb(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		$before = $this->poolRow($userId, $tsumegoId);
		for ($i = 0; $i < 8; $i++)
			MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, 'W');

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'Clean solves before the review day do not climb');
		$this->assertSame($before['next_due'], $row['next_due'], 'And they do not move the review');
	}

	public function testSolveWithMistakeKeepsTheRung(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
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
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
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

		foreach ([1 => 2, 2 => 4, 3 => 8, 4 => 15, 5 => 30, 6 => 60, 7 => 120] as $rung => $days)
		{
			$this->reviewCleanly($userId, $tsumegoId);
			$row = $this->poolRow($userId, $tsumegoId);
			$this->assertSame($rung, (int) $row['rung']);
			$this->assertDaysAhead($days, $row, "A clean solve at rung $rung waits $days days");
			$this->assertSame(0, (int) $row['lapses'], 'Clean solves are no lapses, so the waits are the ladder');
		}
	}

	public function testMistakeAtTheTopRungComesBackTomorrow(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 7; $i++)
			$this->reviewCleanly($userId, $tsumegoId);
		$this->assertSame(7, (int) $this->poolRow($userId, $tsumegoId)['rung']);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null);

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'A mistake at the top rung takes the problem back to the bottom');
		$this->assertDaysAhead(1, $row, 'A mistake at the top rung comes back tomorrow, not after four months');
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
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);

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
		for ($i = 0; $i < 8; $i++)
			$this->reviewCleanly($userId, $tsumegoId);

		$this->assertSame(0, MistakeTraining::importCandidates($userId),
			'A problem the training mastered is not a past mistake to import again');
	}

	public function testAGraduatedProblemIsNotDue(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 8; $i++)
			$this->reviewCleanly($userId, $tsumegoId);

		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId),
			'A graduated problem has no review to be due for');
	}

	public function testAFailedGraduatedProblemComesBackAtTheBottom(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->enterTraining($userId, $tsumegoId);
		for ($i = 0; $i < 8; $i++)
			$this->reviewCleanly($userId, $tsumegoId);

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

	// ==================== Today's training ====================

	/**
	 * Problems in the training, in the order they came in. Each entry is added to the
	 * status fixture of its problem; by default a problem is due, every one at the same
	 * moment the way an import brings them, on the bottom rung and untried today.
	 */
	private function prepareTraining(array ...$problems): ContextPreparator
	{
		$due = date('Y-m-d H:i:s', strtotime('-1 hour'));
		$tsumegos = [];
		foreach ($problems as $index => $problem)
			$tsumegos[] = ['set_order' => $index + 1, 'status' => $problem + ['name' => 'V', 'mistake_training_due' => $due]];
		return new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);
	}

	/**
	 * A problem solved in the training today, waiting for its next review.
	 */
	private static function solvedToday(int $rung = 1): array
	{
		return [
			'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days')),
			'mistake_training_rung' => $rung,
			'mistake_training_review_day' => MistakeTraining::today(),
			'mistake_training_review_result' => MistakeTraining::REVIEW_SOLVED,
		];
	}

	private function tsumegoId(ContextPreparator $context, int $index): int
	{
		return (int) $context->tsumegos[$index]['id'];
	}

	private function setConnectionId(ContextPreparator $context, int $index): int
	{
		return (int) $context->tsumegos[$index]['set-connections'][0]['id'];
	}

	/**
	 * A result of a play in the training, the way the play page reports it.
	 */
	private function playInTraining(ContextPreparator $context, int $index, PlayOutcome $outcome): void
	{
		MistakeTraining::recordResult($context->user['id'], $this->tsumegoId($context, $index), $outcome, null, true);
	}

	/**
	 * The training row the play page shows with the problem at $currentIndex open,
	 * keyed by tsumego.
	 *
	 * @return array<int, \App\Utility\TsumegoButton>
	 */
	private function trainingButtons(ContextPreparator $context, int $currentIndex = 0): array
	{
		$buttons = MistakeTraining::buildQueueButtons($this->setConnectionId($context, $currentIndex));
		return array_column((array) $buttons, null, 'tsumegoID');
	}

	private function trainingRow(ContextPreparator $context, int $index): array
	{
		return $this->poolRow($context->user['id'], $this->tsumegoId($context, $index));
	}

	/**
	 * An import brings many problems due at the same moment, newest mistake first, so
	 * neither the due date nor the order of the problems in their set can order the
	 * day: problems that tie on the date would come out in a different order on every
	 * query, and the training would open at 15/20. The problems come in here in an
	 * order that is neither theirs nor its reverse.
	 */
	public function testTodaysTrainingKeepsTheOrderTheProblemsCameIn(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [['set_order' => 1], ['set_order' => 2], ['set_order' => 3], ['set_order' => 4]],
		]);
		$userId = $context->user['id'];
		$due = date('Y-m-d H:i:s', strtotime('-1 hour'));
		foreach ([2, 0, 3] as $index)
			MistakeTraining::addProblem($userId, $this->tsumegoId($context, $index), $due);
		$order = fn(int ...$indexes) => array_map(fn($index) => $this->tsumegoId($context, $index), $indexes);
		$buttonOrder = fn($buttons) => array_map(fn($button) => $button->tsumegoID, (array) $buttons);

		$this->assertSame($this->tsumegoId($context, 2), (int) MistakeTraining::nextDue($userId)['tsumego_id'],
			'The training starts with the first problem that came in');
		$buttons = MistakeTraining::buildQueueButtons($this->setConnectionId($context, 2));
		$this->assertSame($order(2, 0, 3), $buttonOrder($buttons));
		$this->assertSame(1, $buttons->currentOrder, 'The problem the training starts with is 1/3');
		$this->assertSame(3, $buttons->highestTsumegoOrder);

		MistakeTraining::addProblem($userId, $this->tsumegoId($context, 1), $due);

		$buttons = MistakeTraining::buildQueueButtons($this->setConnectionId($context, 0));
		$this->assertSame($order(2, 0, 3, 1), $buttonOrder($buttons), 'A problem that comes in during the day goes to the end');
		$this->assertSame(2, $buttons->currentOrder, 'And the others keep their numbers');
	}

	public function testTodaysTrainingIsNotOrderedByTheDueDate(): void
	{
		$context = $this->prepareTraining([], ['mistake_training_due' => date('Y-m-d H:i:s', strtotime('-2 days'))]);

		$buttons = MistakeTraining::buildQueueButtons($this->setConnectionId($context, 0));

		$this->assertSame($this->tsumegoId($context, 0), $buttons[0]->tsumegoID,
			'A review moving its date must not reshuffle the row');
		$this->assertSame($this->tsumegoId($context, 0), (int) MistakeTraining::nextDue($context->user['id'])['tsumego_id']);
	}

	public function testAMistakeKeepsTheProblemDueToday(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 2], []);

		$this->playInTraining($context, 0, PlayOutcome::Failed);

		$row = $this->trainingRow($context, 0);
		$this->assertSame(0, (int) $row['rung'], 'A mistake still takes back everything the problem had climbed');
		$this->assertTrue(MistakeTraining::isRowDue($row), 'A mistake must not make the problem disappear from today');
		$this->assertSame(MistakeTraining::today(), $row['last_review_day']);
		$this->assertSame(MistakeTraining::REVIEW_MISSED, $row['last_review_result']);

		$button = $this->trainingButtons($context)[$this->tsumegoId($context, 0)];
		$this->assertSame('N', $button->status, 'A problem missed today is grey until it is solved');
		$this->assertSame(0, $button->rung);
		$this->assertSame('Missed today, try again', $button->label);
		$this->assertSame('Rung 1 of 8. Solve it today and it comes back tomorrow.', $button->description,
			'The tooltip of a miss must not read as if it was solved');
		$this->assertStringContainsString('problem-nav__rung--missed', $button->renderRungBadge(),
			'A red ring tells it from a problem not tried yet');
		$this->assertStringNotContainsString('problem-nav__rung--missed',
			$this->trainingButtons($context)[$this->tsumegoId($context, 1)]->renderRungBadge());
	}

	public function testASecondMistakeTheSameDayKeepsItDue(): void
	{
		$context = $this->prepareTraining([]);

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->playInTraining($context, 0, PlayOutcome::Failed);

		$row = $this->trainingRow($context, 0);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertTrue(MistakeTraining::isRowDue($row), 'Every try that fails keeps the problem in today\'s training');
		$this->assertSame(MistakeTraining::REVIEW_MISSED, $row['last_review_result']);
	}

	public function testASolveAfterAMistakeTheSameDayEarnsNothing(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 2]);

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$row = $this->trainingRow($context, 0);
		$this->assertSame(0, (int) $row['rung'], 'A clean try after a mistake of the same day is a retry, it climbs nothing');
		$this->assertDaysAhead(1, $row, 'A problem solved on a retry comes back tomorrow');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_review_result']);

		$button = $this->trainingButtons($context)[$this->tsumegoId($context, 0)];
		$this->assertSame('S', $button->status, 'Solved today is green');
		$this->assertSame('Solved today', $button->label);
	}

	public function testAMistakeAtTheTopRungThenASolveTheSameDayComesBackTomorrow(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 7]);

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$row = $this->trainingRow($context, 0);
		$this->assertSame(0, (int) $row['rung'], 'The retry does not graduate what the mistake took to the bottom');
		$this->assertDaysAhead(1, $row, 'And it is reviewed tomorrow');
		$this->assertSame(1, MistakeTraining::totalInTraining($context->user['id']));
	}

	public function testACleanReviewClimbsAndIsDoneForTheDay(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 1]);

		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$row = $this->trainingRow($context, 0);
		$this->assertSame(2, (int) $row['rung']);
		$this->assertDaysAhead(4, $row, 'A clean solve at rung 2 waits 4 days');
		$this->assertSame(MistakeTraining::today(), $row['last_review_day']);
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_review_result']);
		$this->assertCount(1, MistakeTraining::todaysTraining($context->user['id']),
			'A problem solved today stays in today\'s training, so the row holds still');
		$this->assertNull(MistakeTraining::nextDue($context->user['id']), 'But it is not served again');
	}

	public function testUntriedProblemsAreServedBeforeMissedOnes(): void
	{
		$context = $this->prepareTraining([], [], []);
		$userId = $context->user['id'];

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->assertSame($this->tsumegoId($context, 1), (int) MistakeTraining::nextDue($userId)['tsumego_id'],
			'A problem missed today waits until the untried ones are done');

		$this->playInTraining($context, 1, PlayOutcome::Failed);
		$this->playInTraining($context, 2, PlayOutcome::Failed);
		$this->assertSame($this->tsumegoId($context, 0), (int) MistakeTraining::nextDue($userId)['tsumego_id'],
			'With only misses left, the first of them comes back');
	}

	public function testTheRowShowsWhatTodayDidAndNotTheStatus(): void
	{
		$context = $this->prepareTraining(
			['name' => 'S'],
			['name' => 'F'],
			['name' => 'C'],
			['name' => 'F'] + self::solvedToday(),
		);

		$buttons = $this->trainingButtons($context);

		foreach ([0, 1, 2] as $index)
			$this->assertSame('N', $buttons[$this->tsumegoId($context, $index)]->status,
				'A problem not solved today is grey whatever its status is');
		$this->assertSame('S', $buttons[$this->tsumegoId($context, 3)]->status,
			'A problem solved today is green whatever its status is');
		$this->assertSame('Due today', $buttons[$this->tsumegoId($context, 0)]->label,
			'The tooltip says what the colour means, not the name of the status');
	}

	public function testAReplayOfAProblemSolvedTodayCountsForNothing(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 1]);
		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);
		$solved = $this->trainingRow($context, 0);

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$row = $this->trainingRow($context, 0);
		$this->assertSame((int) $solved['rung'], (int) $row['rung'], 'A replay neither climbs nor falls');
		$this->assertSame($solved['next_due'], $row['next_due'], 'A replay does not move the review');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_review_result'], 'A replay leaves the day solved');
	}

	public function testNextSkipsWhatTodaySolvedAndWrapsAround(): void
	{
		$context = $this->prepareTraining([], self::solvedToday(), [], []);
		$link = fn(int $currentIndex) => MistakeTraining::nextLink(
			MistakeTraining::buildQueueButtons($this->setConnectionId($context, $currentIndex)),
			$this->setConnectionId($context, $currentIndex));

		$this->assertSame(MistakeTraining::$PLAY_LINK_PREFIX . $this->setConnectionId($context, 2), $link(0),
			'Next skips a problem solved today');
		$this->assertSame(MistakeTraining::$PLAY_LINK_PREFIX . $this->setConnectionId($context, 3), $link(2));
		$this->assertSame(MistakeTraining::$PLAY_LINK_PREFIX . $this->setConnectionId($context, 0), $link(3),
			'After the last problem, Next goes around to the first one still due');
	}

	public function testNextHasNowhereToGoWhileTheLastProblemIsNotSolved(): void
	{
		$context = $this->prepareTraining([], self::solvedToday());
		$scId = $this->setConnectionId($context, 0);

		$this->assertSame('', MistakeTraining::nextLink(MistakeTraining::buildQueueButtons($scId), $scId),
			'The current problem is never its own next one, and the queue page would serve it again');
	}

	public function testNextEndsTheDayOnceEverythingIsSolved(): void
	{
		$context = $this->prepareTraining(self::solvedToday(), self::solvedToday());
		$scId = $this->setConnectionId($context, 1);

		$this->assertSame(MistakeTraining::$QUEUE_LINK, MistakeTraining::nextLink(MistakeTraining::buildQueueButtons($scId), $scId),
			'With the day done, Next leads to the page that says so');
	}

	public function testAProblemGraduatedTodayStaysInTheRowAsSolved(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 7]);

		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$row = $this->trainingRow($context, 0);
		$this->assertNull($row['next_due'], 'A clean solve at the top rung graduates');
		$this->assertSame(MistakeTraining::REVIEW_GRADUATED, $row['last_review_result']);
		$this->assertSame(0, MistakeTraining::totalInTraining($context->user['id']));

		$button = $this->trainingButtons($context)[$this->tsumegoId($context, 0)];
		$this->assertSame('S', $button->status, 'A graduation is today\'s solve, so it is green');
		$this->assertTrue($button->graduated);
		$this->assertSame('Graduated today', $button->label);
	}

	public function testANewDayKeepsOnlyTheMissesThatWereNotSolved(): void
	{
		$context = $this->prepareTraining([], []);
		$userId = $context->user['id'];
		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);
		$this->playInTraining($context, 1, PlayOutcome::Failed);

		// The toolbox shift moves the reviews and their day together, which is the day
		// boundary passing.
		MistakeTraining::shiftReviews($userId, -1);

		$training = MistakeTraining::todaysTraining($userId);
		$this->assertSame([$this->tsumegoId($context, 1)], array_map(fn($row) => (int) $row['tsumego_id'], $training),
			'Yesterday\'s solves leave the list, a miss that was never solved is still due');

		$button = $this->trainingButtons($context, 1)[$this->tsumegoId($context, 1)];
		$this->assertSame('N', $button->status);
		$this->assertSame('Due today', $button->label, 'Yesterday\'s miss is a review like any other today');

		$this->playInTraining($context, 1, PlayOutcome::SolvedClean);
		$this->assertSame(1, (int) $this->trainingRow($context, 1)['rung'],
			'A clean solve on a new day climbs, the retry was yesterday');
	}

	// ==================== Lapses ====================

	private function lapses(ContextPreparator $context, int $index): int
	{
		return (int) $this->trainingRow($context, $index)['lapses'];
	}

	public function testEnteringTheTrainingIsNotALapse(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => [['set_order' => 1], ['set_order' => 2, 'attempt' => ['solved' => true, 'misplays' => 1]]],
		]);
		$userId = $context->user['id'];

		$this->enterTraining($userId, $this->tsumegoId($context, 0));
		MistakeTraining::importPastMistakes($userId);

		$this->assertSame(0, $this->lapses($context, 0), 'The mistake that brings a problem in is what the training is for');
		$this->assertSame(0, $this->lapses($context, 1), 'An imported past mistake comes in without lapses too');
	}

	public function testTheFirstMissOfADueProblemIsALapse(): void
	{
		$context = $this->prepareTraining([]);

		$this->playInTraining($context, 0, PlayOutcome::Failed);

		$this->assertSame(1, $this->lapses($context, 0));
	}

	/**
	 * A missed problem stays due until it is solved, and every try before that fails
	 * on what the first miss showed already, so the day counts one lapse, also when
	 * the problem is tried again outside the training.
	 */
	public function testAMissAgainTheSameDayIsTheSameLapse(): void
	{
		$context = $this->prepareTraining([]);

		$this->playInTraining($context, 0, PlayOutcome::Failed);
		$this->playInTraining($context, 0, PlayOutcome::Failed);
		MistakeTraining::recordResult($context->user['id'], $this->tsumegoId($context, 0), PlayOutcome::Failed, 'V');

		$this->assertSame(1, $this->lapses($context, 0), 'The tries that fail before the solve are one lapse');
	}

	public function testAMissOnAnotherDayIsAnotherLapse(): void
	{
		$context = $this->prepareTraining([]);
		$this->playInTraining($context, 0, PlayOutcome::Failed);

		// The day boundary passes with the problem still due and unsolved.
		MistakeTraining::shiftReviews($context->user['id'], -1);
		$this->playInTraining($context, 0, PlayOutcome::Failed);

		$this->assertSame(2, $this->lapses($context, 0), 'Missing it again on a new day shows it is still not known');
	}

	public function testAMissOutsideTheTrainingOnAProblemThatIsNotDueIsALapse(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];
		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'W');

		$row = $this->poolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['lapses'], 'A mistake in the level mode shows the problem is not known either');
		$this->assertSame(0, (int) $row['rung']);
		$this->assertDaysAhead(1, $row, 'And it comes back tomorrow like after any mistake');
	}

	public function testMissingAProblemAtTheBottomAgainOutsideTheTrainingIsTheSameLapse(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];
		$this->enterTraining($userId, $tsumegoId);
		$this->reviewCleanly($userId, $tsumegoId);

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'W');
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'F');
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'F');

		$this->assertSame(1, (int) $this->poolRow($userId, $tsumegoId)['lapses'],
			'A problem the first mistake took to the bottom has nothing left to lose, so failing it again is the same lapse');
	}

	public function testASolveIsNeverALapse(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 2, 'mistake_training_lapses' => 1]);

		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);
		$this->playInTraining($context, 0, PlayOutcome::Failed);
		MistakeTraining::shiftReviews($context->user['id'], -10);
		$this->playInTraining($context, 0, PlayOutcome::SolvedAfterMistake);

		$this->assertSame(1, $this->lapses($context, 0),
			'A replay counts for nothing, and a solve after a mistake was counted by the mistake already');
	}

	public function testLapsesSlowTheGrowthOfTheWaitsDownToHalfOfTheLadder(): void
	{
		$waits = fn(int $lapses) => array_map(fn(int $rung) => MistakeTraining::wait($rung, $lapses), array_keys(MistakeTraining::ladder()));

		$this->assertSame(MistakeTraining::ladder(), $waits(0), 'A problem that never lapsed waits the ladder');
		$this->assertSame([1, 2, 3, 6, 11, 23, 45, 90], $waits(1), 'A lapse takes a quarter off every wait');
		$this->assertSame([1, 1, 2, 5, 8, 17, 34, 68], $waits(2), 'And every further lapse a quarter of what is left');
		$this->assertSame([1, 1, 2, 4, 8, 15, 30, 60], $waits(3), 'Three lapses reach the floor, half of the ladder');
		$this->assertSame($waits(3), $waits(20), 'A problem that keeps lapsing never waits less than the floor');
	}

	public function testACleanSolveWaitsTheIntervalTheLapsesLeft(): void
	{
		$context = $this->prepareTraining(
			['mistake_training_rung' => 3, 'mistake_training_lapses' => 1],
			['mistake_training_rung' => 4, 'mistake_training_lapses' => 2],
			['mistake_training_rung' => 6, 'mistake_training_lapses' => 5],
		);

		foreach ([0, 1, 2] as $index)
			$this->playInTraining($context, $index, PlayOutcome::SolvedClean);

		$this->assertDaysAhead(11, $this->trainingRow($context, 0), 'Rung 4 waits 11 days instead of 15 after a lapse');
		$this->assertDaysAhead(17, $this->trainingRow($context, 1), 'Rung 5 waits 17 days instead of 30 after two lapses');
		$this->assertDaysAhead(60, $this->trainingRow($context, 2), 'The top rung waits no less than half of its 120 days');
		foreach ([0, 1, 2] as $index)
			$this->assertSame([4, 5, 7][$index], (int) $this->trainingRow($context, $index)['rung'],
				'The lapses slow the waits, a clean solve still climbs one rung');
	}

	public function testALapsedProblemStillGraduatesAtTheTopRung(): void
	{
		$context = $this->prepareTraining(['mistake_training_rung' => 7, 'mistake_training_lapses' => 4]);

		$this->playInTraining($context, 0, PlayOutcome::SolvedClean);

		$this->assertNull($this->trainingRow($context, 0)['next_due'], 'Lapses change the waits, not the way out');
		$this->assertSame(4, $this->lapses($context, 0));
	}

	public function testTheTooltipOfADueProblemSaysTheWaitTheLapsesLeft(): void
	{
		$context = $this->prepareTraining(
			['mistake_training_rung' => 3],
			['mistake_training_rung' => 3, 'mistake_training_lapses' => 1],
			['mistake_training_rung' => 0, 'mistake_training_lapses' => 3],
		);

		$buttons = $this->trainingButtons($context);

		$this->assertSame('Rung 4 of 8. A clean solve brings it back in 15 days.', $buttons[$this->tsumegoId($context, 0)]->description);
		$this->assertSame('Rung 4 of 8. A clean solve brings it back in 11 days.', $buttons[$this->tsumegoId($context, 1)]->description,
			'The tooltip promises the wait the problem will get, not the one of the ladder');
		$this->assertSame('Rung 1 of 8. A clean solve brings it back tomorrow.', $buttons[$this->tsumegoId($context, 2)]->description,
			'A wait of one day is tomorrow');
	}

	public function testADeletedProblemIsNotDue(): void
	{
		$context = $this->prepareTraining([], []);
		$userId = $context->user['id'];
		Util::execute('UPDATE tsumego SET deleted = ? WHERE id = ?', [date('Y-m-d H:i:s'), $this->tsumegoId($context, 0)]);

		$this->assertSame(1, MistakeTraining::dueCount($userId), 'The menu does not count a problem the training cannot serve');
		$this->assertSame($this->tsumegoId($context, 1), (int) MistakeTraining::nextDue($userId)['tsumego_id'],
			'The training skips it');
		$this->assertNotNull($this->poolRow($userId, $this->tsumegoId($context, 0)),
			'And keeps its row, in case the problem comes back');
	}

	// ==================== The history ====================

	private function reviews(int $userId): array
	{
		return Util::query('SELECT * FROM mistake_training_review WHERE user_id = ? ORDER BY id', [$userId]);
	}

	private function trainingDay(int $userId): ?array
	{
		return Util::query('SELECT * FROM mistake_training_day WHERE user_id = ? AND day = ?', [$userId, MistakeTraining::today()])[0] ?? null;
	}

	private function dueProblem(int $rung, int $setOrder = 1): array
	{
		return [
			'set_order' => $setOrder,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => $rung],
		];
	}

	public function testAReviewGoesInTheHistory(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(2), 'tsumegos' => [$this->dueProblem(4, 2)]]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$review = MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, 'V', true);

		$this->assertSame(['result' => 'S', 'rung_before' => 2, 'rung_after' => 3, 'climbed' => true, 'cleared' => false, 'clear_bonus' => false],
			array_diff_key($review, ['id' => 0]));
		$rows = $this->reviews($userId);
		$this->assertCount(1, $rows);
		$this->assertSame($review['id'], (int) $rows[0]['id']);
		$this->assertSame($tsumegoId, (int) $rows[0]['tsumego_id']);
		$this->assertSame(MistakeTraining::today(), $rows[0]['day']);
		$this->assertSame('S', $rows[0]['result']);
		$this->assertSame(0, (int) $rows[0]['xp'], 'The XP is the training\'s to award');

		$day = $this->trainingDay($userId);
		$this->assertSame(2, (int) $day['due'], 'The day holds both problems');
		$this->assertSame(1, (int) $day['reviewed']);
		$this->assertSame(0, (int) $day['cleared']);
		$this->assertNull($day['cleared_at']);
	}

	public function testAMissIsLoggedOnceAndTheRetryEndsTheReview(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(3)]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$miss = MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'V', true);
		$this->assertNull(MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'V', true),
			'Missing it again the same day is the same miss');
		$retry = MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedAfterMistake, 'V', true);

		$this->assertSame('F', $miss['result']);
		$this->assertSame([3, 0], [$miss['rung_before'], $miss['rung_after']]);
		$this->assertFalse($miss['climbed']);
		$this->assertSame('S', $retry['result']);
		$this->assertFalse($retry['climbed'], 'A retry earns nothing');
		$this->assertSame(['F', 'S'], array_column($this->reviews($userId), 'result'));
		$this->assertSame(1, (int) $this->trainingDay($userId)['reviewed'], 'One problem was reviewed');
	}

	public function testAGraduationIsLoggedAsSuch(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(count(MistakeTraining::ladder()) - 1)]);
		$userId = $context->user['id'];

		$review = MistakeTraining::recordResult($userId, (int) $context->tsumegos[0]['id'], PlayOutcome::SolvedClean, 'V', true);

		$this->assertSame(MistakeTraining::REVIEW_GRADUATED, $review['result']);
		$this->assertTrue($review['climbed']);
		$this->assertSame(MistakeTraining::REVIEW_GRADUATED, $this->reviews($userId)[0]['result']);
	}

	public function testAProblemThatIsNotDueIsNoReview(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$this->enterTraining($userId, $tsumegoId);

		foreach ([[true, PlayOutcome::SolvedClean], [false, PlayOutcome::SolvedClean], [false, PlayOutcome::Failed]] as [$inTraining, $outcome])
			$this->assertNull(MistakeTraining::recordResult($userId, $tsumegoId, $outcome, 'V', $inTraining));

		$this->assertSame([], $this->reviews($userId));
		$this->assertNull($this->trainingDay($userId));
	}

	public function testAReplayOfAProblemSolvedTodayIsNoReview(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(1)]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, 'V', true);
		$this->assertNull(MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, 'V', true));
		$this->assertNull(MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, 'V', true));

		$this->assertCount(1, $this->reviews($userId));
	}

	public function testTheDayIsClearedOnceWhenItsLastProblemIsSolved(): void
	{
		$tsumegos = [];
		for ($i = 2; $i <= MistakeTraining::CLEAR_DAY_MIN_PROBLEMS; $i++)
			$tsumegos[] = $this->dueProblem(1, $i);
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(1), 'tsumegos' => $tsumegos]);
		$userId = $context->user['id'];

		$cleared = [];
		foreach ($context->tsumegos as $tsumego)
		{
			$review = MistakeTraining::recordResult($userId, (int) $tsumego['id'], PlayOutcome::SolvedClean, 'V', true);
			$cleared[] = [$review['cleared'], $review['clear_bonus']];
		}

		$this->assertSame([[false, false], [false, false], [false, false], [false, false], [true, true]], $cleared);
		$day = $this->trainingDay($userId);
		$this->assertSame(MistakeTraining::CLEAR_DAY_MIN_PROBLEMS, (int) $day['due']);
		$this->assertSame(MistakeTraining::CLEAR_DAY_MIN_PROBLEMS, (int) $day['reviewed']);
		$this->assertSame(1, (int) $day['cleared']);

		$tsumegoId = (int) $context->tsumegos[0]['id'];
		MistakeTraining::makeDueNow($userId, $tsumegoId);
		Util::execute('UPDATE mistake_training_pool SET last_review_day = NULL WHERE user_id = ? AND tsumego_id = ?', [$userId, $tsumegoId]);
		$this->assertFalse(MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, 'V', true)['clear_bonus'],
			'A day is cleared once');
	}

	public function testASmallDayIsClearedWithoutTheBonus(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueProblem(1)]);
		$userId = $context->user['id'];

		$review = MistakeTraining::recordResult($userId, (int) $context->tsumegos[0]['id'], PlayOutcome::SolvedClean, 'V', true);

		$this->assertTrue($review['cleared'], 'The calendar shows the day cleared');
		$this->assertFalse($review['clear_bonus'], 'But a day of one problem earns no bonus');
		$this->assertSame(1, (int) $this->trainingDay($userId)['cleared']);
	}

	public function testTheHistoryTablesAreStrict(): void
	{
		$columns = Util::query(
			'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS'
			. " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('mistake_training_review', 'mistake_training_day')"
		);
		foreach ($columns as $column)
			$this->assertSame($column['COLUMN_NAME'] === 'cleared_at' ? 'YES' : 'NO', $column['IS_NULLABLE'],
				$column['TABLE_NAME'] . '.' . $column['COLUMN_NAME']);

		$keys = Util::query(
			'SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS'
			. " WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('mistake_training_review', 'mistake_training_day') ORDER BY CONSTRAINT_NAME"
		);
		$this->assertSame([
			['CONSTRAINT_NAME' => 'mistake_training_day_user_id', 'DELETE_RULE' => 'CASCADE'],
			['CONSTRAINT_NAME' => 'mistake_training_review_tsumego_id', 'DELETE_RULE' => 'CASCADE'],
			['CONSTRAINT_NAME' => 'mistake_training_review_user_id', 'DELETE_RULE' => 'CASCADE'],
		], $keys);
	}
}
