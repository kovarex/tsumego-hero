<?php

App::uses('Constants', 'Utility');

/**
 * SM-2 spaced repetition algorithm for mistake training.
 *
 * Replays the algorithm from tsumego_attempt history to compute
 * the next review date. State is never stored, only mt_due is persisted.
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
}
