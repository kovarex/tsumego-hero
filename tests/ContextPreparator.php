<?php

class ContextPreparator {
	public function __construct(?array $options = null) {
		$this->processOptions($options);
		if (!$this->user) {
			$this->user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		}

		if (!$this->tsumego) {
			$this->tsumego = ClassRegistry::init('Tsumego')->find();
			if (!$this->tsumego) {
				$this->tsumego = [];
				$this->tsumego['Tsumego']['description'] = 'test-tsumego';
				ClassRegistry::init('Tsumego')->save($this->tsumego);
				$this->tsumego = ClassRegistry::init('Tsumego')->find();
			}
		}
		$this->prepare();
	}

	private function processOptions(?array $options): void {
		if (!$options) {
			return;
		}
		if ($user = Util::extract('user', $options)) {
			$this->user = $user;
		}
		if ($tsumego = Util::extract('tsumego', $options)) {
			$this->tsumego = $tsumego;
		}
		if ($originalStatus = Util::extract('status', $options)) {
			$this->originalStatus = $originalStatus;
		}
		if ($originalTsumegoAttempt = Util::extract('tsumego_attempt', $options)) {
			$this->originalTsumegoAttempt = $originalTsumegoAttempt;
		}
		if ($mode = Util::extract('mode', $options)) {
			$this->mode = $mode;
		}
		if ($tsumegoSets = Util::extract('tsumego_sets', $options)) {
			$this->tsumegoSets = $tsumegoSets;
		}
		if (count($options)) {
			debug_print_backtrace();
			die("Option " . implode(",", array_keys($options)) . " not recognized\n.");
		}
	}

	private function prepare(): void {
		$this->prepareTsumegoAttempt();
		$this->prepareTsumegoStatus();
		$this->prepareUserMode();
		$this->prepareTsumegoSets();
	}

	private function prepareTsumegoAttempt(): void {
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

	private function prepareUserMode(): void {
		if ($this->mode && $this->user['User']['mode'] != $this->mode) {
			$this->user['User']['mode'] = $this->mode;
			ClassRegistry::init('User')->save($this->user);
		}
	}

	private function prepareTsumegoStatus(): void {
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

	private function prepareTsumegoSets(): void {
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
	public ?string $originalStatus = null; // null=delete relevant statutes, otherwise specifies string code of status to exist
	public ?array $originalTsumegoAttempt = null; // null=remove all relevant tsumego attempts
	public ?array $resultTsumegoStatus = null;
	public ?array $tsumegoSets = null;
}
