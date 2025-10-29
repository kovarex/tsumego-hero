<?php

class TsumegoButton {
	public static function constructFromSetConnection(array $setConnection, array $tsumegoStatusMap) {
		$result = [];
		$result['SetConnection'] = $setConnection['SetConnection'];
		$result['Tsumego'] = ClassRegistry::init('Tsumego')->findById($setConnection['SetConnection']['tsumego_id'])['Tsumego'];
		$status =  $tsumegoStatusMap[$setConnection['SetConnection']['tsumego_id']];
		$result['Tsumego']['status'] = $status ?: 'N';
		return $result;
	}

	public static function constructFromTsumego(array $tsumego, array $tsumegoStatusMap) {
		$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumego['Tsumego']['id'], 'user_id' => $tsumego['Tsumego']['user_id']]]);
		assert((bool) $setConnection); // TODO: can theoretically happen
		$result = [];
		$result['SetConnection'] = $setConnection['SetConnection'];
		$result['Tsumego'] = ClassRegistry::init('Tsumego')->findById($setConnection['SetConnection']['tsumego_id'])['Tsumego'];
		$status =  $tsumegoStatusMap[$setConnection['SetConnection']['tsumego_id']];
		$result['Tsumego']['status'] = $status ?: 'N';
		return $result;
	}
}
