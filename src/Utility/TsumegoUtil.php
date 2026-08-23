<?php

App::uses('SetConnection', 'Model');
App::uses('TsumegoStatus', 'Model');

class TsumegoUtil
{
	public static function getMapForCurrentUser($conditions = null): array
	{
		if (!$conditions)
			$conditions = [];

		$conditions['user_id'] = Auth::getUserID();
		$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => $conditions]);
		if (!$statuses)
			return [];

		$result = [];
		foreach ($statuses as $status)
			$result[$status['TsumegoStatus']['tsumego_id']] = $status['TsumegoStatus']['status'];

		return $result;
	}

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

	public static function collectTsumegosFromSet(int $setID, ?array $tsumegoConditions = null)
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

	public static function hasStateAllowingInspection($tsumego)
	{
		return TsumegoUtil::isRecentlySolved($tsumego['Tsumego']['status']);
	}

	public static function isRecentlySolved($status)
	{
		return $status == TsumegoStatus::$SOLVED || $status == TsumegoStatus::$MASTERED;
	}

	public static function isSolvedStatus($status)
	{
		return $status == TsumegoStatus::$SOLVED || $status == TsumegoStatus::$MASTERED || $status == TsumegoStatus::$REVIEW;
	}

	public static function getXpValue(array $tsumego, float $multiplier = 1.0): int
	{
		return Rating::ratingToXP($tsumego['rating'], $multiplier);
	}

	public static function getProgressDeletionCount(array $tsumego): int
	{
		$result = ClassRegistry::init('ProgressDeletion')->query('
SELECT COUNT(*) AS deletions_count
FROM (
    SELECT DISTINCT progress_deletion.id
    FROM progress_deletion
    JOIN set_connection ON set_connection.set_id = progress_deletion.set_id
    WHERE set_connection.tsumego_id = ' . $tsumego['id'] . ' AND progress_deletion.user_id=' . Auth::getUserID() . ' AND progress_deletion.created >= NOW() - INTERVAL 1 MONTH
) AS unique_deletions');
		return $result[0][0]['deletions_count'];
	}
}
