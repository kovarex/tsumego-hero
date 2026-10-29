<?php

namespace App\Model\Table;

use App\Model\Entity\Set;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoUtil;
use App\Utility\Util;
use Throwable;
use User;

//Problems a teacher gave a class to solve by a day. The teacher builds the assignment up as a
//draft, picking problems from their own collections and the ones of the site, and the pupils get
//it once it is published. The picked problems are rows of classroom_assignment_problem, each a
//problem of a collection (a set_connection), so it keeps the number it has there.
//What counts as done for the class is a solve for the assignment, a row of
//classroom_assignment_result (recordResult()). A problem a pupil solved before is shown apart, so
//the teacher sees it was not played again for the homework.
class ClassroomAssignmentTable extends AppTable
{
	public const MAXIMUM_TITLE_LENGTH = 100;

	//More problems than a class plays in a week, and the table of who solved what stays readable.
	public const MAXIMUM_PROBLEMS = 300;

	public const CELL_SOLVED = 'solved';
	public const CELL_LATE = 'late';
	public const CELL_EARLIER = 'earlier';
	public const CELL_TRIED = 'tried';

	//The colours of the buttons of playButtons(), as the statuses of the problem buttons.
	public const BUTTON_SOLVED = 'S';
	public const BUTTON_SOLVED_BEFORE = 'W';
	public const BUTTON_NOT_SOLVED = 'N';

	public static function isPublished(array $assignment): bool
	{
		return $assignment['published'] !== null;
	}

	//The assignments of a class, the drafts first and then the latest due date first, each with
	//the collections its problems come from under `collections`. The pupils don't see the drafts.
	/** @return list<array<string, mixed>> */
	public static function ofClassroom(int $classroomId, bool $withDrafts): array
	{
		$assignments = Util::query('SELECT a.* FROM classroom_assignment a
			WHERE a.classroom_id = ?' . ($withDrafts ? '' : ' AND a.published IS NOT NULL') . '
			ORDER BY a.published IS NULL DESC, a.due DESC, a.id DESC',
			[$classroomId]);
		return self::withCollections($assignments);
	}

