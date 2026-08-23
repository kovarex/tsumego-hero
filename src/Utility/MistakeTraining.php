<?php

App::uses('Constants', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('Util', 'Utility');

/**
 * Mistake training: SM-2 spaced repetition for failed tsumegos.
 *
 * Owns the training queue (which problems are due) and the SM-2 algorithm.
 * Replays the algorithm from tsumego_attempt history to compute the next review
 * date; state is never stored, only mt_due is persisted.
 */
class MistakeTraining
{
	/**
	 * Graduation threshold: interval >= 365 days means the problem is mastered.
	 */
	public static int $GRADUATION_INTERVAL = 365;

	/**
	 * Initial easiness factor.
	 */
	private static float $INITIAL_EF = 2.5;

	/**
	 * Minimum easiness factor.
	 */
	private static float $MIN_EF = 1.3;

	/**
	 * Compute the next mt_due for a user+tsumego pair by replaying SM-2
	 * from tsumego_attempt history.
	 *
	 * Returns:
	 *  - a datetime string for the next due date, OR
	 *  - null if the problem has graduated (interval >= 365 days) or has no training history
	 */
	public static function computeNextDue(int $userId, int $tsumegoId): ?string
	{
		$attempts = ClassRegistry::init('TsumegoAttempt')->find('all', [
			'conditions' => ['user_id' => $userId, 'tsumego_id' => $tsumegoId],
			'order' => 'created ASC',
		]);

		$ef = self::$INITIAL_EF;
		$n = 0;
		$interval = 0;
		$started = false;
		$lastDate = null;

		foreach ($attempts as $attempt)
		{
			$misplays = (int) $attempt['TsumegoAttempt']['misplays'];
			$solved = (bool) $attempt['TsumegoAttempt']['solved'];
			$created = $attempt['TsumegoAttempt']['created'];

			if (!$started && $misplays > 0)
				$started = true;

			if (!$started)
				continue;

			$lastDate = $created;

			if ($solved && $misplays === 0)
			{
				// Clean solve
				if ($n === 0)
					$interval = 1;
				elseif ($n === 1)
					$interval = 6;
				else
					$interval = (int) round($interval * $ef);
				$n++;
				$ef += 0.1;
			}
			else
			{
				// Mistakes or failure
				$n = 0;
				$interval = 1;
				$ef -= 0.2;
			}
			$ef = max(self::$MIN_EF, $ef);
		}

		if (!$started || $lastDate === null)
			return null;

		// Graduation
		if ($interval >= self::$GRADUATION_INTERVAL)
			return null;

		return date('Y-m-d H:i:s', strtotime($lastDate . " +{$interval} days"));
	}

	/**
	 * Build the navigation buttons for the current training queue.
	 * One button per tsumego, preferring the set connection the user is on,
	 * ordered by mt_due (most overdue first).
	 */
	public static function buildQueueButtons(int $currentSetConnectionID): TsumegoButtons
	{
		$rows = Util::query(self::queueSql(), [$currentSetConnectionID, Auth::getUserID()]);
		return TsumegoButtons::fromRows($rows, $currentSetConnectionID, 200);
	}

	/**
	 * The training queue: user's tsumego_status rows that are due for review.
	 * Deduplicates in SQL (one row per tsumego via ROW_NUMBER), preferring the
	 * current set connection so navigation stays on the connection the user is on.
	 */
	private static function queueSql(): string
	{
		return "
			SELECT tsumego_id, set_connection_id, num, status, rating, sgf
			FROM (
				SELECT
					ts.tsumego_id,
					sc.id AS set_connection_id,
					sc.num,
					ts.status,
					t.rating,
					ts.mt_due,
					COALESCE(sgf.sgf, '') AS sgf,
					ROW_NUMBER() OVER (
						PARTITION BY ts.tsumego_id
						ORDER BY CASE WHEN sc.id = ? THEN 0 ELSE 1 END, sc.id
					) AS rn
				FROM tsumego_status ts
				JOIN set_connection sc ON sc.tsumego_id = ts.tsumego_id
				JOIN tsumego t ON t.id = ts.tsumego_id
				LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = ts.tsumego_id)
				WHERE ts.user_id = ?
				  AND ts.mt_due IS NOT NULL
				  AND ts.mt_due <= NOW()
				  AND t.deleted IS NULL
			) x
			WHERE rn = 1
			ORDER BY mt_due ASC
		";
	}
}
