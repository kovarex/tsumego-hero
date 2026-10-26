<?php

namespace App\Utility;

use ClassRegistry;
use SetConnection;
use TsumegoStatus;

/**
 * @phpstan-import-type TsumegoRow from \App\Utility\RowTypes
 */
class TsumegoUtil
{
	public static function getSetConnectionsWithTitles(int $tsumegoID): array
	{
		$rows = Util::query(
			'SELECT sc.*, s.title AS set_title '
			. 'FROM set_connection sc '
			. 'JOIN `set` s ON s.id = sc.set_id '
			. 'WHERE sc.tsumego_id = ? '
			. 'AND ' . SetConnection::visibilitySql('s') . ' '
			. 'ORDER BY ' . SetConnection::displayOrderSql('s', 'sc'),
			[$tsumegoID]
		);

		if (!$rows)
			return [];

		return array_map(fn($row) => ['SetConnection' => array_merge($row, [
			'title' => $row['set_title'] . ' ' . $row['num'],
		])], $rows);
	}

	public static function collectTsumegosFromSet(int $setID, ?array $tsumegoConditions = null): array
	{
		$scIds = [];
		$scMap = [];
		$tsx = [];
		$sc = ClassRegistry::init('SetConnection')->find('all', ['order' => 'num ASC', 'conditions' => ['set_id' => $setID]]) ?: [];
		$scCount = count($sc);
		for ($i = 0; $i < $scCount; $i++)
		{
			array_push($scIds, $sc[$i]['SetConnection']['tsumego_id']);
			$scMap[$sc[$i]['SetConnection']['tsumego_id']] = $i;
		}
		// a problem in the trash is not in the set anymore
		$finalCondition = ['conditions' => ['id' => $scIds, 'deleted IS NULL']];
		if ($tsumegoConditions)
			$finalCondition['conditions'] [] = $tsumegoConditions;
		$ts = ClassRegistry::init('Tsumego')->find('all', $finalCondition) ?: [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++)
		{
			$ts[$i]['Tsumego']['set_id'] = $setID;
			$tsx[$scMap[$ts[$i]['Tsumego']['id']]] = $ts[$i];
		}

		// in the order of the set, and numbered from 0 even when some of its problems were left out
		ksort($tsx);
		return array_values($tsx);
	}

	/**
	 * How many problems the set has. The same number as count(collectTsumegosFromSet()),
	 * but the database counts them, so no tsumego rows are loaded into PHP.
	 */
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
	public static function isSolvedForCurrentMode(array $tsumego): bool
	{
		return !Auth::isInTimeMode()
			&& !Auth::isInMistakeTrainingMode()
			&& TsumegoUtil::isRecentlySolved($tsumego['Tsumego']['status']);
	}

	public static function isRecentlySolved(?string $status): bool
	{
		return $status == TsumegoStatus::$SOLVED || $status == TsumegoStatus::$MASTERED;
	}

	public static function isSolvedStatus(?string $status): bool
	{
		return $status == TsumegoStatus::$SOLVED || $status == TsumegoStatus::$MASTERED || $status == TsumegoStatus::$REVIEW;
	}

	/**
	 * @param TsumegoRow $tsumego
	 */
	public static function getXpValue(array $tsumego, float $multiplier = 1.0): int
	{
		return Rating::ratingToXP($tsumego['rating'], $multiplier);
	}
}
