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
 * (user, tsumego) tracking the review-ladder rung, the number of lapses, the next
 * review day, and the day and result of the last review. Only a clean solve of a
 * problem that is due advances the rung, and a clean solve at the top rung
 * graduates, removing the problem from the schedule.
 * A mistake on a problem that is due takes it back to the bottom but keeps it
 * due: it waits in today's training until it is solved, and that solve, like any
 * solve that needed a retry, earns nothing and brings it back tomorrow. Training
 * results are never written to tsumego_attempt, since it is a separate,
 * consequence-free mode like time mode, and training never writes
 * tsumego_status either: graduation only removes the problem from the schedule.
 *
 * A mistake on a problem in the pool is a lapse, and every lapse makes the waits
 * of that problem grow more slowly from then on, the way SM-2, Anki and FSRS lower
 * the ease of a card that was forgotten: a problem that keeps going wrong is hard
 * for this player, and on the same ladder as the easy ones it is only solved about
 * three times out of four. See wait().
 *
 * A training day is a fixed list: the problems due today and the ones solved
 * today, in the order they came into the pool. A problem solved today stays in it
 * as done, so the row of buttons and the numbering hold still the whole day, and
 * playing it again from there is a replay that counts for nothing.
 *
 * Reviews are counted in days, not in hours: the next review of a problem is the
 * start of a day, so a mistake made in the evening is not due tomorrow evening.
 * The day is the one of the daily cron (CronController::daily), which also gives
 * back the hearts, so the whole site turns to a new day at the same moment.
 */
class MistakeTraining
{
	/**
	 * Review ladder: days until the next review at each rung, for a problem that
	 * never lapsed. Only a clean solve climbs a rung; any other solve is due again the
	 * next day. Lapses shorten the waits, see wait().
	 */
	private static array $LADDER = [1, 2, 4, 8, 15, 30, 60, 120];

	/**
	 * How much of the waits of the ladder each lapse keeps: one lapse makes them
	 * 1, 2, 3, 6, 11, 23, 45 and 90 days. The shortening compounds, so a problem that
	 * keeps lapsing comes back more often with every lapse, down to MIN_GROWTH.
	 */
	public const LAPSE_SLOWDOWN = 0.75;

	/**
	 * The shortest the waits get however often a problem lapsed: half of the ladder,
	 * 1, 1, 2, 4, 8, 15, 30 and 60 days, which three lapses reach. Without a floor a
	 * problem that was hard once would never get far from the bottom again.
	 */
	public const MIN_GROWTH = 0.5;

	/**
	 * What the last review did, stored with its day in last_review_result. A miss
	 * keeps the problem due until it is solved; the other two mark it done for the
	 * day.
	 */
	public const REVIEW_SOLVED = 'S';
	public const REVIEW_MISSED = 'F';
	public const REVIEW_GRADUATED = 'G';

	/**
	 * XP of the training, as shares of what the problem gives in the level mode
	 * (Rating::ratingToXP of its rating). A review that climbs earns a fifth, a
	 * graduation half on top, and clearing a day of at least CLEAR_DAY_MIN_PROBLEMS
	 * problems half of what the day's reviews earned. A retry, a replay and a problem
	 * that is not due earn nothing. Over its eight reviews a problem gives about three
	 * times its XP, but spread over half a year: per play that is about a third of
	 * a new problem, so making a mistake on purpose to get a problem in does not pay.
	 * Hero powers do not multiply it, the training has none.
	 */
	public const REVIEW_XP_SHARE = 0.2;
	public const GRADUATION_XP_SHARE = 0.5;
	public const CLEAR_DAY_XP_SHARE = 0.5;
	public const CLEAR_DAY_MIN_PROBLEMS = 5;

	/**
	 * How many past mistakes one import brings in. A player with years of history has
	 * thousands, and putting them all in the queue at once would drown the training.
	 */
	public const IMPORT_LIMIT = 10;

	/**
	 * Where the count of past mistakes left to import stops; the page says "or more".
	 */
	public const IMPORT_COUNT_CAP = 1000;

