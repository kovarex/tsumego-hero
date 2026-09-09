<?php

App::uses('Constants', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('TsumegoUtil', 'Utility');
App::uses('Util', 'Utility');
App::uses('Auth', 'Utility');

/**
 * Mistake training: spaced repetition for tsumegos you did not solve on the
 * first attempt.
 *
 * The pool (mistake_training_pool) is the single source of truth: one row per
 * (user, tsumego) tracking the review-ladder rung and the next review date.
 * A clean solve climbs a rung; a fail drops one; a clean solve at the top
 * graduates (removed from the pool). Training results are never written to
 * tsumego_attempt, since it is a separate, consequence-free mode like time mode.
 */
class MistakeTraining
{
	/**
	 * Review ladder: days until the next review at each rung. A clean solve
	 * climbs a rung; a lapse drops a rung; a clean solve at the top graduates.
	 */
	private static array $LADDER = [1, 3, 7, 14, 30, 60];

	/**
	 * Apply a play result to the training pool.
	 *
	 * @return bool true if the problem graduated (removed from the pool)
	 */
	public static function recordResult(int $userId, int $tsumegoId, bool $solved, ?string $oldStatus): bool
	{
		$pool = self::getPoolRow($userId, $tsumegoId);

		if ($pool === null)
		{
			// Entry: a fail on a problem the user has not solved yet (a
			// first-encounter mistake). A clean solve never enters the pool.
			if ($solved)
				return false;
			if ($oldStatus !== null && TsumegoUtil::isSolvedStatus($oldStatus))
				return false;
			self::enterPool($userId, $tsumegoId);
			return false;
		}

		$top = count(self::$LADDER) - 1;

		if ($solved)
		{
			// Clean solve: climb a rung, or graduate at the top.
			if ((int) $pool['rung'] >= $top)
			{
				self::graduate($userId, $tsumegoId);
				return true;
			}
			self::setRung($userId, $tsumegoId, (int) $pool['rung'] + 1);
			return false;
		}

		// Fail: drop a rung, never below the daily rung.
		self::setRung($userId, $tsumegoId, max((int) $pool['rung'] - 1, 0));
		return false;
	}

	/**
	 * The pool row for a user+tsumego, or null if not in training.
	 *
	 * @return array|null
	 */
	public static function getPoolRow(int $userId, int $tsumegoId): ?array
	{
		$row = ClassRegistry::init('MistakeTrainingPool')->find('first', [
			'conditions' => ['user_id' => $userId, 'tsumego_id' => $tsumegoId],
		]);
		return empty($row['MistakeTrainingPool']) ? null : $row['MistakeTrainingPool'];
	}

	/**
	 * Number of tsumegos currently due for review.
	 */
	public static function dueCount(int $userId): int
	{
		return (int) ClassRegistry::init('MistakeTrainingPool')->find('count', [
			'conditions' => [
				'user_id' => $userId,
				'next_due <=' => date('Y-m-d H:i:s'),
			],
		]);
	}

	/**
	 * The next pool row due for review, or null if none.
	 *
	 * @return array|null
	 */
	public static function nextDue(int $userId): ?array
	{
		$row = ClassRegistry::init('MistakeTrainingPool')->find('first', [
			'conditions' => ['user_id' => $userId, 'next_due <=' => date('Y-m-d H:i:s')],
			'order' => 'next_due ASC',
		]);
		return empty($row['MistakeTrainingPool']) ? null : $row['MistakeTrainingPool'];
	}

	/**
	 * Total number of problems in the training pool.
	 */
	public static function totalInTraining(int $userId): int
	{
		return (int) ClassRegistry::init('MistakeTrainingPool')->find('count', [
			'conditions' => ['user_id' => $userId],
		]);
	}

	/**
	 * Upcoming reviews grouped by day, for the "all caught up" view.
	 */
	public static function upcomingByDay(int $userId): array
	{
		$rows = ClassRegistry::init('MistakeTrainingPool')->find('all', [
			'conditions' => ['user_id' => $userId, 'next_due >' => date('Y-m-d H:i:s')],
			'order' => 'next_due ASC',
			'limit' => 30,
		]);
		$byDay = [];
		foreach ($rows as $row)
		{
			$day = date('Y-m-d', strtotime($row['MistakeTrainingPool']['next_due']));
			$byDay[$day] = ($byDay[$day] ?? 0) + 1;
		}
		return $byDay;
	}

	/**
	 * Remove a problem from the pool (e.g. when the tsumego is deleted or has no
	 * set connection).
	 */
	public static function removeFromPool(int $userId, int $tsumegoId): void
	{
		ClassRegistry::init('MistakeTrainingPool')->deleteAll(['user_id' => $userId, 'tsumego_id' => $tsumegoId]);
	}

	/**
	 * Build the navigation buttons for the current training queue.
	 * One button per tsumego, preferring the set connection the user is on,
	 * ordered by next_due (most overdue first).
	 */
	public static function buildQueueButtons(int $currentSetConnectionID): TsumegoButtons
	{
		$rows = Util::query(self::queueSql(), [$currentSetConnectionID, Auth::getUserID()]);
		return TsumegoButtons::fromRows($rows, $currentSetConnectionID, 200);
	}

	/**
	 * Add a problem to the pool on a first-encounter fail.
	 */
	private static function enterPool(int $userId, int $tsumegoId): void
	{
		$now = date('Y-m-d H:i:s');
		$Model = ClassRegistry::init('MistakeTrainingPool');
		$Model->create();
		$Model->save([
			'user_id' => $userId,
			'tsumego_id' => $tsumegoId,
			'rung' => 0,
			'next_due' => date('Y-m-d H:i:s', strtotime($now . ' +' . self::$LADDER[0] . ' days')),
		]);
	}

	/**
	 * Move a pool row to a new rung and recompute its next review date.
	 */
	private static function setRung(int $userId, int $tsumegoId, int $rung): void
	{
		$now = date('Y-m-d H:i:s');
		$nextDue = date('Y-m-d H:i:s', strtotime($now . ' +' . self::$LADDER[$rung] . ' days'));

		$sql = 'UPDATE mistake_training_pool SET rung = ?, next_due = ?, modified = ?';
		$params = [$rung, $nextDue, $now];
		$sql .= ' WHERE user_id = ? AND tsumego_id = ?';
		$params[] = $userId;
		$params[] = $tsumegoId;

		Util::execute($sql, $params);
	}

	/**
	 * Remove a problem from the pool after graduating.
	 */
	private static function graduate(int $userId, int $tsumegoId): void
	{
		ClassRegistry::init('MistakeTrainingPool')->deleteAll(['user_id' => $userId, 'tsumego_id' => $tsumegoId]);
	}

	/**
	 * The training queue: pool rows that are due for review. Deduplicates in SQL
	 * (one row per tsumego via ROW_NUMBER), preferring the current set connection
	 * so navigation stays on the connection the user is on.
	 */
	private static function queueSql(): string
	{
		return "
			SELECT tsumego_id, set_connection_id, num, status, rating, sgf
			FROM (
				SELECT
					p.tsumego_id,
					sc.id AS set_connection_id,
					sc.num,
					ts.status,
					t.rating,
					p.next_due,
					COALESCE(sgf.sgf, '') AS sgf,
					ROW_NUMBER() OVER (
						PARTITION BY p.tsumego_id
						ORDER BY CASE WHEN sc.id = ? THEN 0 ELSE 1 END, sc.id
					) AS rn
				FROM mistake_training_pool p
				JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id
				JOIN tsumego t ON t.id = p.tsumego_id
				LEFT JOIN tsumego_status ts ON ts.user_id = p.user_id AND ts.tsumego_id = p.tsumego_id
				LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = p.tsumego_id)
				WHERE p.user_id = ?
				  AND p.next_due <= NOW()
				  AND t.deleted IS NULL
			) x
			WHERE rn = 1
			ORDER BY next_due ASC
		";
	}
}