	//The assignments, each with the collections its problems come from under `collections`, in
	//the order of their first problem, for the name of an assignment without one (title()).
	/**
	 * @param list<array<string, mixed>> $assignments
	 * @return list<array<string, mixed>>
	 */
	public static function withCollections(array $assignments): array
	{
		if (empty($assignments))
			return [];

		$ids = array_map(fn(array $assignment) => (int) $assignment['id'], $assignments);
		$collections = [];
		foreach (Util::query('SELECT p.classroom_assignment_id, s.id, s.title, COUNT(*) AS problem_count, MIN(p.position) AS first_position
			FROM classroom_assignment_problem p
			' . self::visibleProblemJoins() . '
			WHERE p.classroom_assignment_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
			GROUP BY p.classroom_assignment_id, s.id, s.title
			ORDER BY first_position, s.id', $ids) as $row)
			$collections[$row['classroom_assignment_id']][] = [
				'id' => (int) $row['id'],
				'title' => (string) $row['title'],
				'problemCount' => (int) $row['problem_count'],
			];

		foreach ($assignments as &$assignment)
			$assignment['collections'] = $collections[$assignment['id']] ?? [];
		return $assignments;
	}

	//The joins from classroom_assignment_problem `p` to the problem `sc` of the collection `s`,
	//which leave out a problem or a collection in the trash: nobody but an admin can open it.
	private static function visibleProblemJoins(): string
	{
		return 'JOIN set_connection sc ON sc.id = p.set_connection_id
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			JOIN `set` s ON s.id = sc.set_id AND s.deleted IS NULL';
	}

	//The name of an assignment: the one the teacher gave it, or the collections it draws from.
	/** @param array<string, mixed> $assignment A row of ofClassroom() */
	public static function title(array $assignment): string
	{
		if ($assignment['title'] !== null && $assignment['title'] !== '')
			return $assignment['title'];
		if (empty($assignment['collections']))
			return 'Assignment';
		return implode(', ', array_column($assignment['collections'], 'title'));
	}

	//The title an assignment is saved with, null for none.
	public static function cleanTitle(string $title): ?string
	{
		$title = trim(preg_replace('/\s+/u', ' ', $title));
		if ($title === '')
			return null;
		return mb_substr($title, 0, self::MAXIMUM_TITLE_LENGTH);
	}

	//The collections a teacher can pick problems from, as cards: their own ones, then the ones
	//of the site in the order of the collections page. A collection without problems is left out.
	/** @return array{own: list<array<string, mixed>>, site: list<array<string, mixed>>} */
	public static function collectionCards(int $teacherId): array
	{
		$rows = Util::query('SELECT s.id, s.title, s.color, s.user_id, COUNT(t.id) AS problem_count, AVG(t.rating) AS rating
			FROM `set` s
			JOIN set_connection sc ON sc.set_id = s.id
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			WHERE (s.user_id = ? AND s.deleted IS NULL) OR (' . SetTable::officialCondition('s') . ')
			GROUP BY s.id, s.title, s.color, s.user_id
			ORDER BY ' . SetConnectionTable::displayOrderForSetSql('s'),
			[$teacherId]);
		$cards = ['own' => [], 'site' => []];
		foreach ($rows as $row)
			$cards[$row['user_id'] === null ? 'site' : 'own'][] = [
				'id' => (int) $row['id'],
				'title' => (string) $row['title'],
				'color' => (string) $row['color'],
				'problemCount' => (int) $row['problem_count'],
				'difficulty' => $row['rating'] === null ? null : Rating::getReadableRankFromRating((float) $row['rating']),
			];
		return $cards;
	}

	//Whether a teacher can give the problems of the collection to a class: it is theirs or the site's.
	/** @param array<string, mixed>|Set $set A set row or entity */
	public static function canAssignSet(array|Set $set, int $teacherId): bool
	{
		return SetTable::isOfficial($set) || ((int) ($set['user_id'] ?? 0) === $teacherId && empty($set['deleted']));
	}

	//The columns problemPayload() reads, on the aliases of visibleProblemJoins(). The preview
	//needs the current SGF of the problem too: PREVIEW_COLUMN with previewJoin().
	private const PROBLEM_COLUMNS = 'sc.id AS set_connection_id, sc.tsumego_id, sc.num, s.id AS set_id, s.title AS set_title';
	private const PREVIEW_COLUMN = ', sgf.sgf';

	private static function previewJoin(): string
	{
		return 'LEFT JOIN sgf ON sgf.id = ' . SgfTable::currentVersionIDSql('sc.tsumego_id');
	}

	//A problem as the problem buttons show it: its number in its collection and the stones of the
	//preview in the tooltip (previewBoard.js).
	/**
	 * @param array<string, mixed> $row A row with PROBLEM_COLUMNS, and PREVIEW_COLUMN for a preview
	 * @return array<string, mixed>
	 */
	private static function problemPayload(array $row): array
	{
		return [
			'setConnectionId' => (int) $row['set_connection_id'],
			'tsumegoId' => (int) $row['tsumego_id'],
			'number' => (int) $row['num'],
			'setId' => (int) $row['set_id'],
			'setTitle' => (string) $row['set_title'],
			'preview' => TsumegoButton::sgfToPreviewData((string) ($row['sgf'] ?? '')),
		];
	}

	//The problems of a collection in its order, to pick from.
	/** @return list<array<string, mixed>> What problemPayload() makes of each */
	public static function collectionProblems(int $setId): array
	{
		$rows = Util::query('SELECT ' . self::PROBLEM_COLUMNS . self::PREVIEW_COLUMN . '
			FROM set_connection sc
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			JOIN `set` s ON s.id = sc.set_id
			' . self::previewJoin() . '
			WHERE sc.set_id = ?
			ORDER BY sc.num, sc.id',
			[$setId]);
		return array_map(self::problemPayload(...), $rows);
	}

	//The problems of an assignment in the order the teacher put them, with the previews for the
	//buttons when $withPreview.
	/**
	 * @param array<string, mixed> $assignment
	 * @return list<array<string, mixed>> What problemPayload() makes of each
	 */
	public static function problems(array $assignment, bool $withPreview = false): array
	{
		$rows = Util::query('SELECT ' . self::PROBLEM_COLUMNS . ($withPreview ? self::PREVIEW_COLUMN : '') . '
			FROM classroom_assignment_problem p
			' . self::visibleProblemJoins() . '
			' . ($withPreview ? self::previewJoin() : '') . '
			WHERE p.classroom_assignment_id = ?
			ORDER BY p.position, p.id',
			[(int) $assignment['id']]);
		return array_map(self::problemPayload(...), $rows);
	}

	//Puts the problems in the assignment in place of the ones it had, in the given order. A
	//problem from a collection the teacher can't give, one in the trash and one already in the
	//assignment from another collection are left out.
	//Returns how many problems the assignment has now
	/** @param list<int> $setConnectionIds */
	public static function replaceProblems(int $assignmentId, array $setConnectionIds, int $teacherId): int
	{
		$rows = empty($setConnectionIds) ? [] : Util::query('SELECT sc.id, sc.tsumego_id, s.public, s.user_id, s.deleted
			FROM set_connection sc
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			JOIN `set` s ON s.id = sc.set_id
			WHERE sc.id IN (' . implode(',', array_fill(0, count($setConnectionIds), '?')) . ')',
			$setConnectionIds);
		$problems = [];
		foreach ($rows as $row)
			if (self::canAssignSet($row, $teacherId))
				$problems[(int) $row['id']] = (int) $row['tsumego_id'];

		$connection = Util::connection();
		$connection->beginTransaction();
		try
		{
			Util::execute('DELETE FROM classroom_assignment_problem WHERE classroom_assignment_id = ?', [$assignmentId]);
			$tsumegoIds = [];
			foreach ($setConnectionIds as $setConnectionId)
			{
				$tsumegoId = $problems[$setConnectionId] ?? null;
				if ($tsumegoId === null || isset($tsumegoIds[$tsumegoId]))
					continue;
				$tsumegoIds[$tsumegoId] = true;
				Util::execute('INSERT INTO classroom_assignment_problem (classroom_assignment_id, set_connection_id, position) VALUES (?, ?, ?)',
					[$assignmentId, $setConnectionId, count($tsumegoIds)]);
			}
			$connection->commit();
		}
		catch (Throwable $exception)
		{
			$connection->rollBack();
			throw $exception;
		}
		return count($tsumegoIds);
	}

	//The address where a pupil plays the assignment as one row of problems, at the problem when
	//given, else at the first one they haven't solved (ClassroomsController::play()).
	public static function playLink(int $assignmentId, ?int $setConnectionId = null): string
	{
		return '/classes/play/' . $assignmentId . ($setConnectionId === null ? '' : '/' . $setConnectionId);
	}

	//Counts a result of the player on a problem for every published assignment of their classes
	//that has it: a misplay until the problem is solved for the assignment, and the first solve. A problem solved before counts too when it is
	//solved again (the play page sends that result in an assignment, see Play::play()).
	public static function recordResult(int $userId, int $tsumegoId, PlayOutcome $outcome): void
	{
		if ($outcome === PlayOutcome::TimedOut)
			return;
		$rows = Util::query('SELECT DISTINCT a.id FROM classroom_member m
			JOIN classroom_assignment a ON a.classroom_id = m.classroom_id AND a.published IS NOT NULL
			JOIN classroom_assignment_problem p ON p.classroom_assignment_id = a.id
			JOIN set_connection sc ON sc.id = p.set_connection_id AND sc.tsumego_id = ?
			WHERE m.user_id = ? AND m.classroom_member_status_id = ?',
			[$tsumegoId, $userId, ClassroomMemberStatusTable::ACCEPTED]);
		if (empty($rows))
			return;

		$solved = $outcome === PlayOutcome::Failed ? null : date('Y-m-d H:i:s');
		// a solve after a mistake whose fail wasn't sent, as on a problem solved before, still had one
		$misplays = $outcome === PlayOutcome::SolvedClean ? 0 : 1;
		foreach ($rows as $row)
			Util::execute('INSERT INTO classroom_assignment_result (classroom_assignment_id, user_id, tsumego_id, misplays, solved)
				VALUES (?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					misplays = IF(solved IS NOT NULL, misplays, IF(VALUES(solved) IS NULL, misplays + 1, GREATEST(misplays, VALUES(misplays)))),
					solved = COALESCE(solved, VALUES(solved))',
				[(int) $row['id'], $userId, $tsumegoId, $misplays, $solved]);
	}

	//The problems of the assignment the player solved for it, by tsumego id.
	/**
	 * @param array<string, mixed> $assignment
	 * @return array<int, true>
	 */
	public static function solvedTsumegoIds(array $assignment, int $userId): array
	{
		$rows = Util::query('SELECT tsumego_id FROM classroom_assignment_result
			WHERE classroom_assignment_id = ? AND user_id = ? AND solved IS NOT NULL',
			[(int) $assignment['id'], $userId]);
		return array_fill_keys(array_map('intval', array_column($rows, 'tsumego_id')), true);
	}

	//The first problem of the assignment the player hasn't solved for it, or the first problem
	//once they solved them all; null for an assignment without problems.
	/** @param array<string, mixed> $assignment */
	public static function firstUnsolvedSetConnectionId(array $assignment, int $userId): ?int
	{
		$problems = self::problems($assignment);
		if (empty($problems))
			return null;
		$solved = self::solvedTsumegoIds($assignment, $userId);
		foreach ($problems as $problem)
			if (!isset($solved[$problem['tsumegoId']]))
				return $problem['setConnectionId'];
		return $problems[0]['setConnectionId'];
	}

	//The row of buttons under the board while the player plays the assignment, numbered from 1
	//like a collection of its own: green for a problem solved for the assignment, light blue
	//for one solved before but not yet for the assignment, grey for one never solved. The
	//tooltip names the collection and the number the problem has there.
	/** @param array<string, mixed> $assignment */
	public static function playButtons(array $assignment, int $userId, int $currentSetConnectionId): TsumegoButtons
	{
		$rows = Util::query('SELECT sc.id AS set_connection_id, sc.tsumego_id, sc.num, s.title AS set_title, t.rating, ts.status AS tsumego_status' . self::PREVIEW_COLUMN . '
			FROM classroom_assignment_problem p
			' . self::visibleProblemJoins() . '
			LEFT JOIN tsumego_status ts ON ts.tsumego_id = sc.tsumego_id AND ts.user_id = ?
			' . self::previewJoin() . '
			WHERE p.classroom_assignment_id = ?
			ORDER BY p.position, p.id',
			[$userId, (int) $assignment['id']]);
		$solved = self::solvedTsumegoIds($assignment, $userId);
		foreach ($rows as &$row)
			$row['status'] = match (true)
			{
				isset($solved[(int) $row['tsumego_id']]) => self::BUTTON_SOLVED,
				TsumegoUtil::isSolvedStatus($row['tsumego_status']) => self::BUTTON_SOLVED_BEFORE,
				default => self::BUTTON_NOT_SOLVED,
			};
		unset($row);

		$labels = [
			self::BUTTON_SOLVED => 'Solved',
			self::BUTTON_SOLVED_BEFORE => 'Solved before, not yet for this assignment',
			self::BUTTON_NOT_SOLVED => 'Not solved yet',
		];
		$buttons = TsumegoButtons::fromRows($rows, $currentSetConnectionId, self::MAXIMUM_PROBLEMS, self::playLink((int) $assignment['id']) . '/');
		$rowsBySetConnection = array_column($rows, null, 'set_connection_id');
		foreach ($buttons as $button)
		{
			$row = $rowsBySetConnection[$button->setConnectionID];
			$button->label = $labels[$row['status']];
			$button->description = $row['set_title'] . ' ' . $row['num'];
		}
		return $buttons;
	}

	//Whether a problem of the collection is in a published assignment of a class the player is
	//in. Such a player can open the collection even when the teacher keeps it private.
	public static function isSetAssignedTo(int $userId, int $setId): bool
	{
		$rows = Util::query('SELECT 1 FROM classroom_assignment a
			JOIN classroom_member m ON m.classroom_id = a.classroom_id AND m.user_id = ? AND m.classroom_member_status_id = ?
			JOIN classroom_assignment_problem p ON p.classroom_assignment_id = a.id
			JOIN set_connection sc ON sc.id = p.set_connection_id AND sc.set_id = ?
			WHERE a.published IS NOT NULL
			LIMIT 1',
			[$userId, ClassroomMemberStatusTable::ACCEPTED, $setId]);
		return !empty($rows);
	}

	//The moment from which a solve is late: the end of the due day.
	/** @param array<string, mixed> $assignment */
	public static function lateFrom(array $assignment): string
	{
		return date('Y-m-d H:i:s', strtotime($assignment['due'] . ' +1 day'));
	}

	//Who of the class solved which assigned problem: one row per pupil, one cell per problem.
	//A cell is null for a problem not touched for the assignment, or says it was solved for it
	//(late when after the due day), solved only before, or tried without a solve yet.
	//$pupilId: Only this pupil, for the pupil looking at their own homework
	/**
	 * @param array<string, mixed> $assignment A published one
	 * @return array{problems: list<array<string, mixed>>, pupils: list<array<string, mixed>>}
	 */
	public static function progress(array $assignment, ?int $pupilId = null): array
	{
		$problems = self::problems($assignment);
		$pupils = ClassroomMemberTable::ofClassroom((int) $assignment['classroom_id'])[ClassroomMemberStatusTable::ACCEPTED];
		if ($pupilId !== null)
			$pupils = array_values(array_filter($pupils, fn(array $pupil) => (int) $pupil['user_id'] === $pupilId));

		$results = Util::query('SELECT r.user_id, r.tsumego_id, r.solved, r.misplays
			FROM classroom_assignment_result r
			JOIN classroom_member m ON m.user_id = r.user_id AND m.classroom_id = ? AND m.classroom_member_status_id = ?
			WHERE r.classroom_assignment_id = ?',
			[(int) $assignment['classroom_id'], ClassroomMemberStatusTable::ACCEPTED, (int) $assignment['id']]);
		$statuses = Util::query('SELECT ts.user_id, ts.tsumego_id, ts.status
			FROM classroom_member m
			JOIN tsumego_status ts ON ts.user_id = m.user_id
			WHERE m.classroom_id = ? AND m.classroom_member_status_id = ?
				AND ts.tsumego_id IN (SELECT sc.tsumego_id FROM classroom_assignment_problem p
					JOIN set_connection sc ON sc.id = p.set_connection_id
					WHERE p.classroom_assignment_id = ?)',
			[(int) $assignment['classroom_id'], ClassroomMemberStatusTable::ACCEPTED, (int) $assignment['id']]);

		$lateFrom = self::lateFrom($assignment);
		$cells = [];
		foreach ($statuses as $status)
			if (TsumegoUtil::isSolvedStatus($status['status']))
				$cells[$status['user_id']][$status['tsumego_id']] = ['state' => self::CELL_EARLIER];
		foreach ($results as $result)
		{
			$misplays = (int) $result['misplays'];
			if ($result['solved'] !== null)
				$cells[$result['user_id']][$result['tsumego_id']] = [
					'state' => $result['solved'] >= $lateFrom ? self::CELL_LATE : self::CELL_SOLVED,
					'solvedAt' => Util::toIso8601($result['solved']),
					'misplays' => $misplays,
				];
			elseif ($misplays > 0)
				$cells[$result['user_id']][$result['tsumego_id']] = ['state' => self::CELL_TRIED, 'misplays' => $misplays];
		}

		$pupilRows = [];
		foreach ($pupils as $pupil)
		{
			$userId = (int) $pupil['user_id'];
			$row = [];
			$solvedCount = 0;
			foreach ($problems as $problem)
			{
				$cell = $cells[$userId][$problem['tsumegoId']] ?? null;
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
					'avatar_setting_id' => $pupil['user_avatar_setting_id'],
					'uploaded_avatar_hash' => $pupil['user_uploaded_avatar_hash'],
				]),
				'rating' => (float) $pupil['user_rating'],
				'solvedCount' => $solvedCount,
				'cells' => $row,
			];
		}

		return [
			'problems' => array_map(fn(array $problem) => [
				'setConnectionId' => $problem['setConnectionId'],
				'number' => $problem['number'],
				'setId' => $problem['setId'],
				'setTitle' => $problem['setTitle'],
			], $problems),
			'pupils' => $pupilRows,
		];
	}
}