	/**
	 * How far back the calendar of the profile goes: half a year, like the graphs of
	 * the profile.
	 */
	public const PROFILE_DAYS = 183;

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
	 * ladder; graduation (a clean solve at the top rung) only stops the schedule,
	 * and the tsumego status is never touched, it belongs to the normal modes.
	 *
	 * Every mode reports here. A result on a problem that is due is its review,
	 * wherever it was played: a mistake takes the problem to the bottom and keeps it
	 * due, so it stays in today's training until it is solved. A solve after a
	 * mistake of the same day, in the same play (Failed, then SolvedAfterMistake) or
	 * in a later one, is a retry: it earns no rung and comes back tomorrow.
	 *
	 * A problem that is not due was reviewed already or waits for a later day. In
	 * the training ($inTraining) that is a replay of a problem solved today, which
	 * counts for nothing. Outside the training a mistake still takes it to the bottom
	 * and a solve after a mistake keeps the rung, both due tomorrow, but a clean solve
	 * does not climb: playing a problem again before its review is won by short-term
	 * memory, and Reset and solve again would graduate it in a minute.
	 *
	 * A problem the player solved (S) or mastered (C) is free play in the level and
	 * rating modes: the player tries things on it, so nothing played there brings it
	 * in or moves it, unless it is due. The time mode ($inTimeMode) plays it against
	 * the clock like any other problem, so a mistake there counts. Running out of time
	 * says nothing about whether a problem is known, so a timeout is ignored.
	 *
	 * A result on a problem that is due is a review, and goes in the history (see
	 * logReview()): the first miss of the day, and the solve that ends the day's
	 * review, be it a climb, a graduation or a retry. Those are returned, for the
	 * XP of the training (awardXp()); anything else returns null.
	 *
	 * @return array{id: int, result: string, rung_before: int, rung_after: int, climbed: bool, cleared: bool, clear_bonus: bool}|null
	 */
	public static function recordResult(int $userId, int $tsumegoId, PlayOutcome $outcome, ?string $oldStatus, bool $inTraining = false, bool $inTimeMode = false): ?array
	{
		if ($outcome === PlayOutcome::TimedOut)
			return null;

		$freePlay = !$inTraining && !$inTimeMode
			&& ($oldStatus === TsumegoStatus::$SOLVED || $oldStatus === TsumegoStatus::$MASTERED);

		$pool = self::getPoolRow($userId, $tsumegoId);

		if ($pool === null)
		{
			// Entry: a problem that was solved and still went wrong. Solved now after a
			// mistake, or solved once and failed now: waiting for its review (W),
			// forgotten (X), or solved in the time mode. A problem the player has never
			// solved belongs to practice instead of review, so it stays out however
			// often it is failed.
			if ($freePlay)
				return null;
			$solvedAfterMistake = $outcome === PlayOutcome::SolvedAfterMistake;
			$lapsed = $outcome === PlayOutcome::Failed
				&& (TsumegoUtil::isSolvedStatus($oldStatus) || $oldStatus === TsumegoStatus::$FORGOTTEN);
			if (!$solvedAfterMistake && !$lapsed)
				return null;
			self::enterPool($userId, $tsumegoId);
			return null;
		}

		$due = self::isRowDue($pool);

		// Played again from today's row after it was solved: nothing to review. Free
		// play leaves the schedule alone too; only a review counts wherever it is played.
		if (!$due && ($inTraining || $freePlay))
			return null;

		// A review that comes late tells which days were missed: the problem waited
		// for them, and the review is about to move its date, so they are written down
		// before the evidence is gone.
		if ($due)
			self::recordMissedDays($userId, $pool['next_due']);

		$rung = (int) $pool['rung'];
		$solved = $outcome === PlayOutcome::SolvedClean || $outcome === PlayOutcome::SolvedAfterMistake;
		if (!$solved)
		{
			// A problem that was missed is not known, however far up the ladder it had
			// climbed, so it starts over at the bottom, and it lapsed, so it climbs more
			// slowly from now on. One that is due stays due: it waits in today's training
			// until it is solved instead of disappearing until tomorrow, and the tries
			// that fail again before that are the same lapse, so only the first miss of
			// the day counts. Any other one comes back tomorrow, and counts only if it
			// had climbed: one already at the bottom was missed recently and has
			// nothing left to lose, so failing it again in another mode is that lapse.
			$firstMissToday = $due && !self::wasMissedToday($pool);
			if ($due ? $firstMissToday : $rung > 0)
				self::countLapse($userId, $tsumegoId);
			if (!$due)
			{
				self::setRung($userId, $tsumegoId, 0, self::$LADDER[0]);
				return null;
			}
			self::markReview($userId, $tsumegoId, self::REVIEW_MISSED, 0);
			return $firstMissToday ? self::logReview($userId, $tsumegoId, self::REVIEW_MISSED, $rung, 0, false) : null;
		}

		// A clean solve before the review is an early replay, see above.
		if (!$due && $outcome === PlayOutcome::SolvedClean)
			return null;

		// A solve that needed a retry earns nothing but costs nothing either, and the
		// problem comes back tomorrow. On a problem that is due, a mistake earlier today
		// makes a clean solve of a later play a retry too. Any other clean solve climbs a
		// rung, or graduates at the top.
		$review = self::REVIEW_SOLVED;
		$newRung = $rung;
		$climbed = false;
		if ($outcome === PlayOutcome::SolvedAfterMistake || ($due && self::wasMissedToday($pool)))
			self::setRung($userId, $tsumegoId, $rung, self::$LADDER[0]);
		elseif ($rung < count(self::$LADDER) - 1)
		{
			$newRung = $rung + 1;
			$climbed = true;
			self::setRung($userId, $tsumegoId, $newRung, null, (int) $pool['lapses']);
		}
		else
		{
			self::graduate($userId, $tsumegoId);
			$review = self::REVIEW_GRADUATED;
			$climbed = true;
		}

		// The review of a problem that is due is today's, and keeps it in today's list
		// as done.
		if (!$due)
			return null;
		self::markReview($userId, $tsumegoId, $review);
		return self::logReview($userId, $tsumegoId, $review, $rung, $newRung, $climbed);
	}

