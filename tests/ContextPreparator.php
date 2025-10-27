<?php

class ContextPreparator {
	public function __construct(?array $options = []) {
		$this->prepareUser(Util::extract('user', $options));
		$this->prepareTsumego(Util::extract('tsumego', $options));
		$this->prepareTsumegoStatus(Util::extract('tsumego_status', $options));
		$this->prepareTsumegoAttempt(Util::extract('tsumego_attempt', $options));
		$this->prepareUserMode(Util::extract('mode', $options));
		$this->prepareTsumegoSets(Util::extract('tsumego_sets', $options));
		$this->checkOptionsConsumed($options);
	}

	private function checkOptionsConsumed(?array $options) {
		if (!count($options)) {
			return;
		}
		debug_print_backtrace();
		die("Option " . implode(",", array_keys($options)) . " not recognized\n.");
	}

	private function prepareUser(?array $user): void {
		$this->user = $user ?: ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
	}

	private function prepareTsumego(?array $tsumego): void {
		if ($tsumego) {
			$this->tsumego = $tsumego;
			if (!$tsumego['Tsumego']['id']) {
				ClassRegistry::init('Tsumego')->create($this->tsumego);
				ClassRegistry::init('Tsumego')->save($this->tsumego);
				$this->tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => ['id' => 'DESC']]);
				assert($this->tsumego['Tsumego']['id']);
			}
		} else {
			$this->tsumego = ClassRegistry::init('Tsumego')->find();
			if (!$this->tsumego) {
				$this->tsumego = [];
				$this->tsumego['Tsumego']['description'] = 'test-tsumego';
				ClassRegistry::init('Tsumego')->save($this->tsumego);
				$this->tsumego = ClassRegistry::init('Tsumego')->find();
			}
		}
	}

	private function prepareTsumegoAttempt(?array $tsumegoAttempt): void {
		ClassRegistry::init('TsumegoAttempt')->deleteAll(['user_id' => $this->user['User']['id'],'tsumego_id' => $this->tsumego['Tsumego']['id']]);
		if (!$tsumegoAttempt) {
			return;
		}

		$this->tsumegoAttempt = [];
		$tsumegoAttempt['TsumegoAttempt']['user_id'] = $this->user['User']['id'];
		$tsumegoAttempt['TsumegoAttempt']['elo'] = $this->user['User']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $tsumegoAttempt['solved'] ?: false;
		$tsumegoAttempt['TsumegoAttempt']['mode'] = $this->user['User']['mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $this->tsumego['Tsumego']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] = $tsumegoAttempt['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private function prepareUserMode($mode): void {
		if (!$mode || $this->user['User']['mode'] == $this->mode) {
			return;
		}

		$this->user['User']['mode'] = $mode;
		ClassRegistry::init('User')->save($this->user);
	}

	private function prepareTsumegoStatus($tsumegoStatus): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['User']['id'],
				['tsumego_id' => $this->tsumego['Tsumego']['id']],
			],
		];
		$originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		if ($originalTsumegoStatus) {
			if (!$tsumegoStatus) {
				ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);
			} else {
				$originalTsumegoStatus['TsumegoStatus']['status'] = $tsumegoStatus;
				ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
			}
		} elseif ($tsumegoStatus) {
			$originalTsumegoStatus = [];
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $this->user['User']['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}
	}

	private function prepareTsumegoSets($setsInput): void {
		if ($setsInput) {
			ClassRegistry::init('SetConnection')->deleteAll(['tsumego_id' => $this->tsumego['Tsumego']['id']]);
			$this->tsumegoSetConnections = [];
			$this->tsumegoSets = [];
			foreach ($setsInput as $tsumegoSet) {
				$set = $this->getOrCreateTsumegoSet($tsumegoSet['name']);
				$setConnection = [];
				$setConnection['SetConnection']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
				$setConnection['SetConnection']['set_id'] = $set['Set']['id'];
				$setConnection['SetConnection']['num'] = $tsumegoSet['num'];
				ClassRegistry::init('SetConnection')->create($setConnection);
				ClassRegistry::init('SetConnection')->save($setConnection);
				$this->tsumegoSets[] = $set;
				$this->tsumegoSetConnections[] = $setConnection;
			}
		}
	}

	private function getOrCreateTsumegoSet($name): array {
		$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		if (!$set) {
			$set = [];
			$set['Set']['title'] = $name;
			ClassRegistry::init('Set')->save($set);
			// reloading so the generated id is retrieved
			ClassRegistry::init('SetConnection')->create($set);
			$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		}
		return $set;
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['User']['id'],
				['tsumego_id' => $this->tsumego['Tsumego']['id']],
			],
		];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['TsumegoStatus']['user_id'], $this->user['User']['id']);
		$testCase->assertSame($this->resultTsumegoStatus['TsumegoStatus']['tsumego_id'], $this->tsumego['Tsumego']['id']);
	}

	public ?array $user = null;
	public ?array $tsumego = null;
	public ?int $mode = null;
	public ?array $tsumegoAttempt = null;
	public ?array $resultTsumegoStatus = null;
	public ?array $tsumegoSets = null;
	public ?array $tsumegoSetConnections = null;
}
