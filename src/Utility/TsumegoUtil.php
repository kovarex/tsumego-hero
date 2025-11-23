<?php

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

	public static function getSetConnectionsWithTitles(int $tsumegoID): ?array
	{
		$setConnections = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]);
		foreach ($setConnections as &$setConnection)
		{
			$duplicateSet = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
			$setConnection['SetConnection']['title'] = $duplicateSet['Set']['title'] . ' ' . $setConnection['SetConnection']['num'];
		}
		return $setConnections;
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
		return $status == 'S' || $status == 'C';
	}

	public static function isSolvedStatus($status)
	{
		return $status == 'S' || $status == 'C' || $status == 'W';
	}

	public static function getJavascriptMethodisStatusAllowingInspection()
	{
		$result = '\tfunction isStatusAllowingInspection(status)\n';
		$result .= '\t{\n';
		$result .= '\t\treturn status == \'S\' || status == \'C\';\n';
		$result .= '\t}\n';
		return $result;
	}

	public static function getXpValue(array $tsumego, float $multiplier = 1.0): int
	{
		return intval(ceil(Rating::ratingToXP($tsumego['rating']) * $multiplier));
	}

	public static function getProgressDeletionCount(array $tsumego): int
	{
		$result = ClassRegistry::init('ProgressDeletion')->query('
SELECT COUNT(*) AS deletions_count
FROM (
    SELECT DISTINCT progress_deletion.id
    FROM progress_deletion
    JOIN set_connection ON set_connection.set_id = progress_deletion.set_id
    WHERE set_connection.tsumego_id = ' . $tsumego['id'] . ' AND progress_deletion.created >= NOW() - INTERVAL 1 MONTH
) AS unique_deletions');
		return $result[0][0]['deletions_count'];
	}
}
