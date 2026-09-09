<?php

class TsumegoStatus extends AppModel
{
	public static string $NOT_VISITED = 'N';
	public static string $VISITED = 'V';
	public static string $SOLVED = 'S';
	public static string $MASTERED = 'C';
	public static string $REVIEW = 'W';
	public static string $LOCKED = 'F';
	public static string $FORGOTTEN = 'X';
	public static string $GOLDEN = 'G';

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
			case self::$LOCKED: return 0; // failed
			case self::$NOT_VISITED: return 1; // nothing
			case self::$VISITED: return 2; // visited
			case self::$FORGOTTEN: return 3; // once solve but then failed
			case self::$SOLVED: return 4; // once solved
			case self::$REVIEW: return 5; // half XP after once solved
			case self::$MASTERED: return 6; // double solved
			case self::$GOLDEN: return 7; // golden tsumego
			default: throw new Exception("Unknown status: $status");
		}
	}

	public static array $labels = [
		'N' => 'Not visited',
		'V' => 'Visited',
		'S' => 'Solved',
		'F' => 'Locked',
		'W' => 'Review',
		'C' => 'Mastered',
		'X' => 'Forgotten',
		'G' => 'Golden',
	];

	public static array $descriptions = [
		'N' => 'You haven\'t seen this problem yet.',
		'V' => 'You have seen this problem but have not solved it.',
		'S' => 'You got it right. It gives no more XP and becomes available for Review after one week.',
		'F' => 'You can\'t play this problem today after misplaying with no hearts left. It resets to Visited tomorrow.',
		'W' => 'A week has passed since you solved this. Solve it for half XP. Get it right and it becomes Mastered, fail and it becomes Forgotten.',
		'C' => 'You passed the review. It gives no more XP.',
		'X' => 'You failed a Review. You are locked out for today. It resets to Review tomorrow.',
		'G' => 'You have one attempt at 8x XP. Created by activating the Refinement hero power.',
	];

	public static function label(string $status): string
	{
		return self::$labels[$status] ?? '';
	}

	public static function description(string $status): string
	{
		return self::$descriptions[$status] ?? '';
	}

}
