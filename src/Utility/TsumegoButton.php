<?php

class TsumegoButton {
	public static function construct($setConnection, $tsumegoStatusMap) {
		$result = [];
		$result['SetConnection'] = $setConnection['SetConnection'];
		$result['Tsumego'] = ClassRegistry::init('Tsumego')->findById($setConnection['SetConnection']['tsumego_id'])['Tsumego'];
		$result['Tsumego']['status'] = $tsumegoStatusMap[$setConnection['SetConnection']['tsumego_id']];
		return $result;
	}
}