	/**
	 * Write a review into the history: a row of mistake_training_review, and the sums
	 * of the day in mistake_training_day (how many problems the day's training held,
	 * how many were reviewed). A solve that leaves nothing due clears the day; only
	 * the first one does, so the bonus of the day is paid once, and only for a day
	 * of at least CLEAR_DAY_MIN_PROBLEMS problems (clear_bonus).
	 *
	 * @return array{id: int, result: string, rung_before: int, rung_after: int, climbed: bool, cleared: bool, clear_bonus: bool}
	 */
	private static function logReview(int $userId, int $tsumegoId, string $result, int $rungBefore, int $rungAfter, bool $climbed): array
	{
		$now = date('Y-m-d H:i:s');
		$today = self::today();
		Util::execute(
			'INSERT INTO mistake_training_review (user_id, tsumego_id, day, result, rung_before, rung_after, xp, created)'
			. ' VALUES (?, ?, ?, ?, ?, ?, 0, ?)',
			[$userId, $tsumegoId, $today, $result, $rungBefore, $rungAfter, $now]
		);
		$reviewId = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];

		$inTraining = self::countTodaysTraining($userId);
		Util::execute(
			'INSERT INTO mistake_training_day (user_id, day, due, reviewed)'
			. ' VALUES (?, ?, ?, 1)'
			. ' ON DUPLICATE KEY UPDATE due = GREATEST(due, VALUES(due)),'
			. ' reviewed = (SELECT COUNT(DISTINCT r.tsumego_id) FROM mistake_training_review r WHERE r.user_id = ? AND r.day = ?)',
			[$userId, $today, $inTraining, $userId, $today]
		);

		$cleared = false;
		$clearBonus = false;
		if ($result !== self::REVIEW_MISSED && self::dueCount($userId) == 0)
			$cleared = Util::execute(
				'UPDATE mistake_training_day SET cleared = 1, cleared_at = ? WHERE user_id = ? AND day = ? AND cleared = 0',
				[$now, $userId, $today]
			) > 0;
		if ($cleared)
			$clearBonus = (int) Util::query(
				'SELECT due FROM mistake_training_day WHERE user_id = ? AND day = ?',
				[$userId, $today]
			)[0]['due'] >= self::CLEAR_DAY_MIN_PROBLEMS;

