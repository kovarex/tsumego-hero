<?php

namespace App\Utility;

use App\Model\Table\ChallengeTable;
use App\Model\Table\ClassroomTable;
use App\Model\Table\FriendshipTable;
use App\Model\Table\TimeModeSessionStatusTable;

//What the friends of a player did lately, for the box on the homepage. Nothing is logged for it:
//it reads what the site keeps anyway. The achievements they unlocked (achievement_status.created),
//the challenges they finished (their time_mode_session of the challenge), the daily streaks that
//reached a milestone (the length of the streak and its last day, on the user row) and how many
//problems each solved today (user.daily_solved, which the daily cron empties).
//Two queries: the friends with their rows, then the events of all of them at once. The page is the
//busiest of the site, so each side of the events reads only the rows of the friends, newest first.
class FriendActivity
{
	//How far back the box looks, and how many events it shows at most
	public const int DAYS = 14;
	public const int MAXIMUM_EVENTS = 10;
	//The streak lengths worth telling the friends about; every hundred days after the last one too
	public const array STREAK_MILESTONES = [7, 14, 30, 50, 100, 150, 200, 250, 300, 365];

	public const string ACHIEVEMENT = 'achievement';
	public const string CHALLENGE = 'challenge';
	public const string STREAK = 'streak';

	/**
	 * @param list<array{kind: string, friend: array<string, mixed>, happened: string, subject_id: int, subject_name: string, points: float|null}> $events
	 *   Newest first; subject is the achievement, the challenge or the length of the streak
	 * @param list<array{friend: array<string, mixed>, solved: int}> $solvedToday Most first
	 */
	public function __construct(public readonly array $events, public readonly array $solvedToday) {}

	//Null when the player has no friends, so the homepage shows no box
	public static function forUser(int $userId, ?string $today = null): ?self
	{
		[$friendIdsSql, $friendIdsParams] = FriendshipTable::friendIdsQuery($userId);
		$friends = Util::query('SELECT ' . ClassroomTable::USER_COLUMNS . ', u.daily_solved, u.streak_length, u.streak_last_day
			FROM (' . $friendIdsSql . ') f
			JOIN user u ON u.id = f.id AND u.deleted IS NULL', $friendIdsParams);
		if (empty($friends))
			return null;

		$friendsById = array_column($friends, null, 'user_id');
		$today ??= date('Y-m-d');
		$since = date('Y-m-d', strtotime($today . ' -' . self::DAYS . ' days'));

		$events = [];
		foreach (self::recentEvents(array_keys($friendsById), $since) as $row)
			$events[] = [
				'kind' => $row['kind'],
				'friend' => $friendsById[$row['user_id']],
				'happened' => $row['happened'],
				'subject_id' => (int) $row['subject_id'],
				'subject_name' => $row['kind'] === self::CHALLENGE ? ChallengeTable::title(['id' => $row['subject_id']]) : (string) $row['subject_name'],
				'points' => $row['points'] === null ? null : (float) $row['points']];
		foreach ($friends as $friend)
			if (self::isStreakMilestone((int) $friend['streak_length']) && $friend['streak_last_day'] !== null && $friend['streak_last_day'] >= $since)
				$events[] = [
					'kind' => self::STREAK,
					'friend' => $friend,
					'happened' => $friend['streak_last_day'],
					'subject_id' => (int) $friend['streak_length'],
					'subject_name' => $friend['streak_length'] . '-day streak',
					'points' => null];
		usort($events, fn(array $a, array $b) => strcmp($b['happened'], $a['happened']));

		$solvedToday = [];
		foreach ($friends as $friend)
			if ((int) $friend['daily_solved'] > 0)
				$solvedToday[] = ['friend' => $friend, 'solved' => (int) $friend['daily_solved']];
		usort($solvedToday, fn(array $a, array $b) => $b['solved'] <=> $a['solved']);

		return new self(array_slice($events, 0, self::MAXIMUM_EVENTS), $solvedToday);
	}

	public static function isStreakMilestone(int $length): bool
	{
		$last = self::STREAK_MILESTONES[array_key_last(self::STREAK_MILESTONES)];
		return in_array($length, self::STREAK_MILESTONES, true) || ($length > $last && $length % 100 === 0);
	}

	//The newest achievements and finished challenge runs of the friends since the day, in one statement
	/**
	 * @param list<int> $friendIds
	 * @return list<array<string, mixed>>
	 */
	private static function recentEvents(array $friendIds, string $since): array
	{
		$in = implode(',', array_fill(0, count($friendIds), '?'));
		$limit = self::MAXIMUM_EVENTS;
		return Util::query("(SELECT '" . self::ACHIEVEMENT . "' AS kind, s.user_id, s.created AS happened, a.id AS subject_id, a.name AS subject_name, NULL AS points
				FROM achievement_status s
				JOIN achievement a ON a.id = s.achievement_id
				WHERE s.user_id IN ($in) AND s.created >= ?
				ORDER BY s.created DESC LIMIT $limit)
			UNION ALL
			(SELECT '" . self::CHALLENGE . "', t.user_id, t.created, t.challenge_id, NULL, t.points
				FROM time_mode_session t
				WHERE t.user_id IN ($in) AND t.challenge_id IS NOT NULL AND t.time_mode_session_status_id <> ? AND t.created >= ?
				ORDER BY t.created DESC LIMIT $limit)
			ORDER BY happened DESC
			LIMIT $limit",
			[...$friendIds, $since, ...$friendIds, TimeModeSessionStatusTable::IN_PROGRESS, $since]);
	}
}
