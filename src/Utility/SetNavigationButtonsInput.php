<?php

class SetNavigationButtonsInput {
	public function __construct($setFunction) {
		$this->setFunction = $setFunction;
	}

	public function execute(array $setConnections, array $currentSetConnection, array $tsumegoStatusMap): void {
		$this->collectFromSetConnections($setConnections, $currentSetConnection, $tsumegoStatusMap);
		$this->exportTooltips();
		$this->processJosekiLevel($currentSetConnection);
		($this->setFunction)('setNavigationButtonsInput', $this->result);
	}

	private static function constructFromSetConnection(array $setConnection, array $tsumegoStatusMap) {
		$result = [];
		$result['SetConnection'] = $setConnection['SetConnection'];
		$result['Tsumego'] = ClassRegistry::init('Tsumego')->findById($setConnection['SetConnection']['tsumego_id'])['Tsumego'];
		$status =  $tsumegoStatusMap[$setConnection['SetConnection']['tsumego_id']];
		$result['Tsumego']['status'] = $status ?: 'N';
		return $result;
	}

	private function collectFromSetConnections(array $setConnections, array $currentSetConnection, array $tsumegoStatusMap) {
		$currentIndex = array_find_key($setConnections, function ($setConnection) use ($currentSetConnection) { return $setConnection['SetConnection']['id'] === $currentSetConnection['SetConnection']['id']; });
		if (count($setConnections) <= 13) {
			foreach ($setConnections as $setConnection) {
				$this->result [] = self::constructFromSetConnection($setConnection, $tsumegoStatusMap);
			}
			return;
		}
		$startIndex = max(1, $currentIndex - self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE);
		$endIndex = min($startIndex + self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE * 2, count($setConnections) - 2);
		$startIndex = max(1, $endIndex - self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE * 2);
		$this->result [] = self::constructFromSetConnection($setConnections[0], $tsumegoStatusMap);
		for ($i = $startIndex; $i <= $endIndex; $i++) {
			$this->result [] = self::constructFromSetConnection($setConnections[$i], $tsumegoStatusMap);
		}
		$this->result [] = self::constructFromSetConnection($setConnections[count($setConnections) - 1], $tsumegoStatusMap);
	}

	private function exportTooltips() {
		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];

		foreach ($this->result as $item) {
			$tts = ClassRegistry::init('Sgf')->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $item['Tsumego']['id']]]);
			$tArr = AppController::processSGF($tts[0]['Sgf']['sgf']);
			$tooltipSgfs [] = $tArr[0];
			$tooltipInfo [] = $tArr[2];
			$tooltipBoardSize [] = $tArr[3];
		}

		($this->setFunction)('tooltipSgfs', $tooltipSgfs);
		($this->setFunction)('tooltipInfo', $tooltipInfo);
		($this->setFunction)('tooltipBoardSize', $tooltipBoardSize);
	}

	private function processJosekiLevel($setConnection) {
		$josekiLevel = 1;
		if ($setConnection['SetConnection']['set_id'] == 161) {
			$joseki = ClassRegistry::init('Joseki')->find('first', ['conditions' => ['tsumego_id' => $setConnection['SetConnection']['tsumego_id']]]);
			if ($joseki) {
				$josekiLevel = $joseki['Joseki']['hints'];
			} else {
				$josekiLevel = 0;
			}

			foreach ($this->result as &$item) {
				$j = ClassRegistry::init('Joseki')->find('first', ['conditions' => ['tsumego_id' => $item['Tsumego']['id']]]);
				if ($j) {
					$item['Tsumego']['type'] = $j['Joseki']['type'];
					$item['Tsumego']['thumbnail'] = $j['Joseki']['thumbnail'];
				}
			}
		}
		($this->setFunction)('josekiLevel', $josekiLevel);
	}

	private static $NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE = 5;
	private $result = [];
	private $setFunction;
}