		return [
			'id' => $reviewId,
			'result' => $result,
			'rung_before' => $rungBefore,
			'rung_after' => $rungAfter,
			'climbed' => $climbed,
			'cleared' => $cleared,
			'clear_bonus' => $clearBonus,
		];
	}

	/**
	 * Write down the days a review waited for since it was due at $dueSince: each day
	 * before today gets a row in mistake_training_day that is not cleared, which ends
	 * a streak (see profile()). A day that has its row already keeps it, so a day that
	 * was cleared before an import made more problems due stays cleared.
	 */
	private static function recordMissedDays(int $userId, string $dueSince): void
	{
		$days = [];
		for ($day = substr($dueSince, 0, 10); $day < self::today(); $day = date('Y-m-d', strtotime($day . ' +1 day')))
			$days[] = $day;
		if (empty($days))
			return;
		Util::execute(
			'INSERT INTO mistake_training_day (user_id, day) VALUES ' . implode(', ', array_fill(0, count($days), '(?, ?)'))
			. ' ON DUPLICATE KEY UPDATE user_id = user_id',
			array_merge(...array_map(fn(string $day) => [$userId, $day], $days))
		);
	}

	/**
	 * The XP a review of the training earns, written into its history row and the
	 * day's sum: see REVIEW_XP_SHARE. $problemRating is the rating of the problem
	 * before this play changed it.
	 *
	 * @param array{id: int, result: string, climbed: bool, clear_bonus: bool} $review what recordResult() returned
	 * @return array{review: int, graduation: int, clear: int}
	 */
	public static function awardXp(int $userId, array $review, float $problemRating): array
	{
		$xp = ['review' => 0, 'graduation' => 0, 'clear' => 0];
		if ($review['climbed'])
			$xp['review'] = max(1, Rating::ratingToXP($problemRating, self::REVIEW_XP_SHARE));
		if ($review['result'] === self::REVIEW_GRADUATED)
			$xp['graduation'] = max(1, Rating::ratingToXP($problemRating, self::GRADUATION_XP_SHARE));

		$today = self::today();
		$earned = $xp['review'] + $xp['graduation'];
		if ($earned > 0)
			Util::execute('UPDATE mistake_training_review SET xp = ? WHERE id = ?', [$earned, $review['id']]);
		if ($review['clear_bonus'])
		{
			$dayXp = (int) Util::query(
				'SELECT COALESCE(SUM(xp), 0) AS total FROM mistake_training_review WHERE user_id = ? AND day = ?',
				[$userId, $today]
			)[0]['total'];
			$xp['clear'] = (int) ceil($dayXp * self::CLEAR_DAY_XP_SHARE);
		}
		$total = array_sum($xp);
		if ($total > 0)
			Util::execute('UPDATE mistake_training_day SET xp = xp + ? WHERE user_id = ? AND day = ?', [$total, $userId, $today]);
		return $xp;
	}

	/**
	 * How many problems today's training holds: due now or solved today, of the ones
	 * it can serve, like todaysTraining() without fetching them.
	 */
	public static function countTodaysTraining(int $userId): int
	{
		$rows = Util::query(
			'SELECT COUNT(*) AS total FROM mistake_training_pool p' . self::servableSql()
			. ' AND ((p.next_due IS NOT NULL AND p.next_due <= ?)'
			. " OR (p.last_review_day = ? AND p.last_review_result IN ('" . self::REVIEW_SOLVED . "', '" . self::REVIEW_GRADUATED . "')))",
			[$userId, date('Y-m-d H:i:s'), self::today()]
		);
		return (int) $rows[0]['total'];
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
	 * Whether a problem is in the pool and scheduled for review right now. This is the
	 * schedule only: what the training can open is isInTodaysTraining(), which also
	 * holds the problems solved today.
	 */
	public static function isDue(int $userId, int $tsumegoId): bool
	{
		$row = self::getPoolRow($userId, $tsumegoId);
		return $row !== null && self::isRowDue($row);
	}

	/**
	 * Whether a pool row is scheduled for review right now. A graduated problem has
	 * no next review, and null would otherwise read as older than today.
	 */
	public static function isRowDue(array $row): bool
	{
		return $row['next_due'] !== null && $row['next_due'] <= date('Y-m-d H:i:s');
	}

	/**
	 * Whether a pool row was solved, or graduated, on the current training day, which
	 * keeps it in today's list as done.
	 */
	public static function wasSolvedToday(array $row): bool
	{
		return ($row['last_review_day'] ?? null) === self::today()
			&& in_array($row['last_review_result'] ?? null, [self::REVIEW_SOLVED, self::REVIEW_GRADUATED], true);
	}

	/**
	 * Whether a pool row was missed on the current training day and not solved since.
	 */
	public static function wasMissedToday(array $row): bool
	{
		return ($row['last_review_day'] ?? null) === self::today() && ($row['last_review_result'] ?? null) === self::REVIEW_MISSED;
	}

	/**
	 * Whether a problem belongs to today's training: due now, or solved today.
	 */
	public static function isInTodaysTraining(array $row): bool
	{
		return self::isRowDue($row) || self::wasSolvedToday($row);
	}

	/**
	 * Number of tsumegos currently due for review. Counts only what the training can
	 * serve, like todaysTraining(): a problem that is deleted or in no set is left out.
	 */
	public static function dueCount(int $userId): int
	{
		$rows = Util::query(
			'SELECT COUNT(*) AS total FROM mistake_training_pool p' . self::servableSql()
			. ' AND p.next_due IS NOT NULL AND p.next_due <= ?',
			[$userId, date('Y-m-d H:i:s')]
		);
		return (int) $rows[0]['total'];
	}

	/**
	 * The join and conditions that keep the pool rows of player ? the training can
	 * serve: the problem is not deleted and is in a set.
	 */
	private static function servableSql(): string
	{
		return ' JOIN tsumego t ON t.id = p.tsumego_id AND t.deleted IS NULL'
			. ' WHERE p.user_id = ?'
			. ' AND EXISTS (SELECT 1 FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id)';
	}

	/**
	 * The next pool row due for review, or null if none: the first due problem in
	 * today's order, which is the order the problems came into the pool. The due date
	 * does not order it, because an import brings many problems due at the same
	 * moment. A problem missed earlier today waits until the untried ones are done,
	 * so one hard problem does not hold up the day. A problem the training cannot
	 * serve (deleted, or in no set) is skipped, so it keeps its row for when it is back.
	 *
	 * @return array|null
	 */
	public static function nextDue(int $userId): ?array
	{
		$rows = Util::query(
			'SELECT p.* FROM mistake_training_pool p' . self::servableSql()
			. ' AND p.next_due IS NOT NULL AND p.next_due <= ?'
			. ' ORDER BY (p.last_review_day IS NOT NULL AND p.last_review_day = ?) ASC, p.id ASC LIMIT 1',
			[$userId, date('Y-m-d H:i:s'), self::today()]
		);
		return $rows[0] ?? null;
	}

	/**
	 * Today's training of a player, one row per problem: the problems due now and the
	 * ones solved today, in the order they came into the pool. $preferredSetConnectionID
	 * picks the set connection of a problem that is in several sets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function todaysTraining(int $userId, int $preferredSetConnectionID = 0): array
	{
		return Util::query(self::queueSql(), [$preferredSetConnectionID, $userId, date('Y-m-d H:i:s'), self::today()]);
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
	 * Whether the player ever had a problem in the training, graduated ones included,
	 * which tells an empty training from a finished one.
	 */
	public static function hasEverTrained(int $userId): bool
	{
		return !empty(Util::query('SELECT 1 FROM mistake_training_pool WHERE user_id = ? LIMIT 1', [$userId]));
	}

	/**
	 * The first day of the calendar of the profile: the Monday of the week PROFILE_DAYS
	 * ago, so the calendar starts with a whole week.
	 */
	public static function profileStart(): string
	{
		$first = strtotime(self::today() . ' -' . (self::PROFILE_DAYS - 1) . ' days');
		return date('Y-m-d', strtotime('-' . (((int) date('N', $first)) - 1) . ' days', $first));
	}

	/**
	 * What the profile shows of the training of a player, or null when there is
	 * nothing to show: the training days since $since (reviewed problems, whether the
	 * day was cleared, and whether it was missed), the problems in the training now by
	 * rung, the graduated ones, and the streaks of cleared days.
	 *
	 * A cleared day adds to a streak, and a missed day ends it: one that had problems
	 * due and was not cleared, whether they were tried or not. A day with nothing due
	 * neither ends a streak nor adds to it, so a player who has nothing to review does
	 * not lose the streak, and does not grow it either. Today is missed only once it
	 * is over, so the current streak holds while today's training is still to do.
	 *
	 * @return array{days: array<string, array{reviewed: int, cleared: bool, missed: bool}>, rungs: list<int>, graduated: int, streak: int, best_streak: int}|null
	 */
	public static function profile(int $userId, string $since): ?array
	{
		$rungs = array_fill(0, count(self::$LADDER), 0);
		$graduated = 0;
		$rows = Util::query(
			'SELECT p.rung, p.next_due IS NULL AS graduated, COUNT(*) AS total FROM mistake_training_pool p' . self::servableSql()
			. ' GROUP BY p.rung, p.next_due IS NULL',
			[$userId]
		);
		foreach ($rows as $row)
			if ($row['graduated'])
				$graduated += (int) $row['total'];
			else
				$rungs[min((int) $row['rung'], count(self::$LADDER) - 1)] += (int) $row['total'];

		$today = self::today();
		$history = [];
		foreach (Util::query('SELECT day, reviewed, cleared FROM mistake_training_day WHERE user_id = ? ORDER BY day', [$userId]) as $row)
			$history[$row['day']] = [
				'reviewed' => (int) $row['reviewed'],
				'cleared' => (bool) $row['cleared'],
				'missed' => !$row['cleared'] && $row['day'] < $today,
			];

		// The days a problem that is still due has been waiting for are missed too;
		// recordMissedDays() writes them down only once it is reviewed.
		$overdueSince = Util::query(
			'SELECT MIN(p.next_due) AS since FROM mistake_training_pool p' . self::servableSql()
			. ' AND p.next_due IS NOT NULL AND p.next_due < ?',
			[$userId, $today . ' 00:00:00']
		)[0]['since'] ?? null;
		if ($overdueSince !== null)
			for ($day = substr($overdueSince, 0, 10); $day < $today; $day = date('Y-m-d', strtotime($day . ' +1 day')))
				$history[$day] ??= ['reviewed' => 0, 'cleared' => false, 'missed' => true];
		ksort($history);

		$days = array_filter($history, fn(string $day) => $day >= $since, ARRAY_FILTER_USE_KEY);
		if (empty($days) && !self::hasEverTrained($userId))
			return null;

		$streak = 0;
		$bestStreak = 0;
		foreach ($history as $day)
			if ($day['cleared'])
				$bestStreak = max($bestStreak, ++$streak);
			elseif ($day['missed'])
				$streak = 0;

		return ['days' => $days, 'rungs' => $rungs, 'graduated' => $graduated, 'streak' => $streak, 'best_streak' => $bestStreak];
	}

	/**
	 * How many past mistakes are not in the training yet. An attempt that was solved
	 * with misplays is a problem the player solved but not on the first try; one that
	 * was never solved is practice rather than review, so it does not count. The page
	 * asks on every visit that finds nothing due, and the history of a player can be
	 * long, so the count stops at IMPORT_COUNT_CAP.
	 */
	public static function importCandidates(int $userId): int
	{
		$rows = Util::query(
			'SELECT COUNT(*) AS total FROM (SELECT DISTINCT a.tsumego_id' . self::pastMistakesSql()
			. ' LIMIT ' . self::IMPORT_COUNT_CAP . ') candidates',
			[$userId]
		);
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
	 * These are the waits of a problem that never lapsed, see wait().
	 *
	 * @return int[]
	 */
	public static function ladder(): array
	{
		return self::$LADDER;
	}

	/**
	 * Days until the next review of a problem that climbed to $rung after $lapses
	 * lapses: the wait of the ladder shortened by LAPSE_SLOWDOWN for every lapse,
	 * never below MIN_GROWTH of it, and never below a day. The rung does not depend
	 * on the lapses, so the badges and graduation are the same for every problem; a
	 * hard one only waits less between its reviews.
	 */
	public static function wait(int $rung, int $lapses = 0): int
	{
		$growth = max(self::MIN_GROWTH, self::LAPSE_SLOWDOWN ** $lapses);
		return max(1, (int) round(self::$LADDER[$rung] * $growth));
	}

	/**
	 * The rung as a player reads it, counted from one, for the tooltip and the
	 * accessible name of the badge.
	 */
	public static function rungName(int $rung): string
	{
		return 'Rung ' . ($rung + 1) . ' of ' . count(self::$LADDER);
	}

	/**
	 * The pool of one player, soonest review first, for the admin toolbox.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function poolRows(int $userId): array
	{
		return Util::query(
			'SELECT p.tsumego_id, p.rung, p.lapses, p.next_due, p.last_review_day, p.last_review_result,'
			. ' (SELECT sc.id FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id ORDER BY sc.id LIMIT 1) AS set_connection_id'
			. ' FROM mistake_training_pool p WHERE p.user_id = ?'
			. ' ORDER BY p.next_due ASC, p.id ASC LIMIT 50',
			[$userId]
		);
	}

	/**
	 * Move every review of a player by $days days, so a three week wait becomes a
	 * click. The day of the last review moves with it, so a day back also closes
	 * today's training: what was solved today belongs to yesterday. Only the dates
	 * move: the rung says how well the problem is known.
	 *
	 * @return int How many rows moved.
	 */
	public static function shiftReviews(int $userId, int $days): int
	{
		// The history moves along, so the days trained so far stay where they were
		// against the reviews; a day key is unique, so the rows move in the order that
		// never lands one on another.
		$order = $days < 0 ? 'ASC' : 'DESC';
		Util::execute('UPDATE mistake_training_day SET day = DATE_ADD(day, INTERVAL ? DAY) WHERE user_id = ? ORDER BY day ' . $order, [$days, $userId]);
		Util::execute('UPDATE mistake_training_review SET day = DATE_ADD(day, INTERVAL ? DAY) WHERE user_id = ?', [$days, $userId]);
		return Util::execute(
			'UPDATE mistake_training_pool SET next_due = DATE_ADD(next_due, INTERVAL ? DAY),'
			. ' last_review_day = DATE_ADD(last_review_day, INTERVAL ? DAY) WHERE user_id = ?',
			[$days, $days, $userId]
		);
	}

	/**
	 * Put one problem on a rung and make it due now, which is how the toolbox jumps
	 * to a late review instead of playing the ladder up to it. The lapses stay: the
	 * jump is a shortcut through time, not a new history of the problem.
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
	 * without climbing the whole ladder. The lapses stay, like in moveToRung().
	 */
	public static function graduateNow(int $userId, int $tsumegoId): void
	{
		self::setRung($userId, $tsumegoId, count(self::$LADDER) - 1, 0);
		self::graduate($userId, $tsumegoId);
	}

	/**
	 * Add one problem to the pool as due now, whether or not it was a mistake. For
	 * the toolbox, and the import of past mistakes. A new problem has no lapses, the
	 * mistake that brought it in is what the training is for; one already in the
	 * pool keeps its rung, its review and its lapses.
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
	 * connection. Graduation keeps the row, see graduate().
	 */
	public static function removeFromPool(int $userId, int $tsumegoId): void
	{
		ClassRegistry::init('MistakeTrainingPool')->deleteAll(['user_id' => $userId, 'tsumego_id' => $tsumegoId]);
	}

	/**
	 * Build the navigation buttons for today's training. One button per tsumego,
	 * preferring the set connection the user is on, in today's order, so the row and
	 * the numbering hold still while the day is played. The colour says what today
	 * did and never the tsumego status: grey is still due, green is solved today.
	 * Links stay on the training route, which owns the mode.
	 */
	public static function buildQueueButtons(int $currentSetConnectionID): TsumegoButtons
	{
		$rows = self::todaysTraining((int) Auth::getUserID(), $currentSetConnectionID);
		foreach ($rows as $index => $row)
			$rows[$index]['status'] = self::buttonStatus($row);

		$buttons = TsumegoButtons::fromRows($rows, $currentSetConnectionID, 200, self::$PLAY_LINK_PREFIX);
		$rowsByTsumego = array_column($rows, null, 'tsumego_id');
		foreach ($buttons as $button)
			self::describeButton($button, $rowsByTsumego[$button->tsumegoID]);
		return $buttons;
	}

	/**
	 * Where "Next" leads in the training: the next problem of today's list that is
	 * still due, after the current one and around to the start, so a problem solved
	 * today is not served again. With nothing else due it is the queue page, which
	 * says the day is done, once the current problem is solved; until then there is
	 * nowhere to go (an empty link), since the queue page would serve it again. The
	 * play page turns Next on when the solve comes in.
	 */
	public static function nextLink(TsumegoButtons $buttons, int $currentSetConnectionID): string
	{
		$list = (array) $buttons;
		$currentIndex = array_find_key($list, fn(TsumegoButton $button) => $button->setConnectionID === $currentSetConnectionID);
		$others = $currentIndex === null
			? $list
			: array_merge(array_slice($list, $currentIndex + 1), array_slice($list, 0, $currentIndex));

		// A grey button is a problem that is still due.
		$next = array_find($others, fn(TsumegoButton $button) => $button->status === 'N');
		if ($next)
			return self::$PLAY_LINK_PREFIX . $next->setConnectionID;
		$currentIsDue = $currentIndex !== null && $list[$currentIndex]->status === 'N';
		return $currentIsDue ? '' : self::$QUEUE_LINK;
	}

	/**
	 * What the training button of a problem shows now, for the play page to redraw the
	 * button of the open problem once its result is stored, instead of keeping what
	 * the page was loaded with. Null when the problem is not in today's training.
	 *
	 * @return array{status: string, label: string, description: string, badge: string}|null
	 */
	public static function describeTodaysReview(int $userId, int $tsumegoId): ?array
	{
		$row = self::getPoolRow($userId, $tsumegoId);
		if ($row === null || !self::isInTodaysTraining($row))
			return null;

		$button = new TsumegoButton($tsumegoId, 0, 0, self::buttonStatus($row), null, '');
		self::describeButton($button, $row);
		return [
			'status' => $button->status,
			'label' => (string) $button->label,
			'description' => (string) $button->description,
			'badge' => $button->renderRungBadge(),
		];
	}

	/**
	 * The colour of a training button: grey while the problem is due, green once
	 * today solved it.
	 */
	private static function buttonStatus(array $row): string
	{
		return self::isRowDue($row) ? 'N' : 'S';
	}

	/**
	 * Put the ladder on a training button: the rung badge, and a tooltip that says
	 * what the colour means today instead of naming a tsumego status.
	 */
	private static function describeButton(TsumegoButton $button, array $row): void
	{
		$rung = (int) $row['rung'];
		$rungText = self::rungName($rung);
		$button->rung = $rung;

		if (self::isRowDue($row))
		{
			if (self::wasMissedToday($row))
			{
				$button->missedToday = true;
				$button->label = 'Missed today, try again';
				$button->description = $rungText . '. Solve it today and it comes back tomorrow.';
			}
			else
			{
				$button->label = 'Due today';
				$button->description = $rungText . '. ' . self::cleanSolveOutlook($rung, (int) $row['lapses']);
			}
			return;
		}

		// Not due, so solved today. The next review tells a graduation from a solve
		// rather than the stored result, because a play outside the training can still
		// move the problem afterwards: a clean solve there graduates it, a mistake puts
		// it back on the ladder.
		if ($row['next_due'] === null)
		{
			$button->graduated = true;
			$button->label = 'Graduated today';
			$button->description = 'Solved cleanly on every rung, it has no more reviews.';
			return;
		}
		$button->label = 'Solved today';
		$button->description = $rungText . ', next review ' . self::describeDay(date('Y-m-d', strtotime($row['next_due']))) . '.';
	}

	/**
	 * What a clean solve of a due problem would do, for its tooltip: graduate it at
	 * the top rung, or bring it back after the wait of the next rung, which the lapses
	 * of the problem shorten, down to a day for a problem that lapsed often.
	 */
	private static function cleanSolveOutlook(int $rung, int $lapses): string
	{
		if ($rung >= count(self::$LADDER) - 1)
			return 'A clean solve graduates it.';
		$days = self::wait($rung + 1, $lapses);
		return 'A clean solve brings it back ' . ($days == 1 ? 'tomorrow' : 'in ' . $days . ' days') . '.';
	}

	/**
	 * Add a problem to the pool at the bottom rung, due on the next training day.
	 * Entering is not a lapse: the mistake that brought the problem in is the reason
	 * it is reviewed, not a sign that the reviews are too far apart.
	 */
	private static function enterPool(int $userId, int $tsumegoId): void
	{
		// Two results of the same play can arrive together, so the row may be there already
		$now = date('Y-m-d H:i:s');
		Util::execute(
			'INSERT INTO mistake_training_pool (user_id, tsumego_id, rung, next_due, created, modified)'
			. ' VALUES (?, ?, 0, ?, ?, ?)'
			. ' ON DUPLICATE KEY UPDATE user_id = user_id',
			[$userId, $tsumegoId, self::dueDate(self::$LADDER[0]), $now, $now]
		);
	}

	/**
	 * The current training day: the day of the daily cron (CronController::daily),
	 * in the timezone of the server, so the training turns to a new day together
	 * with the hearts and the rest of the site.
	 */
	public static function today(): string
	{
		return date('Y-m-d');
	}

	/**
	 * The moment a review scheduled $daysAhead days out becomes due: the start of
	 * that day, never "now plus the interval".
	 */
	private static function dueDate(int $daysAhead): string
	{
		return date('Y-m-d', strtotime(self::today() . ' +' . $daysAhead . ' days')) . ' 00:00:00';
	}

	/**
	 * Remember what today's review did with a problem that was due, which decides its
	 * place in today's list. A miss also takes the problem to the bottom ($rung)
	 * without moving its review, because it stays due until it is solved; a solve has
	 * moved the ladder already.
	 */
	private static function markReview(int $userId, int $tsumegoId, string $result, ?int $rung = null): void
	{
		$sql = 'UPDATE mistake_training_pool SET last_review_day = ?, last_review_result = ?, modified = ?';
		$params = [self::today(), $result, date('Y-m-d H:i:s')];
		if ($rung !== null)
		{
			$sql .= ', rung = ?';
			$params[] = $rung;
		}
		$sql .= ' WHERE user_id = ? AND tsumego_id = ?';
		$params[] = $userId;
		$params[] = $tsumegoId;

		Util::execute($sql, $params);
	}

	/**
	 * Count a lapse of a problem in the pool: a mistake on it, which slows the growth
	 * of its waits for good. The count never goes down, a problem that was hard stays
	 * suspect however well it goes later.
	 */
	private static function countLapse(int $userId, int $tsumegoId): void
	{
		Util::execute(
			'UPDATE mistake_training_pool SET lapses = lapses + 1 WHERE user_id = ? AND tsumego_id = ?',
			[$userId, $tsumegoId]
		);
	}

	/**
	 * Move a pool row to a new rung and set its next review. The review waits out
	 * the rung's interval, shortened by the lapses of the problem (see wait()), unless
	 * $daysAhead names the wait explicitly (a retry is due tomorrow whatever rung the
	 * problem sits on, and the toolbox makes a jump due now). $lapses saves reading
	 * them again when the caller has the row.
	 */
	private static function setRung(int $userId, int $tsumegoId, int $rung, ?int $daysAhead = null, ?int $lapses = null): void
	{
		$now = date('Y-m-d H:i:s');
		$daysAhead ??= self::wait($rung, $lapses ?? (int) (self::getPoolRow($userId, $tsumegoId)['lapses'] ?? 0));
		$nextDue = self::dueDate($daysAhead);

		$sql = 'UPDATE mistake_training_pool SET rung = ?, next_due = ?, modified = ?';
		$params = [$rung, $nextDue, $now];
		$sql .= ' WHERE user_id = ? AND tsumego_id = ?';
		$params[] = $userId;
		$params[] = $tsumegoId;

		Util::execute($sql, $params);
	}

	/**
	 * A clean solve at the top rung ends training, wherever it was played, and so does
	 * the toolbox's graduateNow(); every graduation goes through here. The problem
	 * stops being scheduled and its status is left alone, it belongs to the normal
	 * modes. The row stays with no next review, because it remembers that the training
	 * already knows the problem; without it a mastered problem would come back as a
	 * past mistake to import.
	 */
	private static function graduate(int $userId, int $tsumegoId): void
	{
		Util::execute(
			'UPDATE mistake_training_pool SET next_due = NULL, modified = ? WHERE user_id = ? AND tsumego_id = ?',
			[date('Y-m-d H:i:s'), $userId, $tsumegoId]
		);
	}

	/**
	 * Today's training: pool rows that are due now or were solved today. Deduplicates
	 * in SQL (one row per tsumego via ROW_NUMBER), preferring the current set
	 * connection so navigation stays on the connection the user is on, then the set
	 * display order every other screen uses. Ordered by the pool row, which is the
	 * order the problems came in: a list sorted by due date would reshuffle whenever
	 * a review moves a problem, and an import brings many problems due at the same
	 * moment. The tsumego status is not read, the training row does not show it.
	 */
	private static function queueSql(): string
	{
		$displayOrder = SetConnection::displayOrderForSetSql('st');

		return "
			SELECT id, tsumego_id, set_connection_id, num, rating, sgf, rung, lapses, next_due, last_review_day, last_review_result
			FROM (
				SELECT
					p.id,
					p.tsumego_id,
					sc.id AS set_connection_id,
					sc.num,
					t.rating,
					p.rung,
					p.lapses,
					p.next_due,
					p.last_review_day,
					p.last_review_result,
					COALESCE(sgf.sgf, '') AS sgf,
					ROW_NUMBER() OVER (
						PARTITION BY p.tsumego_id
						ORDER BY CASE WHEN sc.id = ? THEN 0 ELSE 1 END, $displayOrder, sc.id
					) AS rn
				FROM mistake_training_pool p
				JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id
				JOIN `set` st ON st.id = sc.set_id
				JOIN tsumego t ON t.id = p.tsumego_id
				LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = p.tsumego_id)
				WHERE p.user_id = ?
				  AND t.deleted IS NULL
				  AND (
					(p.next_due IS NOT NULL AND p.next_due <= ?)
					OR (p.last_review_day = ? AND p.last_review_result IN ('" . self::REVIEW_SOLVED . "', '" . self::REVIEW_GRADUATED . "'))
				  )
			) x
			WHERE rn = 1
			ORDER BY id ASC
		";
	}
}
