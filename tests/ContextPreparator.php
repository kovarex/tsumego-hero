<?php

class ContextPreparator {
	public function __construct(?array $user = null, ?array $tsumego = null) {
		$this->user = $user;
		if (!$this->user) {
			$this->user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		}

		$this->tsumego = $tsumego;
		if (!$this->tsumego) {
			$this->tsumego = ClassRegistry::init('Tsumego')->find('first');
			if (!$this->tsumego) {
				$this->tsumego = [];
				$this->tsumego['Tsumego']['description'] = 'test-tsumego';
				ClassRegistry::init('Tsumego')->save($this->tsumego);
				$this->tsumego = ClassRegistry::init('Tsumego')->find('first');
			}
		}
	}

	public function setStatus(string $originalStatus): ContextPreparator {
		$this->originalStatus = $originalStatus;
		return $this;
	}

	public function setAttempt($originalTsumegoAttempt): ContextPreparator {
		$this->originalTsumegoAttempt = $originalTsumegoAttempt;
		return $this;
	}

	public function setMode($mode): ContextPreparator {
		$this->mode = $mode;
		return $this;
	}

	public function setTsumegoSets(array $tsumegoSets): ContextPreparator {
		$this->tsumegoSets = $tsumegoSets;
		return $this;
	}

	public function prepare(): ContextPreparator {
		$this->prepareTsumegoAttempt();
		$this->prepareTsumegoStatus();
		$this->prepareUserMode();
		$this->prepareTsumegoSets();
		return $this;
	}

	public function prepareTsumegoAttempt(): void {
		ClassRegistry::init('TsumegoAttempt')->deleteAll(['user_id' => $this->user['User']['id'],'tsumego_id' => $this->tsumego['Tsumego']['id']]);
		if (!$this->originalTsumegoAttempt) {
			return;
		}

		$tsumegoAttempt = [];
		$tsumegoAttempt['TsumegoAttempt']['user_id'] = $this->user['User']['id'];
		$tsumegoAttempt['TsumegoAttempt']['elo'] = $this->user['User']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $this->originalTsumegoAttempt['solved'] ?: false;
		$tsumegoAttempt['TsumegoAttempt']['mode'] = $this->user['User']['mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $this->tsumego['Tsumego']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] = $this->originalTsumegoAttempt['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	public function prepareUserMode(): void {
		if ($this->mode && $this->user['User']['mode'] != $this->mode) {
			$this->user['User']['mode'] = $this->mode;
			ClassRegistry::init('User')->save($this->user);
		}
	}

	public function prepareTsumegoStatus(): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['User']['id'],
				['tsumego_id' => $this->tsumego['Tsumego']['id']],
			],
		];
		$originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		if ($originalTsumegoStatus) {
			if (!$this->originalStatus) {
				ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);
			} else {
				$originalTsumegoStatus['TsumegoStatus']['status'] = $this->originalStatus;
				ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
			}
		} elseif ($this->originalStatus) {
			$originalTsumegoStatus = [];
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $this->user['User']['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}
	}

	public function prepareTsumegoSets(): void {
		if ($this->tsumegoSets) {
			ClassRegistry::init('SetConnection')->deleteAll(['tsumego_id' => $this->tsumego['Tsumego']['id']]);
			foreach ($this->tsumegoSets as $tsumegoSet) {
				$set = $this->getOrCreateTsumegoSet($tsumegoSet['name']);
				$setConnection = [];
				$setConnection['SetConnection']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
				$setConnection['SetConnection']['set_id'] = $set['Set']['id'];
				$setConnection['SetConnection']['num'] = $tsumegoSet['num'];
				ClassRegistry::init('SetConnection')->create($setConnection);
				ClassRegistry::init('SetConnection')->save($setConnection);
			}
		}
	}

	public function getOrCreateTsumegoSet($name) {
		$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		if (!$set) {
			$set = [];
			$set['Set']['title'] = $name;
			ClassRegistry::init('Set')->save($set);
			// reloading so the generated id is retrived
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

	public $user;
	public $tsumego;
	public $mode;
	public $originalStatus; // null=delete relevant statatus, oterwise specifies string code of status to exist
	public $originalTsumegoAttempt; // null=remove all relevant tsumego attempts
	public $resultTsumegoStatus;
	public $tsumegoSets;
}
