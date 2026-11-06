<?php

namespace App\Model\Table;

use App\Model\Entity\Challenge;
use App\Model\Entity\TimeModeSession;
use App\Utility\TimeMode;
use App\Utility\TsumegoButton;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

//A challenge between players: one player picks a range of difficulty, the time for a problem and
//how many problems, the site draws the problems, and every player in the challenge plays the same
//ones like a time mode run, whenever they like. The run of a player is a time_mode_session of the
//challenge, so the board, the clock and the points are the ones of the time mode.
//Who is in the challenge is challenge_participant: the player who created it, the players they or
//another participant invited, and anyone who opened its link and joined. A player who hasn't
//started their run can decline the challenge; the row stays, so the others see it and nobody
//invites them again, and the player can still change their mind and play.
//A challenge is in progress until every player still in it finished their run; only then are there
//places. The creator can remove a player whose run is waiting too long (canRemove()), who then
//doesn't count for the finish.
class ChallengeTable extends AppTable
{
	//How long a run may wait before the creator can remove its player: an invited player who
	//hasn't started, and a started run without an answer.
	public const REMOVABLE_NOT_STARTED_AFTER_HOURS = 24;
	public const REMOVABLE_IDLE_AFTER_MINUTES = 60;

	//The times for a problem to choose from, in seconds.
	public const SECONDS_PER_PROBLEM_CHOICES = [15, 30, 45, 60, 90, 120, 180, 240, 300, 600, 1800];
	public const DEFAULT_SECONDS_PER_PROBLEM = 60;

	//The numbers of problems to choose from.
	public const PROBLEM_COUNT_CHOICES = [5, 10, 15, 20, 30];
	public const DEFAULT_PROBLEM_COUNT = 10;

	//More players than this can't be in one challenge, so its invitations can't be used to message everyone.
	public const MAXIMUM_PARTICIPANTS = 50;

	//Where a player is with their run, see standings().
	public const RUN_NOT_STARTED = 'not started';
	public const RUN_PLAYING = 'playing';
	public const RUN_FINISHED = 'finished';
	public const RUN_DECLINED = 'declined';
	public const RUN_REMOVED = 'removed';

	//Draws the problems and creates the challenge, with its creator as its first participant.
	//Returns the id of the new challenge, null when there is no problem of these ranks
	/** @param list<int> $avoidTsumegoIds Problems drawn only when there are not enough others, like the ones of the challenge a rematch repeats */
	public static function start(int $creatorUserId, int $minimumTimeModeRankId, int $maximumTimeModeRankId, int $secondsPerProblem, int $problemCount, array $avoidTsumegoIds = []): ?int
	{
		$tsumegos = TimeMode::getTsumegosOfRanks($minimumTimeModeRankId, $maximumTimeModeRankId);
		if (empty($tsumegos))
			return null;
		shuffle($tsumegos);
		$avoid = array_fill_keys($avoidTsumegoIds, true);
		// the sort keeps the shuffled order within the problems to draw first and within the ones to avoid
		usort($tsumegos, fn(array $a, array $b) => isset($avoid[(int) $a['id']]) <=> isset($avoid[(int) $b['id']]));

		$challengeTable = TableRegistry::getTableLocator()->get('Challenge');
		$challenge = $challengeTable->saveOrFail($challengeTable->newEntity([
			'created_by_user_id' => $creatorUserId,
			'minimum_time_mode_rank_id' => $minimumTimeModeRankId,
			'maximum_time_mode_rank_id' => $maximumTimeModeRankId,
			'seconds_per_problem' => $secondsPerProblem,
		]));
		$challengeId = $challenge->id;

		foreach (array_slice($tsumegos, 0, $problemCount) as $index => $tsumego)
			Util::execute('INSERT INTO challenge_problem (challenge_id, tsumego_id, `order`) VALUES (?, ?, ?)',
				[$challengeId, $tsumego['id'], $index + 1]);
		self::addParticipant($challengeId, $creatorUserId, null);
		return $challengeId;
	}

