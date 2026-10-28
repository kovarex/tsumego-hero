<?php

namespace App\Utility;

use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\SetConnectionTable;

/** @phpstan-import-type TsumegoRow from \App\Utility\RowTypes */
class TsumegoUtil
{
	public static function getSetConnectionsWithTitles(int $tsumegoID): array
	{
		$rows = Util::query(
			'SELECT sc.*, s.title AS set_title '
			. 'FROM set_connection sc '
			. 'JOIN `set` s ON s.id = sc.set_id '
			. 'WHERE sc.tsumego_id = ? '
			. 'AND ' . SetConnectionTable::visibilitySql('s') . ' '
			. 'ORDER BY ' . SetConnectionTable::displayOrderSql('s', 'sc'),
			[$tsumegoID]
		);

		if (!$rows)
			return [];

		return array_map(fn($row) => $row + ['title' => $row['set_title'] . ' ' . $row['num']], $rows);
	}

	public static function collectTsumegosFromSet(int $setID): array
	{
		// the problems of the set in its order, the position of each in it
		$tsumegoIDs = array_column(Util::query('SELECT tsumego_id FROM set_connection WHERE set_id = ? ORDER BY num ASC', [$setID]), 'tsumego_id');
		$positions = array_flip($tsumegoIDs);
		// a problem in the trash is not in the set anymore
		$tsumegos = $tsumegoIDs ? Util::query('SELECT * FROM tsumego WHERE id IN (' . implode(',', array_fill(0, count($tsumegoIDs), '?')) . ') AND deleted IS NULL', $tsumegoIDs) : [];
		$result = [];
		foreach ($tsumegos as $tsumego)
			$result[$positions[$tsumego['id']]] = ['Tsumego' => $tsumego + ['set_id' => $setID]];

		// in the order of the set, and numbered from 0 even when some of its problems were left out
		ksort($result);
		return array_values($result);
	}

	//How many problems the set has. The same number as count(collectTsumegosFromSet()),
	//but the database counts them, so no tsumego rows are loaded into PHP.
	public static function countTsumegosFromSet(int $setID): int
	{
		$rows = Util::query(
			'SELECT COUNT(*) AS total FROM set_connection sc '
			. 'JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL '
			. 'WHERE sc.set_id = ?',
			[$setID]
		);

		return (int) $rows[0]['total'];
	}

	// Whether the problem is solved for the current mode. Time mode and mistake
	// training always present the problem as a fresh challenge, so this stays
	// false at page load even for a problem the player has already solved.
	public static function isSolvedForCurrentMode(string $status): bool
	{
		return !Auth::isInTimeMode()
			&& !Auth::isInMistakeTrainingMode()
			&& TsumegoUtil::isRecentlySolved($status);
	}

	public static function isRecentlySolved(?string $status): bool
	{
		return $status == TsumegoStatusTable::$SOLVED || $status == TsumegoStatusTable::$MASTERED;
	}

	public static function isSolvedStatus(?string $status): bool
	{
		return $status == TsumegoStatusTable::$SOLVED || $status == TsumegoStatusTable::$MASTERED || $status == TsumegoStatusTable::$REVIEW;
	}

	/** @param TsumegoRow $tsumego */
	public static function getXpValue(array $tsumego, float $multiplier = 1.0): int
	{
		return Rating::ratingToXP($tsumego['rating'], $multiplier);
	}
}
