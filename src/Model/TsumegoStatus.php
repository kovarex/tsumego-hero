<?php

class TsumegoStatus extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tsumego_status';
		parent::__construct($id, $table, $ds);
	}

	// when two statuses are to be merged, we need to decide which one is more valuable to keep for the user
	public static function less($status1, $status2)
	{
		return self::value($status1) < self::value($status2);
	}

	private static function value($status): int
	{
		switch ($status)
		{
			case 'F' : return 0; // failed
			case 'N' : return 1; // nothing
			case 'V' : return 2; // visited
			case 'X' : return 3; // once solve but then failed
			case 'S' : return 4; // once solved
			case 'W' : return 5; // half XP after once solved
			case 'C' : return 6; // double solved
			case 'G' : return 7; // golden tsumego
			default: throw new Exception("Unknown status: $status");
		}
	}

	public static function getProblemsSolvedInSet($setID)
	{
		return Util::query("
SELECT
	COUNT(DISTINCT tsumego_status.id) AS total
FROM
	tsumego_status
	JOIN set_connection ON tsumego_status.tsumego_id = set_connection.tsumego_id AND set_connection.set_id = ?
WHERE
	tsumego_status.user_id = ? AND
	tsumego_status.status IN ('S', 'C', 'W')", [$setID, Auth::getUserID()])[0]["total"];
	}
}
