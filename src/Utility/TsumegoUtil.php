<?php

namespace App\Utility;

use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SetTable;

/** @phpstan-import-type TsumegoRow from \App\Utility\RowTypes */
class TsumegoUtil
{
	//Where Play leads: the problem the player opened last, for a first visit the first problem of
	//the site's collections in their order, and the collections page while the site has none
	public static function playUrl(): string
	{
		$tsumegoID = Util::cookie('lastVisit') ?? self::firstProblemID();
		return $tsumegoID === null ? '/sets' : '/tsumegos/play/' . (int) $tsumegoID;
	}

	//The first problem, not in the trash, of the first site collection in the order of the
	//collections page, null when there is none. The layout asks on every page of a first visit, so
	//the collections are walked in order and each is read through its (set_id, num) index.
	public static function firstProblemID(): ?int
	{
		foreach (Util::query('SELECT id FROM `set` s WHERE ' . SetTable::officialCondition('s') . ' ORDER BY (s.order IS NULL), s.order, s.id') as $set)
		{
			$rows = Util::query('SELECT sc.tsumego_id FROM set_connection sc
JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL
WHERE sc.set_id = ?
ORDER BY sc.num, sc.id
LIMIT 1', [$set['id']]);
			if ($rows)
				return (int) $rows[0]['tsumego_id'];
		}
		return null;
	}

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

	//How many problems the set has, the ones in the trash left out.
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
		return in_array($status, TsumegoStatusTable::solvedStatuses(), true);
	}

	/** @param TsumegoRow $tsumego */
	public static function getXpValue(array $tsumego, float $multiplier = 1.0): int
	{
		return Rating::ratingToXP($tsumego['rating'], $multiplier);
	}
}
