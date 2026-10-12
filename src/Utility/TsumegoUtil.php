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
		$finalCondition = ['conditions' => ['id' => $scIds]];
		if ($tsumegoConditions)
			$finalCondition['conditions'] [] = $tsumegoConditions;
		$ts = ClassRegistry::init('Tsumego')->find('all', $finalCondition) ?: [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++)
		{
			$ts[$i]['Tsumego']['set_id'] = $setID;
			$tsx[$scMap[$ts[$i]['Tsumego']['id']]] = $ts[$i];
		}

		return $tsx;
	}

	// Whether the problem is solved for the current mode. In time mode every
	// problem is presented fresh, so this is false at page load until solved.
	public static function isSolvedForCurrentMode(array $tsumego): bool
	{
		return !Auth::isInTimeMode() && TsumegoUtil::isRecentlySolved($tsumego['Tsumego']['status']);
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
