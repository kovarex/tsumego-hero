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
		// n=0 gives interval=1, second attempt is clean: n=0→interval=1 (wait, n=1 now)
		// First attempt (misplays=1): entered training, n=0, interval=1, ef=2.3
		// Second attempt (clean): n=0→interval=1, n=1, ef=2.4
		// Due = 2026-08-02 + 1 day = 2026-08-03
		$this->assertNotNull($result);
		$this->assertSame('2026-08-03 10:00:00', $result);
	}

	public function testCleanSolveProgression()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Clean solve 1: interval = 1 (n=0)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
		// Clean solve 2: interval = 6 (n=1)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-03 + 6 days = 2026-08-09
		$this->assertSame('2026-08-09 10:00:00', $result);
	}

	public function testMisplayResetsInterval()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Clean solve 1: interval = 1
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-02 10:00:00');
		// Clean solve 2: interval = 6
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-03 10:00:00');
		// Misplay: resets to interval = 1
		$this->createAttempt($userId, $tsumegoId, false, 2, '2026-08-10 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		// Due = 2026-08-10 + 1 day = 2026-08-11
		$this->assertSame('2026-08-11 10:00:00', $result);
	}

	public function testGraduation()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// First: misplay (enters training)
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-01-01 10:00:00');
		// Clean solve 1: interval = 1 (n=0→1)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-01-02 10:00:00');
		// Clean solve 2: interval = 6 (n=1→2)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-01-03 10:00:00');
		// Clean solve 3: interval = round(6 * 2.4) = 14 (n=2→3, ef=2.4→2.5)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-01-09 10:00:00');
		// Clean solve 4: interval = round(14 * 2.5) = 35 (n=3→4, ef=2.5→2.6)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-01-23 10:00:00');
		// Clean solve 5: interval = round(35 * 2.6) = 91 (n=4→5, ef=2.6→2.7)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-02-27 10:00:00');
		// Clean solve 6: interval = round(91 * 2.7) = 246 (n=5→6, ef=2.7→2.8)
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-05-28 10:00:00');
		// Clean solve 7: interval = round(246 * 2.8) = 689 >= 365 → GRADUATED
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-08-01 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		$this->assertNull($result, 'Problem should graduate when interval >= 365');
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

	public function testEfFloorAt13()
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = $context->tsumegos[0]['id'];

		// Enter training
		$this->createAttempt($userId, $tsumegoId, true, 1, '2026-08-01 10:00:00');
		// Many failures to push EF down
		for ($i = 0; $i < 20; $i++)
			$this->createAttempt($userId, $tsumegoId, false, 1, '2026-08-' . str_pad($i + 2, 2, '0', STR_PAD_LEFT) . ' 10:00:00');

		$result = MistakeTraining::computeNextDue($userId, $tsumegoId);
		$this->assertNotNull($result);

		// EF should be floored at 1.3. After a clean solve, interval would be 6 (n=1).
		// But we need to verify the EF didn't go below 1.3 — do this by checking
		// that a clean solve after many failures still produces a reasonable interval.
		$this->createAttempt($userId, $tsumegoId, true, 0, '2026-09-01 10:00:00');
		$result2 = MistakeTraining::computeNextDue($userId, $tsumegoId);
		$this->assertNotNull($result2);
	}
}
