<?php

class ContextPreparator {
	public function __construct(?array $options = []) {
		$this->prepareUser(Util::extract('user', $options));
		$this->prepareThisTsumego(Util::extract('tsumego', $options));
		$this->prepareOtherTsumegos(Util::extract('other-tsumegos', $options));
		$this->prepareTimeModeRanks(Util::extract('time-mode-ranks', $options));
		$this->prepareTimeModeSessions(Util::extract('time-mode-sessions', $options));
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
		if (!$this->user) {
			$this->user = [];
			$this->user['name'] = 'kovarex';
			$this->user['email'] = 'test@example.com';
			$this->user['rating'] = 1500;
			$this->user['damage'] = 0;
			$this->user['password_hash'] = '$2y$10$5.F2n794IrgFcLRBnE.rju1ZoJheRr1fVc4SYq5ICeaJG0C800TRG'; // hash of test
			ClassRegistry::init('User')->create($this->user);
		}

		$this->user['isAdmin'] = $user['admin'] ?? false;
		$this->user['rating'] = $user['rating'] ?: 1500;
		$this->user['mode'] = $user['mode'] ?: Constants::$LEVEL_MODE;
		$this->user['damage'] = 0;
		ClassRegistry::init('User')->save($this->user);
		$this->user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];

		ClassRegistry::init('UserContribution')->deleteAll(['user_id' => $this->user['id']]);

		if ($user) {
			CakeSession::write('loggedInUserID', $this->user['id']);
			assert(CakeSession::check('loggedInUserID'));
			Auth::init();

			if (isset($user['query'])
				|| isset($user['filtered_sets'])
				|| isset($user['filtered_topics'])
				|| isset($user['filtered_tags'])
				|| isset($user['collection_size'])) {
				$userContribution = [];
				$userContribution['user_id'] = $this->user['id'];
				$userContribution['query'] = $user['query'] ?: '';
				$userContribution['filtered_sets'] = $user['filtered_sets'] ? implode('@', $user['filtered_sets']) : '';
				$userContribution['filtered_ranks'] = $user['filtered_ranks'] ? implode('@', $user['filtered_ranks']) : '';
				$userContribution['filtered_tags'] = $user['filtered_tags'] ? implode('@', $user['filtered_tags']) : '';
				$userContribution['collection_size'] = $user['collection_size'] ?: 200;
				ClassRegistry::init('UserContribution')->create($userContribution);
				ClassRegistry::init('UserContribution')->save($userContribution);
			}
		} else {
			CakeSession::destroy();
		}

