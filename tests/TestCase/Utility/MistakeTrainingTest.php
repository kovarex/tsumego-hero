<?php

App::uses('MistakeTraining', 'Utility');
App::uses('Constants', 'Utility');

class MistakeTrainingTest extends TestCaseWithAuth
{
	private function createAttempt(int $userId, int $tsumegoId, bool $solved, int $misplays, string $created): void
	{
		ClassRegistry::init('TsumegoAttempt')->create();
		ClassRegistry::init('TsumegoAttempt')->save([
			'TsumegoAttempt' => [
				'user_id' => $userId,
				'tsumego_id' => $tsumegoId,
				'solved' => $solved ? 1 : 0,
				'misplays' => $misplays,
				'seconds' => 10,
				'tsumego_rating' => 1000,
				'user_rating' => 1000,
				'gain' => 0,
				'created' => $created,
			],
		]);
	}

	public function testNoAttemptsReturnsNull()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$result = MistakeTraining::computeNextDue($context->user['id'], $context->tsumegos[0]['id']);
		$this->assertNull($result);
	}

	public function testCleanSolveOnlyReturnsNull()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// Clean solve (misplays=0) — never enters training
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-01 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		$this->assertNull($result);
	}

	public function testFirstMisplayThenCleanSolve()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First attempt: misplay then solve (misplays > 0)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Second attempt: clean solve
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Entering misplay: rung 0. Clean solve: rung 1 (3 days).
		// Due = 2026-08-02 + 3 days = 2026-08-05
		$this->assertSame('2026-08-05 10:00:00', $result);
	}

	public function testCleanSolveProgression()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Clean solve 1: rung 1 (3 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
		// Clean solve 2: rung 2 (7 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-03 + 7 days = 2026-08-10
		$this->assertSame('2026-08-10 10:00:00', $result);
	}

	public function testFailShrinksIntervalAfterCleanStreak()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Clean solve 1: rung 1 (3 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
		// Clean solve 2: rung 2 (7 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');
		// Fail: drops one rung to 3 days instead of resetting to 1
		$this->createAttempt($userId, $tsumegoId, false, 1, '2026-08-10 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-10 + 3 days = 2026-08-13
		$this->assertSame('2026-08-13 10:00:00', $result);
	}

	public function testSolveAfterFailKeepsGrowingFromShrunkInterval()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Clean solve 1: rung 1 (3 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
		// Clean solve 2: rung 2 (7 days)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');
		// Fail: drops to rung 1 (3 days)
		$this->createAttempt($userId, $tsumegoId, false, 1, '2026-08-10 10:00:00');
		// Clean solve climbs back to rung 2, not from rung 0
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-13 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-13 + 7 days = 2026-08-20
		$this->assertSame('2026-08-20 10:00:00', $result);
	}

	public function testFailedAttemptDropsOneRung()
	{
		foreach ([false, true] as $solved)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$userId = $context->user['id'];
			$tsumegoId = $context->tsumegos[0]['id'];

			// Enter training and climb to rung 3 (14 days)
			$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
			$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
			$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');
			$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-10 10:00:00');
			// One failed attempt drops one rung, regardless of how many misplays
			$this->createAttempt($userId, $tsumegoId, $solved, 2, '2026-08-24 10:00:00');

			$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
			// rung 3 -> rung 2 (7 days)
			$this->assertSame('2026-08-31 10:00:00', $result, 'solved=' . ($solved ? '1' : '0'));
		}
	}

	public function testGraduation()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// Enter training, then climb the ladder to the top rung
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		foreach (['02', '03', '04', '05', '06', '07'] as $day)
			$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-' . $day . ' 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		$this->assertNull($result, 'Problem should graduate on a clean solve at the top rung');
	}

	public function testFailedAttemptEntersTraining()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// Failed attempt (not solved, misplays > 0)
		$this->createAttempt($userId, $tsumegoId, false, 3, '2026-08-01 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-01 + 1 day = 2026-08-02
		$this->assertSame('2026-08-02 10:00:00', $result);
	}

	public function testFailsFloorAtDailyRung()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// Enter training
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Repeated failures never push below the daily rung
		for ($i = 0; $i < 5; $i++)
			$this->createAttempt($userId, $tsumegoId, false, 1, '2026-08-' . str_pad($i + 2, 2, '0', STR_PAD_LEFT) . ' 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-06 + 1 day = 2026-08-07 (still daily)
		$this->assertSame('2026-08-07 10:00:00', $result);
	}
}
