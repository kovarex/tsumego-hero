<?php

namespace App\Model\Table;

use App\Model\Entity\Challenge;
use App\Model\Entity\TimeModeSession;
use App\Utility\TimeMode;
use App\Utility\TimeModeUtil;
use App\Utility\TsumegoButton;
use App\Utility\Util;
use Cake\ORM\TableRegistry;
use ClassRegistry;
use Sgf;

//A challenge between players: one player picks a range of difficulty, the time for a problem and
//how many problems, the site draws the problems, and every player in the challenge plays the same
//ones like a time mode run, whenever they like. The run of a player is a time_mode_session of the
//challenge, so the board, the clock and the points are the ones of the time mode.
//Who is in the challenge is challenge_participant: the player who created it, the players they or
//another participant invited, and anyone who opened its link and joined. A player who hasn't
//started their run can decline the challenge; the row stays, so the others see it and nobody
//invites them again, and the player can still change their mind and play.
class ChallengeTable extends AppTable
{
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

	//Draws the problems and creates the challenge, with its creator as its first participant.
	//Returns the id of the new challenge, null when there is no problem of these ranks
	public static function start(int $creatorUserId, int $minimumTimeModeRankId, int $maximumTimeModeRankId, int $secondsPerProblem, int $problemCount): ?int
	{
		$tsumegos = TimeMode::getTsumegosOfRanks($minimumTimeModeRankId, $maximumTimeModeRankId);
		if (empty($tsumegos))
			return null;
		shuffle($tsumegos);

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
		$rows = Util::query('SELECT p.`order`, p.tsumego_id, t.rating, sgf.sgf
			FROM challenge_problem p
			JOIN tsumego t ON t.id = p.tsumego_id
			LEFT JOIN sgf ON sgf.id = ' . Sgf::currentVersionIDSql('p.tsumego_id') . '
			WHERE p.challenge_id = ?
			ORDER BY p.`order`', [$challengeId]);
		$buttons = [];
		foreach ($rows as $row)
		{
			$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection((int) $row['tsumego_id']);
			if (!$setConnection)
				continue;
			$order = (int) $row['order'];
			$attempt = $myAttempts[$order] ?? null;
			[$status, $label, $description] = match ($attempt['status'] ?? 'skipped')
			{
				'solved' => ['S', 'Solved', 'You solved it in ' . self::formatSeconds((float) $attempt['seconds']) . ' for ' . self::formatPoints((float) $attempt['points']) . ' points.'],
				'failed' => ['F', 'Failed', 'You got it wrong.'],
				'timeout' => ['F', 'Out of time', 'You ran out of time.'],
				default => ['N', 'Skipped', 'You didn\'t play it.'],
			};
			$button = new TsumegoButton((int) $row['tsumego_id'], (int) $setConnection['SetConnection']['id'], $order, $status, (float) $row['rating'], $row['sgf'] ?? '');
			$button->label = $label;
			$button->description = $description;
			$buttons[$order] = $button;
		}
		return $buttons;
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
		return Util::query(self::selectSql('my_run.time_mode_session_status_id AS my_run_status_id, my_run.points AS my_points, me.declined AS my_declined,') . '
			JOIN challenge_participant me ON me.challenge_id = c.id AND me.user_id = ?
			LEFT JOIN time_mode_session my_run ON my_run.challenge_id = c.id AND my_run.user_id = me.user_id
			ORDER BY c.created DESC, c.id DESC',
			[$userId]);
	}

	//The challenges a player is in and hasn't played nor declined yet, for the badge of the menu.
	public static function countWaitingFor(int $userId): int
	{
		$rows = Util::query('SELECT COUNT(*) AS count
			FROM challenge_participant p
			LEFT JOIN time_mode_session s ON s.challenge_id = p.challenge_id AND s.user_id = p.user_id
			WHERE p.user_id = ? AND p.declined IS NULL AND s.id IS NULL', [$userId]);
		return (int) $rows[0]['count'];
	}

	//$extraColumns: Columns the caller joins in, each followed by a comma
	private static function selectSql(string $extraColumns = ''): string
	{
		return 'SELECT c.id, c.created_by_user_id, c.minimum_time_mode_rank_id, c.maximum_time_mode_rank_id,
				c.seconds_per_problem, c.created, minimum_rank.name AS minimum_rank, maximum_rank.name AS maximum_rank, ' . $extraColumns . '
				(SELECT COUNT(*) FROM challenge_problem p WHERE p.challenge_id = c.id) AS problem_count,
				(SELECT COUNT(*) FROM challenge_participant p JOIN user pu ON pu.id = p.user_id AND pu.deleted IS NULL
					WHERE p.challenge_id = c.id AND p.declined IS NULL) AS participant_count,
				(SELECT COUNT(*) FROM time_mode_session s JOIN user su ON su.id = s.user_id AND su.deleted IS NULL
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

	//Whether the player is in the challenge and didn't decline it.
	public static function isParticipant(int $challengeId, int $userId): bool
	{
		$participation = self::participation($challengeId, $userId);
		return $participation !== null && $participation['declined'] === null;
	}

	//The players in the challenge, without the ones who declined it.
	public static function participantCount(int $challengeId): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM challenge_participant WHERE challenge_id = ? AND declined IS NULL', [$challengeId])[0]['count'];
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
	//(the faster one first when the points are the same) with their place, then the runs being
	//played, then the players who haven't started, and the ones who declined last. A deleted account
	//is left out.
	/**
	 * @return array<array<string, mixed>> Rows of USER_COLUMNS, session_id, run (RUN_*), points,
	 *   solved_count, played_count, seconds and place (null for a run not finished)
	 */
	public static function standings(int $challengeId): array
	{
		$rows = Util::query('SELECT p.created AS joined, p.declined, ' . ClassroomTable::USER_COLUMNS . ',
				s.id AS session_id, s.time_mode_session_status_id, s.points,
				(SELECT COUNT(*) FROM time_mode_attempt a WHERE a.time_mode_session_id = s.id AND a.time_mode_attempt_status_id = ?) AS solved_count,
				(SELECT COUNT(*) FROM time_mode_attempt a WHERE a.time_mode_session_id = s.id AND a.time_mode_attempt_status_id != ?) AS played_count,
				(SELECT COALESCE(SUM(a.seconds), 0) FROM time_mode_attempt a WHERE a.time_mode_session_id = s.id) AS seconds
			FROM challenge_participant p
			JOIN user u ON u.id = p.user_id AND u.deleted IS NULL
			LEFT JOIN time_mode_session s ON s.challenge_id = p.challenge_id AND s.user_id = p.user_id
			WHERE p.challenge_id = ?',
			[TimeModeUtil::$ATTEMPT_RESULT_SOLVED, TimeModeUtil::$ATTEMPT_RESULT_QUEUED, $challengeId]);

		foreach ($rows as &$row)
		{
			if ($row['session_id'] === null)
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

		$runOrder = [self::RUN_FINISHED => 0, self::RUN_PLAYING => 1, self::RUN_NOT_STARTED => 2, self::RUN_DECLINED => 3];
		usort($rows, fn(array $a, array $b) => [$runOrder[$a['run']], -$a['points'], $a['seconds'], -$a['played_count'], $a['joined'], $a['user_display_name']]
			<=> [$runOrder[$b['run']], -$b['points'], $b['seconds'], -$b['played_count'], $b['joined'], $b['user_display_name']]);

		// the same points in the same time share the place
		$previous = null;
		foreach ($rows as $index => &$row)
		{
			if ($row['run'] !== self::RUN_FINISHED)
				break;
			$row['place'] = $previous !== null && $previous['points'] == $row['points'] && $previous['seconds'] == $row['seconds']
				? $previous['place']
				: $index + 1;
			$previous = $row;
		}
		unset($row);
		return $rows;
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
				'status' => TimeModeUtil::attemptStatusName((int) $row['time_mode_attempt_status_id']),
				'seconds' => $row['seconds'] === null ? null : (float) $row['seconds'],
				'points' => $row['points'] === null ? null : (float) $row['points'],
			];
		return $result;
	}
}