	//A new challenge with the settings of the challenge, started by the player, with the other players
	//of the challenge invited (the ones who declined it, were removed or deleted their account are left out). New
	//problems are drawn, the ones of the challenge only when the ranks have too few others.
	//Returns the id of the new challenge and the ids of the players invited to it, null when the ranks have no problem anymore
	/**
	 * @param array<string, mixed> $challenge As withDetails() returns it
	 * @return array{0: int, 1: list<int>}|null
	 */
	public static function rematch(array $challenge, int $userId): ?array
	{
		$challengeId = (int) $challenge['id'];
		$rematchId = self::start(
			$userId,
			(int) $challenge['minimum_time_mode_rank_id'],
			(int) $challenge['maximum_time_mode_rank_id'],
			(int) $challenge['seconds_per_problem'],
			(int) $challenge['problem_count'],
			self::problemIDs($challengeId));
		if ($rematchId === null)
			return null;

		$players = array_map('intval', array_column(Util::query('SELECT p.user_id
			FROM challenge_participant p
			JOIN user u ON u.id = p.user_id AND u.deleted IS NULL
			WHERE p.challenge_id = ? AND p.declined IS NULL AND p.removed IS NULL AND p.user_id != ?
			ORDER BY p.id', [$challengeId, $userId]), 'user_id'));
		foreach ($players as $playerId)
			self::addParticipant($rematchId, $playerId, $userId);
		return [$rematchId, $players];
	}

	//The problems of a challenge in the order they are played.
	/** @return array<int> Tsumego ids */
	public static function problemIDs(int $challengeId): array
	{
		$rows = Util::query('SELECT tsumego_id FROM challenge_problem WHERE challenge_id = ? ORDER BY `order`', [$challengeId]);
		return array_map(fn(array $row) => (int) $row['tsumego_id'], $rows);
	}

	//The problems of a challenge as the buttons of a collection page, numbered in the order they
	//are played, with the board in the tooltip, and coloured by how the player looking did with
	//them in their run: green solved, red failed or out of time, grey skipped.
	/**
	 * @param array<int, array{status: string, seconds: ?float, points: ?float}> $myAttempts The
	 *     attempts of the run of the player looking, by the order, as attemptsByRun() gives them
	 * @return array<int, TsumegoButton> By the order in the challenge
	 */
	public static function problemButtons(int $challengeId, array $myAttempts): array
	{
		// a problem in no collection has no page to open, so it gets no button
		$rows = Util::query('SELECT p.`order`, p.tsumego_id, t.rating, sgf.sgf, sc.id AS set_connection_id
			FROM challenge_problem p
			JOIN tsumego t ON t.id = p.tsumego_id
			JOIN set_connection sc ON sc.id = ' . SetConnectionTable::displaySetConnectionIdSql('p.tsumego_id') . '
			LEFT JOIN sgf ON sgf.id = ' . SgfTable::currentVersionIDSql('p.tsumego_id') . '
			WHERE p.challenge_id = ?
			ORDER BY p.`order`', [$challengeId]);
		$buttons = [];
		foreach ($rows as $row)
		{
			$order = (int) $row['order'];
			$attempt = $myAttempts[$order] ?? null;
			[$status, $label, $description] = match ($attempt['status'] ?? 'skipped')
			{
				'solved' => ['S', 'Solved', 'You solved it in ' . self::formatSeconds((float) $attempt['seconds']) . ' for ' . self::formatPoints((float) $attempt['points']) . ' points.'],
				'failed' => ['F', 'Failed', 'You got it wrong.'],
				'timeout' => ['F', 'Out of time', 'You ran out of time.'],
				default => ['N', 'Skipped', 'You didn\'t play it.'],
			};
			$button = new TsumegoButton((int) $row['tsumego_id'], (int) $row['set_connection_id'], $order, $status, (float) $row['rating'], $row['sgf'] ?? '');
			$button->label = $label;
			$button->description = $description;
			$buttons[$order] = $button;
		}
		return $buttons;
	}

	//The most points a problem gives, solved at once (TimeMode::calculatePoints()).
	public const MAXIMUM_POINTS_PER_PROBLEM = 100;

	//The points of a run as the percentage of the most a run of the challenge can get, rounded.
	public static function percentOfMaximum(float $points, int $problemCount): int
	{
		return $problemCount > 0 ? (int) round($points / ($problemCount * self::MAXIMUM_POINTS_PER_PROBLEM) * 100) : 0;
	}

	//Points as the tables show them, to a tenth.
	public static function formatPoints(float $points): string
	{
		return number_format($points, 1, '.', '');
	}

	//Seconds as the tables show them, like 1:05.3.
	public static function formatSeconds(float $seconds): string
	{
		$minutes = (int) floor($seconds / 60);
		return $minutes . ':' . str_pad(number_format($seconds - $minutes * 60, 1, '.', ''), 4, '0', STR_PAD_LEFT);
	}

	public static function title(array $challenge): string
	{
		return 'Challenge #' . (int) $challenge['id'];
	}

	//What is played in a challenge, like "10 problems, 5k to 1d, 1 minute each".
	/** @param array<string, mixed> $challenge With problem_count, minimum_rank and maximum_rank, see withDetails() */
	public static function describe(array $challenge): string
	{
		$ranks = $challenge['minimum_rank'] === $challenge['maximum_rank']
			? $challenge['minimum_rank']
			: $challenge['minimum_rank'] . ' to ' . $challenge['maximum_rank'];
		return $challenge['problem_count'] . ' problem' . ($challenge['problem_count'] == 1 ? '' : 's') . ', '
			. $ranks . ', ' . self::formatTimeForAProblem((int) $challenge['seconds_per_problem']) . ' each';
	}

	//The time for a problem in words, like "45 seconds", "1 minute" or "1.5 minutes".
	public static function formatTimeForAProblem(int $seconds): string
	{
		if ($seconds < 60)
			return $seconds . ' seconds';
		return ($seconds / 60) . ($seconds === 60 ? ' minute' : ' minutes');
	}

	//A challenge with the names of its ranks and the number of its problems.
	/** @return array<string, mixed>|null */
	public static function withDetails(int $challengeId): ?array
	{
		$rows = Util::query(self::selectSql() . ' WHERE c.id = ?', [$challengeId]);
		return $rows[0] ?? null;
	}

	//The challenges a player is in, the newest first, with how many players are in each and how
	//many finished, and where the player is with their own run.
	/** @return array<array<string, mixed>> */
	public static function ofPlayer(int $userId): array
	{
		return Util::query(self::selectSql('my_run.time_mode_session_status_id AS my_run_status_id, my_run.points AS my_points, me.declined AS my_declined, me.removed AS my_removed,') . '
			JOIN challenge_participant me ON me.challenge_id = c.id AND me.user_id = ?
			LEFT JOIN time_mode_session my_run ON my_run.challenge_id = c.id AND my_run.user_id = me.user_id
			ORDER BY c.created DESC, c.id DESC',
			[$userId]);
	}

	//The challenges a player is in and hasn't played nor declined yet, for the badge of the menu.
	public static function countWaitingFor(int $userId): int
	{
		[$sql, $params] = self::countWaitingForQuery($userId);
		return (int) Util::query($sql, $params)[0]['count'];
	}

	//The query of countWaitingFor(), which the menu asks together with its other counts (HeaderCounts)
	/** @return array{string, list<mixed>} */
	public static function countWaitingForQuery(int $userId): array
	{
		return ['SELECT COUNT(*) AS count
			FROM challenge_participant p
			LEFT JOIN time_mode_session s ON s.challenge_id = p.challenge_id AND s.user_id = p.user_id
			WHERE p.user_id = ? AND p.declined IS NULL AND p.removed IS NULL AND s.id IS NULL', [$userId]];
	}

	//$extraColumns: Columns the caller joins in, each followed by a comma
	private static function selectSql(string $extraColumns = ''): string
	{
		return 'SELECT c.id, c.created_by_user_id, c.minimum_time_mode_rank_id, c.maximum_time_mode_rank_id,
				c.seconds_per_problem, c.created, minimum_rank.name AS minimum_rank, maximum_rank.name AS maximum_rank, ' . $extraColumns . '
				(SELECT COUNT(*) FROM challenge_problem p WHERE p.challenge_id = c.id) AS problem_count,
				(SELECT COUNT(*) FROM challenge_participant p JOIN user pu ON pu.id = p.user_id AND pu.deleted IS NULL
					WHERE p.challenge_id = c.id AND p.declined IS NULL AND p.removed IS NULL) AS participant_count,
				(SELECT COUNT(*) FROM time_mode_session s JOIN user su ON su.id = s.user_id AND su.deleted IS NULL
					JOIN challenge_participant sp ON sp.challenge_id = s.challenge_id AND sp.user_id = s.user_id AND sp.removed IS NULL
					WHERE s.challenge_id = c.id AND s.time_mode_session_status_id != ' . TimeModeSessionStatusTable::IN_PROGRESS . ') AS finished_count,
				' . ClassroomTable::USER_COLUMNS . '
			FROM challenge c
			JOIN time_mode_rank minimum_rank ON minimum_rank.id = c.minimum_time_mode_rank_id
			JOIN time_mode_rank maximum_rank ON maximum_rank.id = c.maximum_time_mode_rank_id
			LEFT JOIN user u ON u.id = c.created_by_user_id';
	}

	//The row of a player in a challenge, in it or declined, null when they were never in it.
	/** @return array<string, mixed>|null */
	public static function participation(int $challengeId, int $userId): ?array
	{
		return Util::query('SELECT * FROM challenge_participant WHERE challenge_id = ? AND user_id = ?', [$challengeId, $userId])[0] ?? null;
	}

	//Whether the player is in the challenge: they didn't decline it and the creator didn't remove them.
	public static function isParticipant(int $challengeId, int $userId): bool
	{
		$participation = self::participation($challengeId, $userId);
		return $participation !== null && $participation['declined'] === null && $participation['removed'] === null;
	}

	//The players in the challenge, without the ones who declined it or were removed from it.
	public static function participantCount(int $challengeId): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM challenge_participant WHERE challenge_id = ? AND declined IS NULL AND removed IS NULL', [$challengeId])[0]['count'];
	}

	//Whether every player still in the challenge finished their run, so the places are final. With
	//the counts of withDetails() and ofPlayer(), which leave out the declined, removed and deleted players.
	/** @param array<string, mixed> $challenge */
	public static function isFinished(array $challenge): bool
	{
		return (int) $challenge['participant_count'] > 0 && (int) $challenge['finished_count'] >= (int) $challenge['participant_count'];
	}

	//Whether the creator can remove the player of the row of standings(): their run waits too long,
	//not started REMOVABLE_NOT_STARTED_AFTER_HOURS after they got into the challenge, or without an
	//answer REMOVABLE_IDLE_AFTER_MINUTES after the last problem was shown.
	/** @param array<string, mixed> $standing */
	public static function canRemove(array $standing, int $now): bool
	{
		return match ($standing['run'])
		{
			self::RUN_NOT_STARTED => strtotime($standing['joined']) <= $now - self::REMOVABLE_NOT_STARTED_AFTER_HOURS * 3600,
			self::RUN_PLAYING => strtotime($standing['last_activity']) <= $now - self::REMOVABLE_IDLE_AFTER_MINUTES * 60,
			default => false,
		};
	}

	//The creator takes the player out of the challenge; the row stays, so the table says so and nobody invites them again.
	public static function remove(int $challengeId, int $userId): void
	{
		Util::execute('UPDATE challenge_participant SET removed = NOW() WHERE challenge_id = ? AND user_id = ? AND removed IS NULL', [$challengeId, $userId]);
	}

	//$invitedByUserId: Who invited the player, null when they came on their own
	//Returns false when the player is in the challenge already or declined it
	public static function addParticipant(int $challengeId, int $userId, ?int $invitedByUserId): bool
	{
		if (self::participation($challengeId, $userId) !== null)
			return false;
		Util::execute('INSERT INTO challenge_participant (challenge_id, user_id, invited_by_user_id) VALUES (?, ?, ?)',
			[$challengeId, $userId, $invitedByUserId]);
		return true;
	}

	//The player turns the challenge down. A run once started is played to its end, so only a
	//player who hasn't started can decline.
	//Returns whether the player declined
	public static function decline(int $challengeId, int $userId): bool
	{
		if (!self::isParticipant($challengeId, $userId) || self::runOf($challengeId, $userId) !== null)
			return false;
		Util::execute('UPDATE challenge_participant SET declined = NOW() WHERE challenge_id = ? AND user_id = ?', [$challengeId, $userId]);
		return true;
	}

	//A player who declined the challenge changes their mind and is in it again.
	public static function rejoin(int $challengeId, int $userId): void
	{
		Util::execute('UPDATE challenge_participant SET declined = NULL WHERE challenge_id = ? AND user_id = ?', [$challengeId, $userId]);
	}

	//The run of a player in a challenge, null when they haven't started it.
	public static function runOf(int $challengeId, int $userId): ?TimeModeSession
	{
		return TableRegistry::getTableLocator()->get('TimeModeSession')->find()->where([
			'challenge_id' => $challengeId,
			'user_id' => $userId])->first();
	}

	//The players of a challenge in the order of the table: the finished runs by their points
	//(the faster one first when the points are the same), then the runs being played, then the
	//players who haven't started, the ones who declined and the ones the creator removed last. A
	//deleted account is left out. The finished runs have their place once the challenge is
	//finished (everyoneFinished()).
	/**
	 * @return array<array<string, mixed>> Rows of USER_COLUMNS, session_id, run (RUN_*), points,
	 *   solved_count, played_count, seconds, last_activity (the last problem shown, or the start of
	 *   the run) and place (null for a run not finished and while the challenge is in progress)
	 */
	public static function standings(int $challengeId): array
	{
		$rows = Util::query('SELECT p.created AS joined, p.declined, p.removed, ' . ClassroomTable::USER_COLUMNS . ',
				s.id AS session_id, s.time_mode_session_status_id, s.points,
				attempts.solved_count, attempts.played_count, attempts.seconds,
				COALESCE(attempts.last_started, s.created) AS last_activity
			FROM challenge_participant p
			JOIN user u ON u.id = p.user_id AND u.deleted IS NULL
			LEFT JOIN time_mode_session s ON s.challenge_id = p.challenge_id AND s.user_id = p.user_id
			LEFT JOIN (
				SELECT a.time_mode_session_id,
					SUM(a.time_mode_attempt_status_id = ?) AS solved_count,
					SUM(a.time_mode_attempt_status_id != ?) AS played_count,
					SUM(a.seconds) AS seconds,
					MAX(a.started) AS last_started
				FROM time_mode_session run
				JOIN time_mode_attempt a ON a.time_mode_session_id = run.id
				WHERE run.challenge_id = ?
				GROUP BY a.time_mode_session_id
			) attempts ON attempts.time_mode_session_id = s.id
			WHERE p.challenge_id = ?',
			[TimeModeAttemptStatusTable::SOLVED, TimeModeAttemptStatusTable::QUEUED, $challengeId, $challengeId]);

		foreach ($rows as &$row)
		{
			if ($row['removed'] !== null)
				$row['run'] = self::RUN_REMOVED;
			elseif ($row['session_id'] === null)
				$row['run'] = $row['declined'] === null ? self::RUN_NOT_STARTED : self::RUN_DECLINED;
			elseif ((int) $row['time_mode_session_status_id'] === TimeModeSessionStatusTable::IN_PROGRESS)
				$row['run'] = self::RUN_PLAYING;
			else
				$row['run'] = self::RUN_FINISHED;
			$row['points'] = (float) $row['points'];
			$row['seconds'] = (float) $row['seconds'];
			$row['solved_count'] = (int) $row['solved_count'];
			$row['played_count'] = (int) $row['played_count'];
			$row['place'] = null;
		}
		unset($row);

		$runOrder = [self::RUN_FINISHED => 0, self::RUN_PLAYING => 1, self::RUN_NOT_STARTED => 2, self::RUN_DECLINED => 3, self::RUN_REMOVED => 4];
		usort($rows, fn(array $a, array $b) => [$runOrder[$a['run']], -$a['points'], $a['seconds'], -$a['played_count'], $a['joined'], $a['user_display_name']]
			<=> [$runOrder[$b['run']], -$b['points'], $b['seconds'], -$b['played_count'], $b['joined'], $b['user_display_name']]);

		if (!self::everyoneFinished($rows))
			return $rows;
		$finished = array_filter($rows, fn(array $row) => $row['run'] === self::RUN_FINISHED);
		foreach ($finished as $index => $row)
			$rows[$index]['place'] = self::placeAmong($row, $finished);
		return $rows;
	}

	//Whether the players of the standings still in the challenge all finished their run, the rule
	//isFinished() counts by: the ones who declined or were removed don't count.
	/** @param array<array<string, mixed>> $standings */
	public static function everyoneFinished(array $standings): bool
	{
		$inIt = array_filter($standings, fn(array $row) => $row['run'] !== self::RUN_DECLINED && $row['run'] !== self::RUN_REMOVED);
		return !empty($inIt) && array_all($inIt, fn(array $row) => $row['run'] === self::RUN_FINISHED);
	}

	//The place of a finished run among the finished runs of its challenge: one more than the runs with
	//more points, or the same points in less time. The same points in the same time share the place.
	/**
	 * @param array{points: float, seconds: float} $run
	 * @param array<array{points: float, seconds: float}> $finishedRuns
	 */
	public static function placeAmong(array $run, array $finishedRuns): int
	{
		$better = array_filter($finishedRuns, fn(array $other) => $other['points'] > $run['points']
			|| ($other['points'] == $run['points'] && $other['seconds'] < $run['seconds']));
		return count($better) + 1;
	}

	//Where the player is in each of their challenges and who won it, for the list of their
	//challenges, from one query of the finished runs of all of them; the list shows them only for a
	//finished challenge (isFinished()). The runs of removed players don't count.
	/**
	 * @return array<int, array{myPlace: ?int, finishedCount: int, winners: list<array<string, mixed>>}> By challenge id,
	 *   only the challenges with a finished run; the winners are rows of USER_COLUMNS, by their names
	 */
	public static function placesOfPlayer(int $userId): array
	{
		$runs = Util::query('SELECT s.challenge_id, s.points, attempts.seconds, ' . ClassroomTable::USER_COLUMNS . '
			FROM challenge_participant me
			JOIN time_mode_session s ON s.challenge_id = me.challenge_id AND s.time_mode_session_status_id != ?
			JOIN user u ON u.id = s.user_id AND u.deleted IS NULL
			JOIN challenge_participant p ON p.challenge_id = s.challenge_id AND p.user_id = s.user_id AND p.removed IS NULL
			LEFT JOIN (
				SELECT a.time_mode_session_id, SUM(a.seconds) AS seconds
				FROM challenge_participant mine
				JOIN time_mode_session run ON run.challenge_id = mine.challenge_id
				JOIN time_mode_attempt a ON a.time_mode_session_id = run.id
				WHERE mine.user_id = ?
				GROUP BY a.time_mode_session_id
			) attempts ON attempts.time_mode_session_id = s.id
			WHERE me.user_id = ?
			ORDER BY u.display_name',
			[TimeModeSessionStatusTable::IN_PROGRESS, $userId, $userId]);

		$byChallenge = [];
		foreach ($runs as $run)
		{
			$run['points'] = (float) $run['points'];
			$run['seconds'] = (float) $run['seconds'];
			$byChallenge[(int) $run['challenge_id']][] = $run;
		}

		$places = [];
		foreach ($byChallenge as $challengeId => $finishedRuns)
		{
			$myRun = array_find($finishedRuns, fn(array $run) => (int) $run['user_id'] === $userId);
			$places[$challengeId] = [
				'myPlace' => $myRun === null ? null : self::placeAmong($myRun, $finishedRuns),
				'finishedCount' => count($finishedRuns),
				'winners' => array_values(array_filter($finishedRuns, fn(array $run) => self::placeAmong($run, $finishedRuns) === 1)),
			];
		}
		return $places;
	}

	//How every run of a challenge did with each problem.
	/** @return array<int, array<int, array{status: string, seconds: float|null, points: float|null}>> By session id and problem order */
	public static function attemptsByRun(int $challengeId): array
	{
		$rows = Util::query('SELECT a.time_mode_session_id, a.`order`, a.time_mode_attempt_status_id, a.seconds, a.points
			FROM time_mode_attempt a
			JOIN time_mode_session s ON s.id = a.time_mode_session_id
			WHERE s.challenge_id = ?', [$challengeId]);
		$result = [];
		foreach ($rows as $row)
			$result[(int) $row['time_mode_session_id']][(int) $row['order']] = [
				'status' => TimeModeAttemptStatusTable::name((int) $row['time_mode_attempt_status_id']),
				'seconds' => $row['seconds'] === null ? null : (float) $row['seconds'],
				'points' => $row['points'] === null ? null : (float) $row['points'],
			];
		return $result;
	}
}
