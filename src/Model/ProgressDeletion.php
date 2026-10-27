<?php

use App\Utility\Util;

//A reset of the user's progress on one part of a set. The resets reduce the XP of the problems of
//that part for a month.
class ProgressDeletion extends AppModel
{
	// A reset clears one part of a set: its problems ordered by number, in parts of this size. Resetting
	// is only possible with this collection size and without rank and tag filters, so a part is the
	// same for everybody.
	public static int $PARTITION_SIZE = 200;

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'progress_deletion';
		parent::__construct($id, $table, $ds);
	}

	//The set and part of a set connection. The problems of a set are ordered by number with the
	//deleted ones left out, the same way the set page shows them without filters.
	//$tsumegoID: when given, null is returned unless the set connection is of this problem
	/** @return array{set_id: int, partition: int}|null */
	public static function partitionOf(int $setConnectionID, ?int $tsumegoID = null): ?array
	{
		$rows = Util::query('
SELECT sc.set_id, sc.tsumego_id, (
    SELECT COUNT(*)
    FROM set_connection other
    JOIN tsumego ON tsumego.id = other.tsumego_id AND tsumego.deleted IS NULL
    WHERE other.set_id = sc.set_id AND (other.num < sc.num OR (other.num = sc.num AND other.id < sc.id))
) AS position
FROM set_connection sc
WHERE sc.id = ?', [$setConnectionID]);
		if (!$rows || ($tsumegoID !== null && (int) $rows[0]['tsumego_id'] !== $tsumegoID))
			return null;
		return ['set_id' => (int) $rows[0]['set_id'], 'partition' => intdiv((int) $rows[0]['position'], self::$PARTITION_SIZE)];
	}

	//How many times the user reset the part of the set in the last month. Resets saved before the
	//part was recorded count for every part of their set.
	public static function countRecent(int $userID, int $setID, int $partition): int
	{
		return (int) Util::query('
SELECT COUNT(*) AS resets
FROM progress_deletion
WHERE user_id = ? AND set_id = ? AND (set_partition = ? OR set_partition IS NULL) AND created >= NOW() - INTERVAL 1 MONTH',
			[$userID, $setID, $partition])[0]['resets'];
	}

	//The resets reducing the XP of a problem played from the set connection: those of its part. When
	//the set connection isn't of the problem, the problem's display set connection is used instead.
	public static function countRecentForProblem(int $userID, int $tsumegoID, ?int $setConnectionID): int
	{
		$partition = $setConnectionID ? self::partitionOf($setConnectionID, $tsumegoID) : null;
		if (!$partition && ($displaySetConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoID)))
			$partition = self::partitionOf((int) $displaySetConnection['SetConnection']['id']);
		return $partition ? self::countRecent($userID, $partition['set_id'], $partition['partition']) : 0;
	}
}
