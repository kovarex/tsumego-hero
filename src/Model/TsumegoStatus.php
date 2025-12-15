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
}