		// Achievements popups can get into the way when testing, once we want to test achievements
		// we can make this command conditional
		$_COOKIE['disable-achievements'] = true;
	}

	private function prepareTsumego(?array $tsumegoInput): array {
		if ($tsumegoInput) {
			if (!$tsumegoInput['id']) {
				$tsumegoInput['deleted'] = $tsumegoInput['deleted'] ?: null;
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
		$this->prepareTsumegoTags($tsumegoInput['tags'], $tsumego);
		$this->prepareTsumegoStatus($tsumegoInput['status'], $tsumego);
		$this->prepareTsumegoAttempt($tsumegoInput['attempt'], $tsumego);
		return $tsumego;
	}

	private function prepareThisTsumego(?array $tsumego): void {
		if (is_null($tsumego)) {
			return;
		}
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

	private function prepareTsumegoStatus($tsumegoStatus, $tsumego): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				['tsumego_id' => $tsumego['id']]]];
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
			ClassRegistry::init('TsumegoStatus')->create($originalTsumegoStatus);
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

	private function prepareTsumegoTags($tagsInput, &$tsumego): void {
		if ($tagsInput) {
			ClassRegistry::init('Tag')->deleteAll(['tsumego_id' => $tsumego['id']]);
			foreach ($tagsInput as $tagInput) {
				$tag = $this->getOrCreateTag($tagInput['name']);
				$tagConnection = [];
				$tagConnection['Tag']['tsumego_id'] = $tsumego['id'];
				$tagConnection['Tag']['user_id'] = $this->user['id'];
				$tagConnection['Tag']['tag_name_id'] = $tag['id'];
				ClassRegistry::init('Tag')->create($tagConnection);
				ClassRegistry::init('Tag')->save($tagConnection);
				$tagConnection = ClassRegistry::init('Tag')->find('first', ['order' => ['id' => 'DESC']])['SetConnection'];
				$tsumego['tags'] [] = $tag;
				$tsumego['tag-connections'] [] = $tagConnection;
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
			$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		}
		$this->checkSetClear($set['Set']['id']);
		return $set['Set'];
	}

	private function getOrCreateTag($name): array {
		$tag  = ClassRegistry::init('TagName')->find('first', ['conditions' => ['name' => $name]]);
		if (!$tag) {
			$tag = [];
			$tag['name'] = $name;
			ClassRegistry::init('TagName')->create($tag);
			ClassRegistry::init('TagName')->save($tag);
			// reloading so the generated id is retrieved
			$tag  = ClassRegistry::init('TagName')->find('first', ['conditions' => ['name' => $name]]);
		}
		return $tag['TagName'];
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

	private function prepareTimeModeSessions($timeModeSessions): void {
		ClassRegistry::init('TimeModeSession')->deleteAll(['1 = 1']);
		foreach ($timeModeSessions as $timeModeSessionInput) {
			$timeModeSession = [];
			$timeModeSession['user_id'] = Auth::getUserID();
			$timeModeSession['time_mode_category_id'] = $timeModeSessionInput['category'];
			$timeModeSession['time_mode_session_status_id'] = $timeModeSessionInput['status'];
			$rank = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => $timeModeSessionInput['rank']]]);
			$timeModeSession['time_mode_rank_id'] = $rank['TimeModeRank']['id'];
			ClassRegistry::init('TimeModeSession')->create($timeModeSession);
			ClassRegistry::init('TimeModeSession')->save($timeModeSession);
			$newSession = ClassRegistry::init('TimeModeSession')->find('first', ['order' => 'id DESC'])['TimeModeSession'];
			$this->timeModeSessions [] = $newSession;
			foreach ($timeModeSessionInput['attempts'] as $attemptInput) {
				$this->prepareTimeModeAttempts($attemptInput, $newSession['id']);
			}
		}
	}

	private function prepareTimeModeAttempts(array $attemptsInput, int $timeModeSessionID): void {
		$attempt = [];
		$attempt['time_mode_session_id'] = $timeModeSessionID;
		if (empty($this->allTsumegos)) {
			throw Exception("No tsumego assign to the time mode attempt");
		}
		$attempt['tsumego_id'] = $this->allTsumegos[0]['id'];
		$attempt['order'] = $attemptsInput['order'];
		$attempt['time_mode_attempt_status_id'] = $attemptsInput['status'];
		ClassRegistry::init('TimeModeAttempt')->create($attempt);
		ClassRegistry::init('TimeModeAttempt')->save($attempt);
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				'tsumego_id' => $this->tsumego['id']]];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition)['TsumegoStatus'];
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['user_id'], $this->user['id']);
		$testCase->assertSame($this->resultTsumegoStatus['tsumego_id'], $this->tsumego['id']);
	}

	public ?array $user = null;
	public ?array $tsumego = null;
	public array $otherTsumegos = [];
	public array $allTsumegos = [];
	public ?int $mode = null;
	public ?array $resultTsumegoStatus = null;
	public ?array $tsumegoSets = null;
	public array $timeModeRanks = [];
	public array $timeModeSessions = [];
	public array $tags = [];

	private array $setsCleared = []; // map of IDs of sets already cleared this run. Exists to avoid sets having leftovers from previous runs
}
