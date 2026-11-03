<?php

namespace App\Utility;

//The rows of the highscore tables (UsersController's highscore pages, rendered by HighscoreHelper).
//A table lists the top players and the few around the signed-in player, each with its position.
class Highscore
{
	//What every table shows of a player: the link with the avatar and the rank, and the premium badge
	private const string PLAYER_COLUMNS = 'user.id, user.display_name, user.external_id, user.picture, user.avatar_setting_id,
		user.uploaded_avatar_hash, user.email, user.rating, user.premium_id';

	//How many players above and below the signed-in one are listed when they are not among the top
	private const int NEIGHBOR_RADIUS = 3;

	/** @return array<int, array<string, mixed>> */
	public static function level(): array
	{
		return self::ranked('user.level, user.xp, user.solved', 'user.level DESC, user.xp DESC', topRows: 1000);
	}

	/** @return array<int, array<string, mixed>> */
	public static function rating(): array
	{
		return self::ranked('', 'user.rating DESC');
	}

	/** @return array<int, array<string, mixed>> */
	public static function daily(): array
	{
		return self::ranked('user.daily_xp, user.daily_solved', 'user.daily_xp DESC', where: 'user.daily_xp > 0 OR user.id = :listed_self');
	}

	/** @return array<int, array<string, mixed>> */
	public static function tags(): array
	{
		return self::ranked(
			'COUNT(tag_connection.id) AS tag_count',
			'COUNT(tag_connection.id) DESC',
			join: 'LEFT JOIN tag_connection ON tag_connection.user_id = user.id AND tag_connection.approved = true',
			where: 'tag_connection.id IS NOT NULL OR user.id = :listed_self');
	}

	/** @return array<int, array<string, mixed>> */
	public static function achievements(): array
	{
		return self::ranked(
			'COUNT(DISTINCT achievement_status.achievement_id) AS achievement_score,
				COALESCE(SUM(CASE WHEN achievement_status.value > 1 THEN achievement_status.value ELSE 0 END), 0) AS achievement_earned_times',
			'COUNT(DISTINCT achievement_status.achievement_id) DESC, COALESCE(SUM(achievement_status.value), 0) DESC',
			join: 'LEFT JOIN achievement_status ON achievement_status.user_id = user.id',
			where: 'achievement_status.id IS NOT NULL OR user.id = :listed_self');
	}

	//The best points of each player in time mode runs of the category and rank
	/** @return array<int, array<string, mixed>> */
	public static function timeMode(int $categoryID, int $rankID): array
	{
		return self::ranked(
			'COALESCE(MAX(time_mode_session.points), 0) AS points',
			'COALESCE(MAX(time_mode_session.points), 0) DESC',
			join: 'LEFT JOIN time_mode_session ON time_mode_session.user_id = user.id
				AND time_mode_session.time_mode_category_id = :category
				AND time_mode_session.time_mode_rank_id = :rank',
			where: 'time_mode_session.id IS NOT NULL OR user.id = :listed_self',
			params: ['category' => $categoryID, 'rank' => $rankID]);
	}

	//The ids of the ranks a time mode run of the category was played at, as keys
	/** @return array<int, true> */
	public static function playedTimeModeRankIDs(int $categoryID): array
	{
		$rankIDs = [];
		foreach (Util::query('SELECT DISTINCT time_mode_rank_id FROM time_mode_session WHERE time_mode_category_id = ?', [$categoryID]) as $row)
			$rankIDs[(int) $row['time_mode_rank_id']] = true;
		return $rankIDs;
	}

	public static function activePlayerCount(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM user WHERE deleted IS NULL')[0]['count'];
	}

	public static function dailyPlayerCount(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM user WHERE daily_xp > 0 AND deleted IS NULL')[0]['count'];
	}

	//The top rows of the players ordered by $order, and the rows around the signed-in player
	//$scoreColumns: What the table shows besides the player, aggregates of $join grouped by the player
	//$where: Which players are ranked (besides not being deleted), :listed_self is the signed-in player
	/**
	 * @param array<string, mixed> $params
	 * @return array<int, array<string, mixed>>
	 */
	private static function ranked(string $scoreColumns, string $order, string $join = '', string $where = 'TRUE', array $params = [], int $topRows = 30): array
	{
		$selfID = Auth::isLoggedIn() ? Auth::getUserID() : -1;
		if (str_contains($where, ':listed_self'))
			$params['listed_self'] = $selfID;
		//The ranking sorts only the ids and the scores, the shown columns of the listed players are joined after it:
		//sorting all the players with their names and avatars took ten times as long. The id breaks the ties, so the
		//two statements rank the players the same way.
		$ranking = "
			SELECT user.id" . ($scoreColumns === '' ? '' : ', ' . $scoreColumns) . ", ROW_NUMBER() OVER (ORDER BY {$order}, user.id) AS position
			FROM user {$join}
			WHERE ({$where}) AND user.deleted IS NULL
			" . ($join === '' ? '' : 'GROUP BY user.id');

		$listed = 'ranked.position <= ' . $topRows;
		$selfPosition = $selfID < 0 ? [] : Util::query("SELECT position FROM ({$ranking}) ranked WHERE ranked.id = :self", $params + ['self' => $selfID]);
		if (!empty($selfPosition))
		{
			$params['neighbors_from'] = $selfPosition[0]['position'] - self::NEIGHBOR_RADIUS;
			$params['neighbors_to'] = $selfPosition[0]['position'] + self::NEIGHBOR_RADIUS;
			$listed .= ' OR ranked.position BETWEEN :neighbors_from AND :neighbors_to';
		}
		return Util::query("
			SELECT " . self::PLAYER_COLUMNS . ", ranked.*
			FROM ({$ranking}) ranked JOIN user ON user.id = ranked.id
			WHERE {$listed}
			ORDER BY ranked.position",
			$params);
	}
}
