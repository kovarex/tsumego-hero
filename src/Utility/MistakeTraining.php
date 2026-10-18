<?php

namespace App\Utility;

use ClassRegistry;
use DateTime;
use SetConnection;
use TsumegoStatus;

/**
 * Mistake training: spaced repetition for tsumegos you did not solve on the
 * first attempt.
 *
 * The pool (mistake_training_pool) is the single source of truth: one row per
 * (user, tsumego) tracking the review-ladder rung and the next review day. Only
 * a clean solve advances the rung; a solve that needed a retry keeps it, and a
 * mistake takes the problem back to the bottom. Either comes back tomorrow. A
 * clean solve at the top rung graduates, removing the problem from the pool.
 * Training results are never written to tsumego_attempt, since it is a separate,
 * consequence-free mode like time mode, and training never writes
 * tsumego_status either: graduation only removes the problem from the pool.
 *
 * Reviews are counted in days, not in hours: the next review of a problem is the
 * start of a day, so a mistake made in the evening is not due tomorrow morning
 * minus an hour. See $DAY_START_HOUR for the boundary that keeps a session after
 * midnight on the day it started.
 */
class MistakeTraining
{
	/**
	 * Review ladder: days until the next review at each rung. Only a clean solve
	 * climbs a rung; anything else is due again the next day.
	 */
	private static array $LADDER = [1, 3, 7, 14, 30, 60];

	/**
	 * Hour at which a new day starts for the training. Reviews are counted in days,
	 * so a problem due tomorrow is there at this hour, not 24 hours after the
	 * mistake. The boundary keeps a session after midnight on the previous day.
	 */
	public static int $DAY_START_HOUR = 4;

	/**
	 * How many past mistakes one import brings in. A player with years of history has
	 * thousands, and putting them all in the queue at once would drown the training.
	 */
	public const IMPORT_LIMIT = 20;

	/**
	 * Route prefix for queue links. Every one of them re-asserts training mode.
	 */
	public static string $PLAY_LINK_PREFIX = '/mistake-training/play/';

	/**
	 * Landing page of the training queue.
	 */
	public static string $QUEUE_LINK = '/mistake-training';

	/**
	 * Where the "bring past mistakes in" button posts.
	 */
	public static string $IMPORT_LINK = '/mistake-training/import';

	/**
	 * Admin toolbox for trying the training without waiting between reviews.
	 */
	public static string $DEBUG_LINK = '/mistake-training/debug';

