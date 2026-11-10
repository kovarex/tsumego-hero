<?php

namespace App\Utility;

use App\Model\Table\TimeModeSessionStatusTable;
use App\Model\Table\UserTable;
use Cake\Routing\Router;

//What the profile page (UsersController::view) shows of a player besides the user row.
class UserProfile
{
	//The solves, the fails and the highest rating of each day of the last half year, oldest first
	/** @return array<int, array{day: string, Solves: int|string, Fails: int|string, Rating: float|string|null}> */
	public static function dailyResults(int $userID): array
	{
		return Util::query('
			SELECT
				DATE(created) AS day,
				SUM(CASE WHEN solved = 1 THEN 1 ELSE 0 END) AS Solves,
				SUM(CASE WHEN solved = 0 THEN 1 ELSE 0 END) AS Fails,
				MAX(user_rating) AS Rating
			FROM tsumego_attempt
			WHERE user_id = ? AND created > ?
			GROUP BY DATE(created)
			ORDER BY day ASC', [$userID, date('Y-m-d', strtotime('-183 days'))]);
	}

	//For each time mode category: the best rank a run was passed at (null for none) and the number of runs
	/** @return array<int, array{category_name: string, best_solved_rank_name: string|null, session_count: int|string}> */
	public static function timeModeRanks(int $userID): array
	{
		return Util::query('
			SELECT
				category.name AS category_name,
				(SELECT time_mode_rank.name
					FROM time_mode_session passed
					JOIN time_mode_rank ON time_mode_rank.id = passed.time_mode_rank_id
					WHERE passed.time_mode_category_id = category.id
						AND passed.time_mode_session_status_id = ' . TimeModeSessionStatusTable::SOLVED . '
						AND passed.user_id = ?
					ORDER BY time_mode_rank.id DESC
					LIMIT 1) AS best_solved_rank_name,
				COUNT(runs.id) AS session_count
			FROM time_mode_category category
				LEFT JOIN time_mode_session runs ON runs.user_id = ? AND runs.time_mode_category_id = category.id
			GROUP BY category.id, category.name', [$userID, $userID]);
	}

	//The passed and failed time mode runs of each day, newest first, the runs of challenges left out
	/** @return array<int, array{category: string, Passes: int|string, Fails: int|string}> */
	public static function timeModeRuns(int $userID): array
	{
		return Util::query('
			SELECT
				DATE(created) AS category,
				SUM(CASE WHEN time_mode_session_status_id = ' . TimeModeSessionStatusTable::SOLVED . ' THEN 1 ELSE 0 END) AS Passes,
				SUM(CASE WHEN time_mode_session_status_id = ' . TimeModeSessionStatusTable::FAILED . ' THEN 1 ELSE 0 END) AS Fails
			FROM time_mode_session
			WHERE user_id = ? AND challenge_id IS NULL
			GROUP BY DATE(created)
			ORDER BY category DESC', [$userID]);
	}

	//How many days with attempts a page of the level chart of the profile shows
	//A page of the level chart: the solves and fails of each day with attempts of a calendar quarter, newest first.
	//Page 1 is the current quarter, the last the quarter of the first attempt; a page number out of range is the
	//nearest page. With the count of pages and the first and last day of the quarter.
	/** @return array{page: int, pages: int, from: string, until: string, days: list<array{day: string, Solves: int, Fails: int}>} */
	public static function levelDays(int $userID, int $page = 1): array
	{
		$now = self::quarterIndex(date('Y-m-d'));
		// the first attempt and the attempts of the quarter both come from the index on (user_id, created)
		$first = Util::query('SELECT MIN(created) AS first FROM tsumego_attempt WHERE user_id = ?', [$userID])[0]['first'];
		$pages = $first === null ? 1 : max(1, $now - self::quarterIndex($first) + 1);
		$page = min(max(1, $page), $pages);
		$quarter = $now - $page + 1;
		$from = sprintf('%04d-%02d-01', intdiv($quarter, 4), $quarter % 4 * 3 + 1);
		$next = date('Y-m-d', strtotime($from . ' +3 months'));
		$days = array_map(fn($row) => ['day' => $row['day'], 'Solves' => (int) $row['Solves'], 'Fails' => (int) $row['Fails']], Util::query('
			SELECT
				DATE(created) AS day,
				SUM(CASE WHEN solved = 1 THEN 1 ELSE 0 END) AS Solves,
				SUM(CASE WHEN solved = 0 THEN 1 ELSE 0 END) AS Fails
			FROM tsumego_attempt
			WHERE user_id = ? AND created >= ? AND created < ?
			GROUP BY DATE(created)
			ORDER BY day DESC', [$userID, $from, $next]));
		return [
			'page' => $page,
			'pages' => $pages,
			'from' => $from,
			'until' => date('Y-m-d', strtotime($next . ' -1 day')),
			'days' => $days];
	}

	//How many different achievements the player has, and how many times the ones earned more than once were earned
	/** @return array{completed: int, earned_times: int} */
	public static function achievementCounts(int $userID): array
	{
		$counts = Util::query('
			SELECT COUNT(DISTINCT achievement_id) AS completed,
				COALESCE(SUM(CASE WHEN value > 1 THEN value ELSE 0 END), 0) AS earned_times
			FROM achievement_status
			WHERE user_id = ?', [$userID])[0];
		return ['completed' => (int) $counts['completed'], 'earned_times' => (int) $counts['earned_times']];
	}

	//The Open Graph tags the profile is shared with: the avatar the player picked, at the size a
	//preview takes. What the site draws is a png of known size and type; an avatar of another host
	//is shared as its url, and only the size a host is told to send is known.
	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	public static function openGraph(array $user): array
	{
		$og = [
			'title' => 'Profile of ' . $user['display_name'],
			'description' => $user['display_name'] . ' on Tsumego',
			'image_alt' => 'Avatar of ' . $user['display_name'],
			'type' => 'profile',
			'profile_username' => $user['display_name'],
			'site_name' => 'Tsumego',
		];
		$avatarChoice = UserTable::avatarChoice($user);
		if ($avatarChoice === UserTable::AVATAR_BOARD)
			return $og + [
				'image' => Router::url(Avatar::pngUrl($user['display_name']), true),
				'image_type' => 'image/png',
				'image_width' => Avatar::SHARE_SIZE,
				'image_height' => Avatar::SHARE_SIZE];
		// an uploaded picture is stored at one size and type, so both are known
		if ($avatarChoice === UserTable::AVATAR_UPLOADED)
			return $og + [
				'image' => Router::url(UserTable::getAvatarUrl($user), true),
				'image_type' => 'image/webp',
				'image_width' => UploadedAvatar::SIZE,
				'image_height' => UploadedAvatar::SIZE];
		$og['image'] = UserTable::getAvatarUrl($user, Avatar::SHARE_SIZE);
		// Gravatar answers with the size it is asked for, so the size of the picture is known even though its format is not
		if ($avatarChoice === UserTable::AVATAR_GRAVATAR)
			$og += ['image_width' => Avatar::SHARE_SIZE, 'image_height' => Avatar::SHARE_SIZE];
		return $og;
	}

	//The quarters since the year 0 up to the quarter of the date, so the quarters between two dates are a difference
	private static function quarterIndex(string $date): int
	{
		return (int) substr($date, 0, 4) * 4 + intdiv((int) substr($date, 5, 2) - 1, 3);
	}
}
