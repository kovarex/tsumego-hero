<?php

class TsumegoUtil {
	public static function getMapForCurrentUser($conditions = null): array {
		if (!$conditions) {
			$conditions = [];
		}

		$conditions['user_id'] = Auth::getUserID();
		$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => $conditions]);
		if (!$statuses) {
			return [];
		}

		$result = [];
		foreach ($statuses as $status) {
			$result[$status['TsumegoStatus']['tsumego_id']] = $status['TsumegoStatus']['status'];
		}

		return $result;
	}

	public static function getSetConnectionsWithTitles(int $tsumegoID): ?array {
		$setConnections = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]);
		foreach ($setConnections as &$setConnection) {
			$duplicateSet = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
			$setConnection['SetConnection']['title'] = $duplicateSet['Set']['title'] . ' ' . $setConnection['SetConnection']['num'];
		}
		return $setConnections;
	}

	public static function collectTsumegosFromSet(int $setID, ?array $tsumegoConditions = null) {
		$scIds = [];
		$scMap = [];
		$tsx = [];
		$sc = ClassRegistry::init('SetConnection')->find('all', ['order' => 'num ASC', 'conditions' => ['set_id' => $setID]]) ?: [];
		$scCount = count($sc);
		for ($i = 0; $i < $scCount; $i++) {
			array_push($scIds, $sc[$i]['SetConnection']['tsumego_id']);
			$scMap[$sc[$i]['SetConnection']['tsumego_id']] = $i;
		}
		$finalCondition = ['conditions' => ['id' => $scIds]];
		if ($tsumegoConditions) {
			$finalCondition['conditions'] [] = $tsumegoConditions;
		}
		$ts = ClassRegistry::init('Tsumego')->find('all', $finalCondition) ?: [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$ts[$i]['Tsumego']['set_id'] = $setID;
			$tsx[$scMap[$ts[$i]['Tsumego']['id']]] = $ts[$i];
		}

		return $tsx;
	}

	public static function hasStateAllowingInspection($tsumego) {
		return TsumegoUtil::isStatusAllowingInspection($status = $tsumego['Tsumego']['status']);
	}

	public static function isStatusAllowingInspection($status) {
		return $status == 'S' || $status == 'C';
	}

	public static function getJavascriptMethodisStatusAllowingInspection() {
		$result = '\tfunction isStatusAllowingInspection(status)\n';
		$result .= '\t{\n';
		$result .= '\t\treturn status == \'S\' || status == \'C\';\n';
		$result .= '\t}\n';
		return $result;
	}
}
