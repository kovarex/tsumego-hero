<?php

namespace App\Utility;

use AchievementCondition;
use Set;

//Counts the sets a user completed, for the "complete N sets" achievements.
//Sets are counted in parts of $PARTITION_SIZE problems, the way the collections page splits
//them with the default collection size, regardless of the size the user picked. A set of
//1000 problems therefore counts as 5 sets.
//The count is kept in the achievement condition AchievementCondition::CATEGORY_COMPLETED_SETS.
class SetCompletionCounter
{
	// Deliberately not Constants::$DEFAULT_COLLECTION_SIZE: changing the default partitioning
	// of the collections page must not change how many sets the users have completed.
	public static int $PARTITION_SIZE = 200;

	public static function count(int $userID): int
	{
		$rows = Util::query('
SELECT COUNT(*) AS completed
FROM (
    SELECT 1
    FROM (' . self::partitionedProblemsSql('') . ') problems
    GROUP BY problems.set_id, problems.part
    HAVING MIN(problems.solved) = 1
) completed', [$userID]);
		return (int) $rows[0]['completed'];
	}

	//To be called when the user solved a problem that was not solved before.
	//Only the sets containing the problem are checked, and the full count is recomputed just
	//when the solve completed one of their parts, so a regular solve stays cheap.
	public static function updateAfterSolve(int $userID, int $tsumegoID): void
	{
		$completedPart = Util::query('
SELECT 1
FROM (' . self::partitionedProblemsSql('WHERE sc.set_id IN (SELECT set_id FROM set_connection WHERE tsumego_id = ?)') . ') problems
GROUP BY problems.set_id, problems.part
HAVING MIN(problems.solved) = 1 AND MAX(problems.tsumego_id = ?) = 1
LIMIT 1', [$userID, $tsumegoID, $tsumegoID]);
		if ($completedPart)
			self::save($userID, self::count($userID));
	}

	public static function save(int $userID, int $count): void
	{
		AchievementCondition::put($userID, AchievementCondition::CATEGORY_COMPLETED_SETS, $count);
	}

	//Every problem of the official public sets with the part of its set it falls into and whether
	//the user (the first query parameter) solved it. Problems are ordered within a set the same way
	//as on the collections page, so the parts match the cards shown there.
	private static function partitionedProblemsSql(string $where): string
	{
		return '
    SELECT
        sc.set_id,
        sc.tsumego_id,
        FLOOR((ROW_NUMBER() OVER (PARTITION BY sc.set_id ORDER BY sc.num, sc.tsumego_id) - 1) / ' . self::$PARTITION_SIZE . ') AS part,
        COALESCE(ts.status IN (\'S\', \'W\', \'C\'), 0) AS solved
    FROM set_connection sc
    JOIN `set` s ON s.id = sc.set_id AND ' . Set::officialCondition('s') . '
    JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
    LEFT JOIN tsumego_status ts ON ts.tsumego_id = sc.tsumego_id AND ts.user_id = ?
    ' . $where;
	}
}