	/**
	 * Apply a play result to the training pool. Only a clean solve advances the
	 * ladder; graduation (a clean solve at the top rung) only removes the problem
	 * from the pool, and the tsumego status is never touched, it belongs to the
	 * normal modes.
	 *
	 * Every mode reports here, so the outcomes differ by where the play happened. A
	 * training play gives one attempt, therefore it sends either Failed (the board
	 * locks on the first mistake) or SolvedClean. A play outside the training can
	 * send Failed and then SolvedAfterMistake for the same problem: the first call
	 * takes the problem back to the bottom, the second must not earn a rung for the
	 * retry.
	 */
	public static function recordResult(int $userId, int $tsumegoId, PlayOutcome $outcome, ?string $oldStatus): void
	{
		$clean = $outcome === PlayOutcome::SolvedClean;
		$pool = self::getPoolRow($userId, $tsumegoId);

		if ($pool === null)
		{
			// Entry: a problem that was solved and still went wrong. Solved now after a
			// mistake, or solved once and failed now. A problem the player has never
			// solved belongs to practice instead of review, so it stays out however
			// often it is failed.
			$solvedAfterMistake = $outcome === PlayOutcome::SolvedAfterMistake;
			$lapsed = $outcome === PlayOutcome::Failed && $oldStatus === TsumegoStatus::$FORGOTTEN;
			if (!$solvedAfterMistake && !$lapsed)
				return;
			self::enterPool($userId, $tsumegoId);
			return;
		}

		if (!$clean)
		{
			// A problem that was missed is not known, however far up the ladder it had
			// climbed, so it starts over at the bottom and comes back tomorrow. A solve
			// that needed a retry earns nothing but costs nothing either.
			$rung = $outcome === PlayOutcome::SolvedAfterMistake ? (int) $pool['rung'] : 0;
			self::setRung($userId, $tsumegoId, $rung, self::$LADDER[0]);
			return;
		}

		// Clean solve: climb a rung, or graduate at the top.
		if ((int) $pool['rung'] >= count(self::$LADDER) - 1)
		{
			self::graduate($userId, $tsumegoId);
			return;
		}
		self::setRung($userId, $tsumegoId, (int) $pool['rung'] + 1);
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
	 * Whether a problem is in the pool and scheduled for review right now. A graduated
	 * problem has no next review, and null would otherwise read as older than today.
	 */
	public static function isDue(int $userId, int $tsumegoId): bool
	{
		$row = self::getPoolRow($userId, $tsumegoId);
		return $row !== null && $row['next_due'] !== null && $row['next_due'] <= date('Y-m-d H:i:s');
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
	 * Total number of problems in the training pool. Counts only what the queue can
	 * serve, so a problem that is gone (deleted, or in no set) is not announced, and a
	 * graduated problem (no next review) is not in the training any more.
	 */
	public static function totalInTraining(int $userId): int
	{
		$rows = Util::query(
			'SELECT COUNT(DISTINCT p.id) AS total FROM mistake_training_pool p'
			. ' JOIN tsumego t ON t.id = p.tsumego_id'
			. ' JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id'
			. ' WHERE p.user_id = ? AND p.next_due IS NOT NULL AND t.deleted IS NULL',
			[$userId]
		);

		return (int) $rows[0]['total'];
	}

	/**
	 * How many past mistakes are not in the training yet. An attempt that was solved
	 * with misplays is a problem the player solved but not on the first try; one that
	 * was never solved is practice rather than review, so it does not count.
	 */
	public static function importCandidates(int $userId): int
	{
		$rows = Util::query('SELECT COUNT(DISTINCT a.tsumego_id) AS total' . self::pastMistakesSql(), [$userId]);
		return (int) $rows[0]['total'];
	}

	/**
	 * Bring the player's past mistakes into the training, newest first. They are due
	 * at once, because they are old rather than scheduled. A problem already in the
	 * pool keeps its rung and its next review, so the import can be run again.
	 *
	 * @return int How many problems the training gained.
	 */
	public static function importPastMistakes(int $userId, int $limit = self::IMPORT_LIMIT): int
	{
		$rows = Util::query(
			'SELECT a.tsumego_id, MAX(a.created) AS last_mistake' . self::pastMistakesSql()
			. ' GROUP BY a.tsumego_id ORDER BY last_mistake DESC LIMIT ' . (int) $limit,
			[$userId]
		);

		$now = date('Y-m-d H:i:s');
		foreach ($rows as $row)
			self::addProblem($userId, (int) $row['tsumego_id'], $now);

		return count($rows);
	}

	/**
	 * The ladder intervals, oldest rung first, for screens that explain the wait.
	 *
	 * @return int[]
	 */
	public static function ladder(): array
	{
		return self::$LADDER;
	}

	/**
	 * The pool of one player, soonest review first, for the admin toolbox.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function poolRows(int $userId): array
	{
		return Util::query(
			'SELECT p.tsumego_id, p.rung, p.next_due,'
			. ' (SELECT sc.id FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id ORDER BY sc.id LIMIT 1) AS set_connection_id'
			. ' FROM mistake_training_pool p WHERE p.user_id = ?'
			. ' ORDER BY p.next_due ASC, p.id ASC LIMIT 50',
			[$userId]
		);
	}

	/**
	 * Move every review of a player by $days days, so a three week wait becomes a
	 * click. Only the date moves: the rung says how well the problem is known.
	 *
	 * @return int How many rows moved.
	 */
	public static function shiftReviews(int $userId, int $days): int
	{
		return Util::execute(
			'UPDATE mistake_training_pool SET next_due = DATE_ADD(next_due, INTERVAL ? DAY) WHERE user_id = ?',
			[$days, $userId]
		);
	}

	/**
	 * Put one problem on a rung and make it due now, which is how the toolbox jumps
	 * to a late review instead of playing the ladder up to it.
	 */
	public static function moveToRung(int $userId, int $tsumegoId, int $rung): void
	{
		$rung = max(0, min($rung, count(self::$LADDER) - 1));
		self::setRung($userId, $tsumegoId, $rung, 0);
	}

	/**
	 * Schedule a problem for right now, so the toolbox can open it in the training
	 * without waiting for its review day.
	 */
	public static function makeDueNow(int $userId, int $tsumegoId): void
	{
		Util::execute(
			'UPDATE mistake_training_pool SET next_due = ? WHERE user_id = ? AND tsumego_id = ?',
			[date('Y-m-d H:i:s'), $userId, $tsumegoId]
		);
	}

	/**
	 * Take a problem to the top rung and graduate it, so the toolbox can see the exit
	 * without six reviews.
	 */
	public static function graduateNow(int $userId, int $tsumegoId): void
	{
		self::setRung($userId, $tsumegoId, count(self::$LADDER) - 1, 0);
		self::graduate($userId, $tsumegoId);
	}

	/**
	 * Add one problem to the pool as due now, whether or not it was a mistake. For
	 * the toolbox, and the import of past mistakes.
	 */
	public static function addProblem(int $userId, int $tsumegoId, ?string $due = null): void
	{
		$now = date('Y-m-d H:i:s');
		Util::execute(
			'INSERT INTO mistake_training_pool (user_id, tsumego_id, rung, next_due, created, modified)'
			. ' VALUES (?, ?, 0, ?, ?, ?)'
			. ' ON DUPLICATE KEY UPDATE user_id = user_id',
			[$userId, $tsumegoId, $due ?? $now, $now, $now]
		);
	}

	/**
	 * Empty the pool of one player.
	 *
	 * @return int How many rows were removed.
	 */
	public static function clearPool(int $userId): int
	{
		return Util::execute('DELETE FROM mistake_training_pool WHERE user_id = ?', [$userId]);
	}

	/**
	 * The attempts that are past mistakes and are not in the training yet: a problem
	 * the player solved with misplays, still alive, still reachable from a set.
	 */
	private static function pastMistakesSql(): string
	{
		return ' FROM tsumego_attempt a'
			. ' JOIN tsumego t ON t.id = a.tsumego_id AND t.deleted IS NULL'
			. ' WHERE a.user_id = ? AND a.solved = 1 AND a.misplays > 0'
			. ' AND EXISTS (SELECT 1 FROM set_connection sc WHERE sc.tsumego_id = a.tsumego_id)'
			. ' AND NOT EXISTS (SELECT 1 FROM mistake_training_pool p'
			. ' WHERE p.user_id = a.user_id AND p.tsumego_id = a.tsumego_id)';
	}

	/**
	 * Upcoming reviews per day, for the "all caught up" view: the next $maxDays
	 * days that have reviews, how many further days are scheduled, and the last
	 * of them.
	 *
	 * Counts are aggregated over the whole pool, so a day is never cut off
	 * halfway through by a row limit.
	 *
	 * @return array{days: array<string, int>, furtherDays: int, lastDay: ?string}
	 */
	public static function upcomingByDay(int $userId, int $maxDays = 7): array
	{
		$rows = Util::query(
			'SELECT DATE(p.next_due) AS day, COUNT(DISTINCT p.id) AS day_count FROM mistake_training_pool p'
			. ' JOIN tsumego t ON t.id = p.tsumego_id'
			. ' JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id'
			. ' WHERE p.user_id = ? AND p.next_due > ? AND t.deleted IS NULL'
			. ' GROUP BY DATE(p.next_due) ORDER BY day',
			[$userId, date('Y-m-d H:i:s')]
		);
		$countsByDay = [];
		foreach ($rows as $row)
			$countsByDay[$row['day']] = (int) $row['day_count'];

		return [
			'days' => array_slice($countsByDay, 0, $maxDays, true),
			'furtherDays' => max(0, count($countsByDay) - $maxDays),
			'lastDay' => $countsByDay ? array_key_last($countsByDay) : null,
		];
	}

	/**
	 * How a review day is named for the player: "today", "tomorrow", a weekday
	 * within the coming week, a date beyond that.
	 */
	public static function describeDay(string $day, ?string $today = null): string
	{
		$today = $today ?: self::today();
		$daysAhead = (new DateTime($today))->diff(new DateTime($day))->days;
		if ($daysAhead <= 0)
			return 'today';
		if ($daysAhead == 1)
			return 'tomorrow';
		if ($daysAhead <= 6)
			return date('l', strtotime($day));
		return date('M j', strtotime($day));
	}

	/**
	 * Remove a problem from the pool: when the tsumego is deleted or has no set
	 * connection. Graduation goes through graduate().
	 */
	public static function removeFromPool(int $userId, int $tsumegoId): void
	{
		ClassRegistry::init('MistakeTrainingPool')->deleteAll(['user_id' => $userId, 'tsumego_id' => $tsumegoId]);
	}

	/**
	 * Build the navigation buttons for the current training queue.
	 * One button per tsumego, preferring the set connection the user is on,
	 * ordered by next_due (most overdue first). Links stay on the training route,
	 * which owns the mode.
	 */
	public static function buildQueueButtons(int $currentSetConnectionID): TsumegoButtons
	{
		$rows = Util::query(self::queueSql(), [$currentSetConnectionID, Auth::getUserID(), date('Y-m-d H:i:s')]);
		$buttons = TsumegoButtons::fromRows($rows, $currentSetConnectionID, 200, self::$PLAY_LINK_PREFIX);
		return $buttons;
	}

	/**
	 * Add a problem to the pool at the bottom rung, due on the next training day.
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
			'next_due' => self::dueDate(self::$LADDER[0]),
			'created' => $now,
			'modified' => $now,
		]);
	}

	/**
	 * The current training day. A session that runs past midnight still belongs to
	 * the day it started on, until the day boundary passes.
	 */
	public static function today(): string
	{
		return date('Y-m-d', strtotime('-' . self::$DAY_START_HOUR . ' hours'));
	}

	/**
	 * The moment a review scheduled $daysAhead days out becomes due: the start of
	 * that day, never "now plus the interval".
	 */
	private static function dueDate(int $daysAhead): string
	{
		$day = date('Y-m-d', strtotime(self::today() . ' +' . $daysAhead . ' days'));
		return $day . ' ' . str_pad((string) self::$DAY_START_HOUR, 2, '0', STR_PAD_LEFT) . ':00:00';
	}

	/**
	 * Move a pool row to a new rung and set its next review. The review waits out
	 * the rung's interval unless $daysAhead names the wait explicitly (a retry is
	 * due tomorrow whatever rung the problem sits on).
	 */
	private static function setRung(int $userId, int $tsumegoId, int $rung, ?int $daysAhead = null): void
	{
		$now = date('Y-m-d H:i:s');
		$daysAhead = $daysAhead ?? self::$LADDER[$rung];
		$nextDue = self::dueDate($daysAhead);

		$sql = 'UPDATE mistake_training_pool SET rung = ?, next_due = ?, modified = ?';
		$params = [$rung, $nextDue, $now];
		$sql .= ' WHERE user_id = ? AND tsumego_id = ?';
		$params[] = $userId;
		$params[] = $tsumegoId;

		Util::execute($sql, $params);
	}

	/**
	 * A clean solve at the top rung ends training: the problem stops being scheduled
	 * and its status is left alone, it belongs to the normal modes. The row stays with
	 * no next review, because it remembers that the training already knows the problem;
	 * without it a mastered problem would come back as a past mistake to import.
	 */
	private static function graduate(int $userId, int $tsumegoId): void
	{
		Util::execute(
			'UPDATE mistake_training_pool SET next_due = NULL, modified = ? WHERE user_id = ? AND tsumego_id = ?',
			[date('Y-m-d H:i:s'), $userId, $tsumegoId]
		);
	}

	/**
	 * The training queue: pool rows that are due for review. Deduplicates in SQL
	 * (one row per tsumego via ROW_NUMBER), preferring the current set connection
	 * so navigation stays on the connection the user is on, then the set display
	 * order every other screen uses.
	 */
	private static function queueSql(): string
	{
		$displayOrder = SetConnection::displayOrderForSetSql('st');

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
						ORDER BY CASE WHEN sc.id = ? THEN 0 ELSE 1 END, $displayOrder, sc.id
					) AS rn
				FROM mistake_training_pool p
				JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id
				JOIN `set` st ON st.id = sc.set_id
				JOIN tsumego t ON t.id = p.tsumego_id
				LEFT JOIN tsumego_status ts ON ts.user_id = p.user_id AND ts.tsumego_id = p.tsumego_id
				LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = p.tsumego_id)
				WHERE p.user_id = ?
				  AND p.next_due <= ?
				  AND t.deleted IS NULL
			) x
			WHERE rn = 1
			ORDER BY next_due ASC
		";
	}
}
