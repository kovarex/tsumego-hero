<?php

class ContextPreparator {
	public function __construct(?array $options = []) {
		$this->prepareUser(Util::extract('user', $options));
		$this->prepareThisTsumego(Util::extract('tsumego', $options));
		$this->prepareOtherTsumegos(Util::extract('other-tsumegos', $options));
		$this->prepareUserMode(Util::extract('mode', $options));
		$this->prepareTimeModeRanks(Util::extract('time-mode-ranks', $options));
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
		$this->user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		if ($user) {
			if ($user['rating']) {
				$this->user['rating'] = $user['rating'];
			}
			if ($user['mode']) {
				$this->user['mode'] = $user['mode'];
			}
			ClassRegistry::init('User')->save($this->user);
			CakeSession::write('loggedInUserID', $this->user['id']);
		} else {
			CakeSession::destroy();
		}

		ClassRegistry::init('UserContribution')->deleteAll(['user_id' => $this->user['id']]);
	}

	private function prepareTsumego(?array $tsumegoInput): array {
		if ($tsumegoInput) {
			if (!$tsumegoInput['id']) {
				ClassRegistry::init('Tsumego')->create($tsumegoInput);
				ClassRegistry::init('Tsumego')->save($tsumegoInput);
				$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => ['id' => 'DESC']])['Tsumego'];
				assert($tsumego['id']);
			}
		} else {
			$tsumego = ClassRegistry::init('Tsumego')->find()['Tsumego'];
			if (!$tsumego) {
				$tsumego = [];
				$tsumego['Tsumego']['description'] = 'test-tsumego';
				ClassRegistry::init('Tsumego')->save($tsumego);
				$tsumego  = ClassRegistry::init('Tsumego')->find()['Tsumego'];
			}
		}
		$this->prepareTsumegoSets($tsumegoInput['sets'], $tsumego);
		$this->prepareTsumegoStatus($tsumegoInput['status'], $tsumego);
		$this->prepareTsumegoAttempt($tsumegoInput['attempt'], $tsumego);
		return $tsumego;
	}

	private function prepareThisTsumego(?array $tsumego): void {
		$this->tsumego = $this->prepareTsumego($tsumego);
		$this->allTsumegos [] = $this->tsumego;
	}

	private function prepareOtherTsumegos(?array $tsumegos): void {
		foreach ($tsumegos as $tsumego) {
			$tsumego = $this->prepareTsumego($tsumego);
			$this->otherTsumegos [] = $tsumego;
			$this->allTsumegos [] = $tsumego;
		}
	}

	private function prepareTsumegoAttempt(?array $tsumegoAttempt, $tsumego): void {
		ClassRegistry::init('TsumegoAttempt')->deleteAll(['user_id' => $this->user['id'],'tsumego_id' => $tsumego['id']]);
		if (!$tsumegoAttempt) {
			return;
		}

		$tsumegoAttempt['TsumegoAttempt']['user_id'] = $this->user['id'];
		$tsumegoAttempt['TsumegoAttempt']['elo'] = $this->user['rating'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $tsumego['id'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $tsumegoAttempt['solved'] ?: false;
		$tsumegoAttempt['TsumegoAttempt']['mode'] = $this->user['mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $tsumego['rating'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] = $tsumegoAttempt['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private function prepareUserMode($mode): void {
		if (!$mode || $this->user['User']['mode'] == $this->mode) {
			return;
		}

		$this->user['mode'] = $mode;
		ClassRegistry::init('User')->save($this->user);
	}

	private function prepareTsumegoStatus($tsumegoStatus, $tsumego): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				['tsumego_id' => $tsumego['id']],
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
			$originalTsumegoStatus['TsumegoStatus']['status'] = $tsumegoStatus;
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $this->user['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $tsumego['id'];
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}
	}

	private function prepareTsumegoSets($setsInput, &$tsumego): void {
		if ($setsInput) {
			ClassRegistry::init('SetConnection')->deleteAll(['tsumego_id' => $tsumego['id']]);
			$this->tsumegoSets = [];
			foreach ($setsInput as $tsumegoSet) {
				$set = $this->getOrCreateTsumegoSet($tsumegoSet['name']);
				$setConnection = [];
				$setConnection['SetConnection']['tsumego_id'] = $tsumego['id'];
				$setConnection['SetConnection']['set_id'] = $set['id'];
				$setConnection['SetConnection']['num'] = $tsumegoSet['num'];
				ClassRegistry::init('SetConnection')->create($setConnection);
				ClassRegistry::init('SetConnection')->save($setConnection);
				$setConnection = ClassRegistry::init('SetConnection')->find('first', ['order' => ['id' => 'DESC']])['SetConnection'];
				$tsumego['sets'] [] = $set;
				$tsumego['set-connections'] [] = $setConnection;
			}
		}
	}

	private function getOrCreateTsumegoSet($name): array {
		$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		if (!$set) {
			$set = [];
			$set['Set']['title'] = $name;
			ClassRegistry::init('Set')->create($set);
			ClassRegistry::init('Set')->save($set);
			// reloading so the generated id is retrieved
			ClassRegistry::init('SetConnection')->create($set);
			$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		}
		$this->checkSetClear($set['Set']['id']);
		return $set['Set'];
	}

	private function checkSetClear(int $setID): void {
		if ($this->setsCleared[$setID]) {
			return;
		}
		ClassRegistry::init('SetConnection')->deleteAll(['set_id' => $setID]);
		$this->setsCleared[$setID] = true;
	}

	private function prepareTimeModeRanks($timeModeRanks): void {
		ClassRegistry::init('TimeModeSession')->deleteAll(['1 = 1']);
		assert(ClassRegistry::init('TimeModeSession')->find('count') == 0);
		ClassRegistry::init('TimeModeRank')->deleteAll(['1 = 1']);
		assert(ClassRegistry::init('TimeModeRank')->find('count') == 0);
		foreach ($timeModeRanks as $timeModeRankInput) {
			$timeModeRank = [];
			$timeModeRank['name'] = $timeModeRankInput;
			ClassRegistry::init('TimeModeRank')->create($timeModeRank);
			ClassRegistry::init('TimeModeRank')->save($timeModeRank);
			$timeModeRank = ClassRegistry::init('TimeModeRank')->find('first', ['order' => 'id DESC'])['TimeModeRank'];
			$this->timeModeRanks [] = $timeModeRank;
		}
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				'tsumego_id' => $this->tsumego['id']],
		];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition)['TsumegoStatus'];
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['user_id'], $this->user['id']);
		$testCase->assertSame($this->resultTsumegoStatus['tsumego_id'], $this->tsumego['id']);
	}

	public ?array $user = null;
	public ?array $tsumego = null;
	public ?array $otherTsumegos = [];
	public ?array $allTsumegos = [];
	public ?int $mode = null;
	public ?array $resultTsumegoStatus = null;
	public ?array $tsumegoSets = null;
	public ?array $timeModeRanks = [];

	private array $setsCleared = []; // map of IDs of sets already cleared this run. Exists to avoid sets having leftovers from previous runs
}
