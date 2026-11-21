<?php

namespace App\Model\Table;

use App\Utility\Util;

//What a player reached so far towards the achievements that take more than one solve: a value per
//player and category, and per part of a set for the two set records (the parts a reset clears,
//ProgressDeletionTable::$PARTITION_SIZE). Each exists once (a unique key), and the methods below write it,
//creating it when it is not there yet.
class AchievementConditionTable extends AppTable
{
	// the rows of achievement_condition_category, what the value counts
	public const CATEGORY_COMPLETED_SETS = 1; // sets completed, see SetCompletionCounter
	public const CATEGORY_SET_TIME = 2; // the best average seconds per problem on a part of the set
	public const CATEGORY_SET_ACCURACY = 3; // the best accuracy on a part of the set, in percent
	public const CATEGORY_NO_ERROR_STREAK = 4; // problems solved in a row without a mistake
	public const CATEGORY_POTION = 5; // potions drunk on the player's day, DailyReset sets it back
	public const CATEGORY_SPRINT = 6; // problems solved in a row during a sprint
	public const CATEGORY_GOLDEN = 7; // golden problems solved in a row
	public const CATEGORY_USER_OF_THE_DAY = 8; // 1 once the player was the user of the day
	public const CATEGORY_DAN_SOLVE_1D = 9; // problems of the rank solved
	public const CATEGORY_DAN_SOLVE_2D = 10;
	public const CATEGORY_DAN_SOLVE_3D = 11;
	public const CATEGORY_DAN_SOLVE_4D = 12;
	public const CATEGORY_DAN_SOLVE_5D = 13;
	public const CATEGORY_EMERALD = 14; // 1 once the player found the emerald of a day
	public const CATEGORY_SAPPHIRE = 15;
	public const CATEGORY_RUBY = 16;

	//The categories counting the solved problems of a dan rank, by the rank.
	public const DAN_SOLVE_CATEGORIES = [
		'1d' => self::CATEGORY_DAN_SOLVE_1D,
		'2d' => self::CATEGORY_DAN_SOLVE_2D,
		'3d' => self::CATEGORY_DAN_SOLVE_3D,
		'4d' => self::CATEGORY_DAN_SOLVE_4D,
		'5d' => self::CATEGORY_DAN_SOLVE_5D];

	//The value of the player's condition, null when the player has none.
	//$partition: The part of the set, for the set records
	public static function value(int $userID, int $categoryID, ?int $setID = null, ?int $partition = null): ?float
	{
		$rows = Util::query(
			'SELECT value FROM achievement_condition WHERE user_id = ? AND achievement_condition_category_id = ? AND set_id_or_zero = ? AND set_partition_or_zero = ?',
			[$userID, $categoryID, $setID ?? 0, $partition ?? 0]);
		return $rows && $rows[0]['value'] !== null ? (float) $rows[0]['value'] : null;
	}

	//The values of the player's conditions of the categories that are not per set.
	/**
	 * @param int[] $categoryIDs
	 * @return array<int, float> By category, the ones the player has
	 */
	public static function values(int $userID, array $categoryIDs): array
	{
		if (!$categoryIDs)
			return [];
		$rows = Util::query(
			'SELECT achievement_condition_category_id AS category_id, value FROM achievement_condition
			WHERE user_id = ? AND set_id IS NULL AND achievement_condition_category_id IN (' . implode(',', array_fill(0, count($categoryIDs), '?')) . ')',
			[$userID, ...$categoryIDs]);
		$values = [];
		foreach ($rows as $row)
			$values[(int) $row['category_id']] = (float) $row['value'];
		return $values;
	}

	//$partition: The part of the set, for the set records
	public static function put(int $userID, int $categoryID, float $value, ?int $setID = null, ?int $partition = null): void
	{
		Util::execute(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, set_id, set_partition, value) VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE value = VALUES(value)',
			[$userID, $categoryID, $setID, $partition, $value]);
	}

	//Adds one to the value of the player's condition, which starts at zero.
	public static function increment(int $userID, int $categoryID): void
	{
		Util::execute(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, value) VALUES (?, ?, 1)
			ON DUPLICATE KEY UPDATE value = COALESCE(value, 0) + 1',
			[$userID, $categoryID]);
	}

	//Keeps the lower of the stored value and the given one, for a record like the best time.
	//$partition: The part of the set, for the set records
	public static function keepLowest(int $userID, int $categoryID, float $value, ?int $setID = null, ?int $partition = null): void
	{
		Util::execute(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, set_id, set_partition, value) VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE value = LEAST(COALESCE(value, VALUES(value)), VALUES(value))',
			[$userID, $categoryID, $setID, $partition, $value]);
	}

	//Keeps the higher of the stored value and the given one, for a record like the best accuracy.
	//$partition: The part of the set, for the set records
	public static function keepHighest(int $userID, int $categoryID, float $value, ?int $setID = null, ?int $partition = null): void
	{
		Util::execute(
			'INSERT INTO achievement_condition (user_id, achievement_condition_category_id, set_id, set_partition, value) VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE value = GREATEST(COALESCE(value, VALUES(value)), VALUES(value))',
			[$userID, $categoryID, $setID, $partition, $value]);
	}

	//The player's best accuracies of the parts of sets, 100 when the part was solved without a mistake
	/**
	 * @param int[] $setIDs
	 * @return array<int, array<int, float>> By set and part
	 */
	public static function bestAccuracies(int $userID, array $setIDs): array
	{
		if (!$setIDs)
			return [];
		$best = [];
		foreach (Util::query('SELECT set_id, set_partition_or_zero AS part, value FROM achievement_condition
			WHERE user_id = ? AND achievement_condition_category_id = ? AND set_id IN (' . implode(',', array_fill(0, count($setIDs), '?')) . ')',
			[$userID, self::CATEGORY_SET_ACCURACY, ...$setIDs]) as $row)
				$best[(int) $row['set_id']][(int) $row['part']] = (float) $row['value'];
		return $best;
	}
}
