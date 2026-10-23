<?php

use App\Utility\TsumegoUtil;
use App\Utility\Util;

/**
 * Problems a teacher gave a class to solve by a day: a collection, or the problems of it numbered
 * from first_number to last_number. The collection is the teacher's own or one of the site, so the
 * problems are picked with the tools the collections already have.
 *
 * What counts as done for the class is a solve after the problem was assigned. A problem a pupil
 * solved before is shown apart, so the teacher sees it was not played again for the homework.
 */
class ClassroomAssignment extends AppModel
{
	public $useTable = 'classroom_assignment';

	public const CELL_SOLVED = 'solved';
	public const CELL_LATE = 'late';
	public const CELL_EARLIER = 'earlier';
	public const CELL_TRIED = 'tried';

	/**
	 * The assignments of a class with the title of their collection, the latest due date first.
	 *
	 * @return array<array<string, mixed>>
	 */
	public static function ofClassroom(int $classroomId): array
	{
		return Util::query('SELECT a.*, s.title AS set_title
			FROM classroom_assignment a
			JOIN `set` s ON s.id = a.set_id
			WHERE a.classroom_id = ?
			ORDER BY a.due DESC, a.id DESC',
			[$classroomId]);
	}

	/**
	 * The collections a teacher can give to a class: their own ones, then the ones of the site.
	 *
	 * @return array{own: array<array{id: int, title: string}>, site: array<array{id: int, title: string}>}
	 */
	public static function assignableSets(int $teacherId): array
	{
		$rows = Util::query('SELECT s.id, s.title, s.user_id FROM `set` s
			WHERE s.user_id = ? OR (' . Set::officialCondition('s') . ')
			ORDER BY s.user_id IS NULL, s.title, s.id',
			[$teacherId]);
		$sets = ['own' => [], 'site' => []];
		foreach ($rows as $row)
			$sets[$row['user_id'] === null ? 'site' : 'own'][] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
		return $sets;
	}

	/**
	 * Whether a teacher can give the collection to a class: it is theirs or the site's.
	 *
	 * @param array<string, mixed> $set Unwrapped set row
	 */
	public static function canAssignSet(array $set, int $teacherId): bool
	{
		return Set::isOfficial($set) || (int) ($set['user_id'] ?? 0) === $teacherId;
	}

	/**
	 * Whether the collection is given to a class the player is in. Such a player can open it even
	 * when the teacher keeps it private.
	 */
	public static function isSetAssignedTo(int $userId, int $setId): bool
	{
		$rows = Util::query('SELECT 1 FROM classroom_assignment a
			JOIN classroom_member m ON m.classroom_id = a.classroom_id AND m.user_id = ? AND m.classroom_member_status_id = ?
			WHERE a.set_id = ?
			LIMIT 1',
			[$userId, ClassroomMemberStatus::ACCEPTED, $setId]);
		return !empty($rows);
	}

	/**
	 * The SQL condition limiting the problems of the collection to the assigned numbers, on the
	 * set_connection alias `sc`, with its parameters appended to $parameters.
	 *
	 * @param array<string, mixed> $assignment
	 * @param array<mixed> $parameters
	 */
	private static function problemCondition(array $assignment, array &$parameters): string
	{
		$condition = 'sc.set_id = ?';
		$parameters[] = (int) $assignment['set_id'];
		if ($assignment['first_number'] !== null)
		{
			$condition .= ' AND sc.num >= ?';
			$parameters[] = (int) $assignment['first_number'];
		}
		if ($assignment['last_number'] !== null)
		{
			$condition .= ' AND sc.num <= ?';
			$parameters[] = (int) $assignment['last_number'];
		}
		return $condition;
	}

	/**
	 * The assigned problems in the order of the collection.
	 *
	 * @param array<string, mixed> $assignment
	 * @return array<array{set_connection_id: int, tsumego_id: int, number: int}>
	 */
	public static function problems(array $assignment): array
	{
		$parameters = [];
		$condition = self::problemCondition($assignment, $parameters);
		$rows = Util::query('SELECT sc.id, sc.tsumego_id, sc.num FROM set_connection sc
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			WHERE ' . $condition . '
			ORDER BY sc.num, sc.id',
			$parameters);
		return array_map(fn(array $row) => [
			'set_connection_id' => (int) $row['id'],
			'tsumego_id' => (int) $row['tsumego_id'],
			'number' => (int) $row['num'],
		], $rows);
	}

	/**
	 * The moment from which a solve is late: the end of the due day.
	 *
	 * @param array<string, mixed> $assignment
	 */
	public static function lateFrom(array $assignment): string
	{
		return date('Y-m-d H:i:s', strtotime($assignment['due'] . ' +1 day'));
	}

