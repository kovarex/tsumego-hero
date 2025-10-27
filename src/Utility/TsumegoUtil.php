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
}
