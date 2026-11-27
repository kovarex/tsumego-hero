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
			'COALESCE(tags.tag_count, 0) AS tag_count',
			'COALESCE(tags.tag_count, 0) DESC',
			join: 'LEFT JOIN (SELECT user_id, COUNT(*) AS tag_count FROM tag_connection WHERE approved = true GROUP BY user_id) tags
				ON tags.user_id = user.id',
			where: 'tags.user_id IS NOT NULL OR user.id = :listed_self');
	}

	//A player has an achievement once (the unique key of user_id and achievement_id), its value says how many times
	//a repeatable one was earned
	/** @return array<int, array<string, mixed>> */
	public static function achievements(): array
	{
		return self::ranked(
			'COALESCE(achievements.score, 0) AS achievement_score, COALESCE(achievements.earned_times, 0) AS achievement_earned_times',
			'COALESCE(achievements.score, 0) DESC, COALESCE(achievements.total, 0) DESC',
			join: 'LEFT JOIN (SELECT user_id, COUNT(*) AS score, SUM(CASE WHEN value > 1 THEN value ELSE 0 END) AS earned_times, SUM(value) AS total
				FROM achievement_status GROUP BY user_id) achievements ON achievements.user_id = user.id',
			where: 'achievements.user_id IS NOT NULL OR user.id = :listed_self');
	}

	//The best points of each player in time mode runs of the category and rank
	/** @return array<int, array<string, mixed>> */
	public static function timeMode(int $categoryID, int $rankID): array
	{
		return self::ranked(
			'COALESCE(best.points, 0) AS points',
			'COALESCE(best.points, 0) DESC',
			join: 'LEFT JOIN (SELECT user_id, MAX(points) AS points FROM time_mode_session
				WHERE time_mode_category_id = :category AND time_mode_rank_id = :rank GROUP BY user_id) best ON best.user_id = user.id',
			where: 'best.user_id IS NOT NULL OR user.id = :listed_self',
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
	//$scoreColumns: What the table shows besides the player, of the player's row or of $join
	//$join: A table of one row per player, grouped before it is joined: grouping the rows of every player after
	//joining them took three times as long (the achievements: 356 ms instead of 132 for 30000 players)
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
			WHERE ({$where}) AND user.deleted IS NULL";

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