	/**
	 * Who of the class solved which assigned problem: one row per pupil, one cell per problem.
	 * A cell is null for a problem not touched since it was assigned, or says it was solved (late
	 * when after the due day), solved only before it was assigned, or tried without a solve yet.
	 *
	 * @param array<string, mixed> $assignment
	 * @param int|null $pupilId Only this pupil, for the pupil looking at their own homework
	 * @return array{problems: array<array{setConnectionId: int, number: int}>, pupils: array<array<string, mixed>>}
	 */
	public static function progress(array $assignment, ?int $pupilId = null): array
	{
		$problems = self::problems($assignment);
		$pupils = ClassroomMember::ofClassroom((int) $assignment['classroom_id'])[ClassroomMemberStatus::ACCEPTED];
		if ($pupilId !== null)
			$pupils = array_values(array_filter($pupils, fn(array $pupil) => (int) $pupil['user_id'] === $pupilId));

		$parameters = [$assignment['created']];
		$condition = self::problemCondition($assignment, $parameters);
		$parameters[] = (int) $assignment['classroom_id'];
		$parameters[] = ClassroomMemberStatus::ACCEPTED;
		$attempts = Util::query('SELECT ta.user_id, ta.tsumego_id,
				MIN(CASE WHEN ta.solved = 1 THEN ta.created END) AS solved_at,
				SUM(ta.misplays) AS misplays
			FROM classroom_member m
			JOIN tsumego_attempt ta ON ta.user_id = m.user_id AND ta.created >= ?
			JOIN set_connection sc ON sc.tsumego_id = ta.tsumego_id AND ' . $condition . '
			WHERE m.classroom_id = ? AND m.classroom_member_status_id = ?
			GROUP BY ta.user_id, ta.tsumego_id',
			$parameters);

		$parameters = [];
		$condition = self::problemCondition($assignment, $parameters);
		$parameters[] = (int) $assignment['classroom_id'];
		$parameters[] = ClassroomMemberStatus::ACCEPTED;
		$statuses = Util::query('SELECT ts.user_id, ts.tsumego_id, ts.status
			FROM classroom_member m
			JOIN tsumego_status ts ON ts.user_id = m.user_id
			JOIN set_connection sc ON sc.tsumego_id = ts.tsumego_id AND ' . $condition . '
			WHERE m.classroom_id = ? AND m.classroom_member_status_id = ?',
			$parameters);

		$lateFrom = self::lateFrom($assignment);
		$cells = [];
		foreach ($statuses as $status)
			if (TsumegoUtil::isSolvedStatus($status['status']))
				$cells[$status['user_id']][$status['tsumego_id']] = ['state' => self::CELL_EARLIER];
		foreach ($attempts as $attempt)
		{
			$misplays = (int) $attempt['misplays'];
			if ($attempt['solved_at'] !== null)
				$cells[$attempt['user_id']][$attempt['tsumego_id']] = [
					'state' => $attempt['solved_at'] >= $lateFrom ? self::CELL_LATE : self::CELL_SOLVED,
					'solvedAt' => Util::toIso8601($attempt['solved_at']),
					'misplays' => $misplays,
				];
			else
				$cells[$attempt['user_id']][$attempt['tsumego_id']] = ['state' => self::CELL_TRIED, 'misplays' => $misplays];
		}

		$pupilRows = [];
		foreach ($pupils as $pupil)
		{
			$userId = (int) $pupil['user_id'];
			$row = [];
			$solvedCount = 0;
			foreach ($problems as $problem)
			{
				$cell = $cells[$userId][$problem['tsumego_id']] ?? null;
				if ($cell !== null && ($cell['state'] === self::CELL_SOLVED || $cell['state'] === self::CELL_LATE))
					$solvedCount++;
				$row[] = $cell;
			}
			$pupilRows[] = [
				'userId' => $userId,
				'name' => User::displayNameOrNull(['id' => $userId, 'display_name' => $pupil['user_display_name']]),
				'avatarUrl' => User::avatarOrNull([
					'id' => $userId,
					'display_name' => $pupil['user_display_name'],
					'external_id' => $pupil['user_external_id'],
					'picture' => $pupil['user_picture'],
					'email' => $pupil['user_email'],
					'pref_avatar' => $pupil['user_pref_avatar'],
				]),
				'rating' => (float) $pupil['user_rating'],
				'solvedCount' => $solvedCount,
				'cells' => $row,
			];
		}

		return [
			'problems' => array_map(fn(array $problem) => [
				'setConnectionId' => $problem['set_connection_id'],
				'number' => $problem['number'],
			], $problems),
			'pupils' => $pupilRows,
		];
	}
}
