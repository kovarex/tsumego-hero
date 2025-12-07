<?php

class ContextPreparator
{
	public function __construct(?array $options = [])
	{
		ClassRegistry::init('TagConnection')->deleteAll(['1 = 1']);      // FK to: user, tag
		ClassRegistry::init('Tag')->deleteAll(['1 = 1']);                // FK to: user
		ClassRegistry::init('Schedule')->deleteAll(['1 = 1']);           // FK to: Tsumego, Set
		ClassRegistry::init('ProgressDeletion')->deleteAll(['1 = 1']);   // FK to: User, Set
		ClassRegistry::init('DayRecord')->deleteAll(['1 = 1']);          // FK to: User
		ClassRegistry::init('Sgf')->deleteAll(['1 = 1']);                // FK to: User, Tsumego
		ClassRegistry::init('TimeModeAttempt')->deleteAll(['1 = 1']);    // FK to: TimeModeSession
		ClassRegistry::init('TimeModeSession')->deleteAll(['1 = 1']);    // FK to: User, TimeModeRank
		ClassRegistry::init('TsumegoComment')->deleteAll(['1 = 1']);     // FK to: User
		ClassRegistry::init('TsumegoIssue')->deleteAll(['1 = 1']);       // FK to: User
		ClassRegistry::init('AdminActivity')->deleteAll(['1 = 1']);      // FK to: User, Tsumego, Set
		ClassRegistry::init('AchievementCondition')->deleteAll(['1 = 1']);  // FK to: User, Set
		ClassRegistry::init('User')->deleteAll(['1 = 1']);               // Parent table
		ClassRegistry::init('TimeModeRank')->deleteAll(['1 = 1']);       // Parent table
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);            // Parent table
		ClassRegistry::init('Set')->deleteAll(['1 = 1']);                // Parent table

		if (!array_key_exists('user', $options) && !array_key_exists('other-users', $options))
			$this->prepareThisUser(['name' => 'kovarex']);
		else
			$this->prepareThisUser(Util::extract('user', $options));
		$this->prepareOtherUsers(Util::extract('other-users', $options));
		$this->prepareThisTsumego(Util::extract('tsumego', $options));
		$this->prepareOtherTsumegos(Util::extract('other-tsumegos', $options));
		$this->prepareTimeModeRanks(Util::extract('time-mode-ranks', $options));
		$this->prepareTimeModeSessions(Util::extract('time-mode-sessions', $options));
		$this->prepareProgressDeletion(Util::extract('progress-deletions', $options));
		$this->prepareDayRecords(Util::extract('day-records', $options));
		$this->prepareAdminActivities(Util::extract('admin-activities', $options));
		$this->prepareTags(Util::extract('tags', $options));
		$this->checkOptionsConsumed($options);
	}

	private function checkOptionsConsumed(?array $options)
	{
		if (!count($options))
			return;
		debug_print_backtrace();
		die("Option " . implode(",", array_keys($options)) . " not recognized\n.");
	}

	private function prepareThisUser(?array $user): void
	{
		if (!$user)
		{
			Auth::logout();
			unset($_COOKIE['hackedLoggedInUserID']);
			return;
		}
		$this->user = $this->prepareUser($user);

		// Set hackedLoggedInUserID for test environment auth
		$_COOKIE['hackedLoggedInUserID'] = $this->user['id'];

		Auth::init();
		// Achievements popups can get into the way when testing, once we want to test achievements
		// we can make this command conditional
		$_COOKIE['disable-achievements'] = true;
	}

	private function prepareUser(?array $userInput): array
	{
		$user = [];
		$user['name'] = Util::extract('name', $userInput) ?: 'kovarex';
		$user['email'] = Util::extract('email', $userInput) ?: 'test@example.com';
		$user['password_hash'] = '$2y$10$5.F2n794IrgFcLRBnE.rju1ZoJheRr1fVc4SYq5ICeaJG0C800TRG'; // hash of test
		$user['isAdmin'] = Util::extract('admin', $userInput) ?? false;
		$user['rating'] = Util::extract('rating', $userInput) ?: 1500;
		$user['premium'] = Util::extract('premium', $userInput) ?: 0;
		$user['solved'] = Util::extract('solved', $userInput) ?: 0;
		$user['xp'] = Util::extract('xp', $userInput) ?: 0;
		$user['daily_xp'] = Util::extract('daily_xp', $userInput) ?: 0;
		$user['daily_solved'] = Util::extract('daily_solved', $userInput) ?: 0;
		foreach ([
			'used_refinement',
			'used_sprint',
			'used_rejuvenation',
			'used_potion',
			'used_intuition',
			'used_revelation'] as $name)
				$user[$name] = Util::extract($name, $userInput) ?: 0;
		$user['level'] = Util::extract('level', $userInput) ?: 1;
		if ($health = Util::extract('health', $userInput))
			$user['damage'] = Util::getHealthBasedOnLevel($user['level']) - $health;
		else
			$user['damage'] = Util::extract('damage', $userInput) ?? 0;
		$user['sprint_start'] = Util::extract('sprint_start', $userInput) ?: null;
		$user['mode'] = Util::extract('mode', $userInput) ?: Constants::$LEVEL_MODE;
		if ($lastTimeModeCategoryID = Util::extract('last-time-mode-category-id', $userInput))
			$user['last_time_mode_category_id'] = $lastTimeModeCategoryID;
		ClassRegistry::init('User')->create($user);
		ClassRegistry::init('User')->save($user);
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $user['name']]])['User'];

		ClassRegistry::init('UserContribution')->deleteAll(['user_id' => $this->user['id']]);

		if (isset($userInput['query'])
			|| isset($userInput['filtered_sets'])
			|| isset($userInput['filtered_topics'])
			|| isset($userInput['filtered_tags'])
			|| isset($userInput['collection_size']))
		{
			$userContribution = [];
			$userContribution['user_id'] = $user['id'];
			$userContribution['query'] = Util::extract('query', $userInput) ?: '';
			$userContribution['filtered_sets'] = $userInput['filtered_sets'] ? implode('@', Util::extract('filtered_sets', $userInput)) : '';
			$userContribution['filtered_ranks'] = $userInput['filtered_ranks'] ? implode('@', Util::extract('filtered_ranks', $userInput)) : '';
			$userContribution['filtered_tags'] = $userInput['filtered_tags'] ? implode('@', Util::extract('filtered_tags', $userInput)) : '';
			$userContribution['collection_size'] = Util::extract('collection_size', $userInput) ?: 200;
			ClassRegistry::init('UserContribution')->create($userContribution);
			ClassRegistry::init('UserContribution')->save($userContribution);
		}
		$this->checkOptionsConsumed($userInput);
		return $user;
	}

	private function prepareOtherUsers(?array $usersInput): void
	{
		if (!$usersInput)
			return;
		foreach ($usersInput as $userInput)
			$this->otherUsers [] = $this->prepareUser($userInput);
	}

	private function prepareTsumego(?array $tsumegoInput): array
	{
		$tsumego = [];
		$tsumego['description'] = Util::extract('description', $tsumegoInput) ?: 'test-tsumego';
		$tsumego['hint'] = Util::extract('hint', $tsumegoInput) ?: '';
		$tsumego['rating'] = Util::extract('rating', $tsumegoInput) ?: 1000;
		$tsumego['minimum_rating'] = Util::extract('minimum_rating', $tsumegoInput) ?: null;
		$tsumego['maximum_rating'] = Util::extract('maximum_rating', $tsumegoInput) ?: null;
		$tsumego['deleted'] = Util::extract('deleted', $tsumegoInput);
		$tsumego['author'] = Util::extract('author', $tsumegoInput) ?: '';
		ClassRegistry::init('Tsumego')->create($tsumego);
		ClassRegistry::init('Tsumego')->save($tsumego);
		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => ['id' => 'DESC']])['Tsumego'];
		assert($tsumego['id']);

		$this->prepareTsumegoSets(Util::extract('sets', $tsumegoInput), $tsumego);
		$this->prepareTsumegoTags(Util::extract('tags', $tsumegoInput), $tsumego);
		$this->prepareTsumegoStatus(Util::extract('status', $tsumegoInput), $tsumego);
		$this->prepareTsumegoAttempt(Util::extract('attempt', $tsumegoInput), $tsumego);
		$singleSgf = Util::extract('sgf', $tsumegoInput);
		$multipleSgfs = Util::extract('sgfs', $tsumegoInput);
		if ($singleSgf)
			$this->prepareTsumegoSgf($singleSgf, $tsumego);
		if ($multipleSgfs)
			$this->prepareTsumegoSgfs($multipleSgfs, $tsumego);
		if (!$singleSgf && !$multipleSgfs)
			$this->prepareTsumegoSgf(self::defaultSgf(), $tsumego);
		$this->prepareTsumegoComments(Util::extract('comments', $tsumegoInput), $tsumego);
		$this->prepareTsumegoIssues(Util::extract('issues', $tsumegoInput), $tsumego);
		$this->checkOptionsConsumed($tsumegoInput);
		return $tsumego;
	}

	private function prepareThisTsumego(?array $tsumego): void
	{
		if (is_null($tsumego))
			return;
		$this->tsumego = $this->prepareTsumego($tsumego);
		$this->allTsumegos [] = $this->tsumego;
	}

	private function prepareOtherTsumegos(?array $tsumegos): void
	{
		foreach ($tsumegos as $tsumego)
		{
			$tsumego = $this->prepareTsumego($tsumego);
			$this->otherTsumegos [] = $tsumego;
			$this->allTsumegos [] = $tsumego;
		}
	}

	private function prepareTsumegoAttempt(?array $tsumegoAttempt, $tsumego): void
	{
		ClassRegistry::init('TsumegoAttempt')->deleteAll(['user_id' => $this->user['id'],'tsumego_id' => $tsumego['id']]);
		if (!$tsumegoAttempt)
			return;

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

	private function prepareTsumegoSgf(?string $tsumegoSgf, $tsumego): void
	{
		if (!$tsumegoSgf)
			return;
		$sgf = [];
		ClassRegistry::init('Sgf')->create($sgf);
		$sgf['tsumego_id'] = $tsumego['id'];
		$sgf['sgf'] = $tsumegoSgf;
		ClassRegistry::init('Sgf')->save($sgf);
	}

	private function prepareTsumegoSgfs(?array $tsumegoSgfs, $tsumego): void
	{
		if (!$tsumegoSgfs)
			return;
		foreach ($tsumegoSgfs as $tsumegoSgf)
			$this->prepareTsumegoSgf($tsumegoSgf, $tsumego);
	}

	private static function defaultSgf(): string
	{
		return '(;SZ[19])';
	}

	private function prepareTsumegoComments(?array $tsumegoComments, $tsumego): void
	{
		if (!$tsumegoComments)
			return;
		foreach ($tsumegoComments as $tsumegoComment)
			$this->prepareTsumegoComment($tsumegoComment, $tsumego);
	}

	private function prepareTsumegoComment(array $commentInput, $tsumego, ?int $issueId = null): void
	{
		ClassRegistry::init('TsumegoComment')->create();
		$comment = [];
		$comment['message'] = Util::extract('message', $commentInput);
		$comment['tsumego_id'] = $tsumego['id'];
		$comment['user_id'] = $this->user['id'];
		if ($issueId !== null)
			$comment['tsumego_issue_id'] = $issueId;
		ClassRegistry::init('TsumegoComment')->save($comment);
		$this->checkOptionsConsumed($commentInput);
	}

	private function prepareTsumegoIssues(?array $tsumegoIssues, $tsumego): void
	{
		if (!$tsumegoIssues)
			return;
		foreach ($tsumegoIssues as $tsumegoIssue)
			$this->prepareTsumegoIssue($tsumegoIssue, $tsumego);
	}

	private function prepareTsumegoIssue(array $issueInput, $tsumego): void
	{
		App::uses('TsumegoIssue', 'Model');

		// Create the issue
		ClassRegistry::init('TsumegoIssue')->create();
		$issue = [];
		$issue['tsumego_id'] = $tsumego['id'];
		$issue['user_id'] = $this->user['id'];
		$issue['tsumego_issue_status_id'] = Util::extract('status', $issueInput) ?: TsumegoIssue::$OPENED_STATUS;
		ClassRegistry::init('TsumegoIssue')->save($issue);

		// Get the created issue ID
		$createdIssue = ClassRegistry::init('TsumegoIssue')->find('first', ['order' => 'id DESC']);
		$issueId = $createdIssue['TsumegoIssue']['id'];

		// Create initial comment for the issue
		$message = Util::extract('message', $issueInput);
		if ($message)
			$this->prepareTsumegoComment(['message' => $message], $tsumego, $issueId);

		$this->checkOptionsConsumed($issueInput);
	}

	private function prepareTsumegoStatus($tsumegoStatus, $tsumego): void
	{
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				['tsumego_id' => $tsumego['id']]]];
		$statusValue = $tsumegoStatus ? (is_string($tsumegoStatus) ? $tsumegoStatus : $tsumegoStatus['name']) : null;
		$updated = $tsumegoStatus ? (is_string($tsumegoStatus) ? null : $tsumegoStatus['updated']) : null;
		$originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		if ($originalTsumegoStatus)
			if (!$tsumegoStatus)
				ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);
			else
			{
				$originalTsumegoStatus['TsumegoStatus']['status'] = $statusValue;
				if ($updated)
					$originalTsumegoStatus['TsumegoStatus']['updated'] = $updated;
				ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
			}
		elseif ($tsumegoStatus)
		{
			$originalTsumegoStatus = [];
			$originalTsumegoStatus['TsumegoStatus']['status'] = $statusValue;
			if ($updated)
				$originalTsumegoStatus['TsumegoStatus']['updated'] = $updated;
			$originalTsumegoStatus['TsumegoStatus']['status'] = $statusValue;
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $this->user['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $tsumego['id'];
			ClassRegistry::init('TsumegoStatus')->create($originalTsumegoStatus);
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}
	}

	private function prepareTsumegoSets($setsInput, &$tsumego): void
	{
		if (!$setsInput)
			return;

		ClassRegistry::init('SetConnection')->deleteAll(['tsumego_id' => $tsumego['id']]);
		$this->tsumegoSets = [];
		foreach ($setsInput as $tsumegoSet)
		{
			$set = $this->getOrCreateTsumegoSet([
				'name' => Util::extract('name', $tsumegoSet),
				'included_in_time_mode' => Util::extract('included_in_time_mode', $tsumegoSet),
				'public' => Util::extract('public', $tsumegoSet),
				'premium' => $tsumegoSet['premium'] ?? 0]);
			unset($tsumegoSet['premium']);  // Mark as consumed
			$setConnection = [];
			$setConnection['SetConnection']['tsumego_id'] = $tsumego['id'];
			$setConnection['SetConnection']['set_id'] = $set['id'];
			$setConnection['SetConnection']['num'] = Util::extract('num', $tsumegoSet);
			ClassRegistry::init('SetConnection')->create($setConnection);
			ClassRegistry::init('SetConnection')->save($setConnection);
			$setConnection = ClassRegistry::init('SetConnection')->find('first', ['order' => ['id' => 'DESC']])['SetConnection'];
			$tsumego['sets'] [] = $set;
			$tsumego['set-connections'] [] = $setConnection;
			$this->checkOptionsConsumed($tsumegoSet);
		}
	}

	private function prepareTsumegoTags($tagsInput, &$tsumego): void
	{
		if (!$tagsInput)
			return;

		ClassRegistry::init('TagConnection')->deleteAll(['tsumego_id' => $tsumego['id']]);
		foreach ($tagsInput as $tagInput)
		{
			$tag = $this->getOrCreateTag([
				'name' => Util::extract('name', $tagInput),
				'popular' => Util::extract('popular', $tagInput) ?: false,
				'is_hint' => Util::extract('is_hint', $tagInput) ?: 0]);
			$tagConnection = [];
			$tagConnection['TagConnection']['tsumego_id'] = $tsumego['id'];
			$tagConnection['TagConnection']['user_id'] = self::getUserIdFromName(Util::extract('user', $tagInput)) ?: $this->user['id'];
			$tagConnection['TagConnection']['tag_id'] = $tag['id'];
			$approved = Util::extract('approved', $tagInput);
			$tagConnection['TagConnection']['approved'] = !is_null($approved) ? $approved : 1;
			ClassRegistry::init('TagConnection')->create($tagConnection);
			ClassRegistry::init('TagConnection')->save($tagConnection);
			$tagConnection = ClassRegistry::init('TagConnection')->find('first', ['order' => ['id' => 'DESC']])['TagConnection'];
			$tsumego['tags'] [] = $tag;
			$tsumego['tag-connections'] [] = $tagConnection;
			$this->checkOptionsConsumed($tagInput);
		}
	}

	private function getOrCreateTsumegoSet($input): array
	{
		if (is_string($input))
		{
			$name = $input;
			$includedInTimeMode = false;
			$public = 1;
			$premium = 0;
		}
		else
		{
			$name = $input['name'];
			$includedInTimeMode = $input['included_in_time_mode'];
			$public = $input['public'];
			$premium = $input['premium'] ?? 0;
		}
		$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		if (!$set)
		{
			$set = [];
			$set['Set']['title'] = $name;
			$set['Set']['included_in_time_mode'] = is_null($includedInTimeMode) ? true : $includedInTimeMode;
			$set['Set']['public'] = is_null($public) ? true : $public;
			$set['Set']['premium'] = $premium;
			ClassRegistry::init('Set')->create($set);
			ClassRegistry::init('Set')->save($set);
			// reloading so the generated id is retrieved
			$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		}
		$this->checkSetClear($set['Set']['id']);
		return $set['Set'];
	}

	private function getOrCreateTag($tagInput): array
	{
		$name = Util::extract('name', $tagInput);
		$tag  = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $name]]);
		if (!$tag)
		{
			$tag = [];
			$tag['popular'] = Util::extract('popular', $tagInput) ?: false;
			$tag['hint'] = Util::extract('is_hint', $tagInput) ?: 0;
			$tag['name'] = $name;
			ClassRegistry::init('Tag')->create($tag);
			ClassRegistry::init('Tag')->save($tag);
			// reloading so the generated id is retrieved
			$tag  = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $name]]);
		}
		else
		{
			Util::extract('popular', $tagInput);
			Util::extract('is_hint', $tagInput);
		}

		$this->checkOptionsConsumed($tagInput);
		$this->tags[] = $tag['Tag'];
		return $tag['Tag'];
	}

	private function checkSetClear(int $setID): void
	{
		if ($this->setsCleared[$setID])
			return;
		ClassRegistry::init('SetConnection')->deleteAll(['set_id' => $setID]);
		$this->setsCleared[$setID] = true;
	}

	private function prepareTimeModeRanks($timeModeRanks): void
	{
		foreach ($timeModeRanks as $timeModeRankInput)
		{
			$timeModeRank = [];
			$timeModeRank['name'] = $timeModeRankInput;
			ClassRegistry::init('TimeModeRank')->create($timeModeRank);
			ClassRegistry::init('TimeModeRank')->save($timeModeRank);
			$timeModeRank = ClassRegistry::init('TimeModeRank')->find('first', ['order' => 'id DESC'])['TimeModeRank'];
			$this->timeModeRanks [] = $timeModeRank;
		}
	}

	private function prepareTimeModeSessions($timeModeSessions): void
	{
		ClassRegistry::init('TimeModeSession')->deleteAll(['1 = 1']);
		foreach ($timeModeSessions as $timeModeSessionInput)
		{
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
			foreach ($timeModeSessionInput['attempts'] as $attemptInput)
				$this->prepareTimeModeAttempts($attemptInput, $newSession['id']);
		}
	}

	private function prepareTimeModeAttempts(array $attemptsInput, int $timeModeSessionID): void
	{
		$attempt = [];
		$attempt['time_mode_session_id'] = $timeModeSessionID;
		if (empty($this->allTsumegos))
			throw new Exception("No tsumego assign to the time mode attempt");
		$attempt['tsumego_id'] = $this->allTsumegos[0]['id'];
		$attempt['order'] = $attemptsInput['order'];
		$attempt['time_mode_attempt_status_id'] = $attemptsInput['status'];
		ClassRegistry::init('TimeModeAttempt')->create($attempt);
		ClassRegistry::init('TimeModeAttempt')->save($attempt);
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void
	{
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				'tsumego_id' => $this->tsumego['id']]];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition)['TsumegoStatus'];
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['user_id'], $this->user['id']);
		$testCase->assertSame($this->resultTsumegoStatus['tsumego_id'], $this->tsumego['id']);
	}

	public function prepareProgressDeletion($progressDeletions)
	{
		foreach ($progressDeletions as $progressDeletionInput)
		{
			ClassRegistry::init('ProgressDeletion')->create();
			$progressDeletion = [];
			$progressDeletion['user_id'] = $progressDeletionInput['user'] ? ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $progressDeletionInput['user']]])['User']['id'] : $this->user['id'];
			;
			$progressDeletion['set_id'] = $this->getOrCreateTsumegoSet($progressDeletionInput['set'])['id'];
			$progressDeletion['created'] = $progressDeletionInput['created'];
			ClassRegistry::init('ProgressDeletion')->save($progressDeletion);
		}
	}

	public function prepareDayRecords(?array $dayRecords): void
	{
		if (!$dayRecords)
			return;

		foreach ($dayRecords as $dayRecordInput)
		{
			$dayRecord = [];
			$dayRecord['user_id'] = Util::extract('user_id', $dayRecordInput) ?: $this->user['id'];
			$dayRecord['date'] = Util::extract('date', $dayRecordInput) ?: date('Y-m-d');
			$dayRecord['solved'] = Util::extract('solved', $dayRecordInput) ?: 0;
			$dayRecord['quote'] = Util::extract('quote', $dayRecordInput) ?: 'q13';
			$dayRecord['tsumego_count'] = Util::extract('tsumego_count', $dayRecordInput) ?: 0;
			$dayRecord['usercount'] = Util::extract('usercount', $dayRecordInput) ?: 1;
			$dayRecord['visitedproblems'] = Util::extract('visitedproblems', $dayRecordInput) ?: 0;
			$dayRecord['gems'] = Util::extract('gems', $dayRecordInput) ?: '0-0-0';
			$dayRecord['gemCounter1'] = Util::extract('gemCounter1', $dayRecordInput) ?: 0;
			$dayRecord['gemCounter2'] = Util::extract('gemCounter2', $dayRecordInput) ?: 0;
			$dayRecord['gemCounter3'] = Util::extract('gemCounter3', $dayRecordInput) ?: 0;

			ClassRegistry::init('DayRecord')->create($dayRecord);
			ClassRegistry::init('DayRecord')->save($dayRecord);
			$this->checkOptionsConsumed($dayRecordInput);
		}
	}

	/**
	 * Ensures admin_activity_type table is populated with correct IDs matching AdminActivityLogger constants.
	 * This is needed because the test database might have stale or incorrect IDs.
	 */
	private function ensureAdminActivityTypes(): void
	{
		App::uses('AdminActivityLogger', 'Utility');
		App::uses('AdminActivityType', 'Model');

		// Define all activity types with their correct IDs from AdminActivityLogger constants
		$types = [
			AdminActivityType::DESCRIPTION_EDIT => 'Description Edit',
			AdminActivityType::HINT_EDIT => 'Hint Edit',
			AdminActivityType::PROBLEM_DELETE => 'Problem Delete',
			AdminActivityType::ALTERNATIVE_RESPONSE => 'Alternative Response',
			AdminActivityType::PASS_MODE => 'Pass Mode',
			AdminActivityType::MULTIPLE_CHOICE => 'Multiple Choice',
			AdminActivityType::SCORE_ESTIMATING => 'Score Estimating',
			AdminActivityType::SOLUTION_REQUEST => 'Solution Request',
			AdminActivityType::SET_TITLE_EDIT => 'Set Title Edit',
			AdminActivityType::SET_DESCRIPTION_EDIT => 'Set Description Edit',
			AdminActivityType::SET_COLOR_EDIT => 'Set Color Edit',
			AdminActivityType::SET_ORDER_EDIT => 'Set Order Edit',
			AdminActivityType::SET_RATING_EDIT => 'Set Rating Edit',
			AdminActivityType::PROBLEM_ADD => 'Problem Add',
			AdminActivityType::SET_ALTERNATIVE_RESPONSE => 'Set Alternative Response',
			AdminActivityType::SET_PASS_MODE => 'Set Pass Mode',
			AdminActivityType::DUPLICATE_REMOVE => 'Duplicate Remove',
			AdminActivityType::DUPLICATE_GROUP_CREATE => 'Duplicate Group Create',
			AdminActivityType::AUTHOR_EDIT => 'Author Edit',
			AdminActivityType::RATING_EDIT => 'Rating Edit',
			AdminActivityType::MINIMUM_RATING_EDIT => 'Minimum Rating Edit',
			AdminActivityType::MAXIMUM_RATING_EDIT => 'Maximum Rating Edit'];

		$adminActivityType = ClassRegistry::init('AdminActivityType');

		// Clear existing entries and repopulate with correct IDs
		$adminActivityType->deleteAll(['1 = 1']);

		foreach ($types as $id => $name)
		{
			$adminActivityType->create();
			$adminActivityType->save([
				'AdminActivityType' => [
					'id' => $id,
					'name' => $name,
				]
			], false);
		}
	}

	public function prepareAdminActivities(?array $adminActivities): void
	{
		if (!$adminActivities)
			return;

		// Ensure admin_activity_type table is populated with correct IDs matching AdminActivityLogger constants
		$this->ensureAdminActivityTypes();

		foreach ($adminActivities as $activityInput)
		{
			$activity = [];

			// Handle user_id - support specifying by name from other-users
			$userId = Util::extract('user_id', $activityInput);
			if (is_string($userId))
			{
				// Find user by name from otherUsers
				$foundUser = null;
				foreach ($this->otherUsers as $otherUser)
					if ($otherUser['name'] === $userId)
					{
						$foundUser = $otherUser;
						break;
					}
				$activity['user_id'] = $foundUser ? $foundUser['id'] : $this->user['id'];
			}
			else
				$activity['user_id'] = $userId ?: $this->user['id'];

			$activity['type'] = Util::extract('type', $activityInput);

			// Support 'tsumego_id' => true to use the main context tsumego
			// Support 'tsumego_id' => 'other:0' to use otherTsumegos[0], etc.
			$tsumegoId = Util::extract('tsumego_id', $activityInput);
			if ($tsumegoId === true && $this->tsumego)
				$activity['tsumego_id'] = $this->tsumego['id'];
			elseif (is_string($tsumegoId) && strpos($tsumegoId, 'other:') === 0)
			{
				$index = (int) substr($tsumegoId, 6);
				$activity['tsumego_id'] = $this->otherTsumegos[$index]['id'] ?? null;
			}
			else
				$activity['tsumego_id'] = $tsumegoId;

			// Support 'set_id' => true to use the first set from main tsumego
			$setId = Util::extract('set_id', $activityInput);
			if ($setId === true && $this->tsumego && isset($this->tsumego['set-connections'][0]))
				$activity['set_id'] = $this->tsumego['set-connections'][0]['set_id'];
			else
				$activity['set_id'] = $setId;

			$activity['old_value'] = Util::extract('old_value', $activityInput);
			$activity['new_value'] = Util::extract('new_value', $activityInput);

			// Convert 'other:X' references in old_value/new_value to actual IDs
			if (is_string($activity['old_value']) && strpos($activity['old_value'], 'other:') === 0)
			{
				$index = (int) substr($activity['old_value'], 6);
				$activity['old_value'] = (string) ($this->otherTsumegos[$index]['id'] ?? null);
			}
			if (is_string($activity['new_value']) && strpos($activity['new_value'], 'other:') === 0)
			{
				$index = (int) substr($activity['new_value'], 6);
				$activity['new_value'] = (string) ($this->otherTsumegos[$index]['id'] ?? null);
			}

			ClassRegistry::init('AdminActivity')->create();
			ClassRegistry::init('AdminActivity')->save(['AdminActivity' => $activity]);
			$this->checkOptionsConsumed($activityInput);
		}
	}

	public function prepareTags($tagsInput): void
	{
		if (!$tagsInput)
			return;
		foreach ($tagsInput as $tagInput)
			$this->getOrCreateTag($tagInput);
	}

	public function addFavorite($tsumego)
	{
		$favorite = [];
		$favorite['tsumego_id'] = $tsumego['id'];
		$favorite['user_id'] = $this->user['id'];
		ClassRegistry::init('Favorite')->create($favorite);
		ClassRegistry::init('Favorite')->save($favorite);
	}

	public function reloadUser(): array
	{
		$this->user = ClassRegistry::init('User')->findById($this->user['id'])['User'];
		return $this->user;
	}

	public function XPGained(): int
	{
		$result = Level::getOverallXPGained($this->reloadUser());
		$toBeLastXP = $result;
		$result -= $this->lastXp;
		$this->lastXp = $toBeLastXP;
		return $result;
	}

	public static function getUserIdFromName($input): ?int
	{
		if (!$input)
			return null;
		return ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $input]])['User']['id'];
	}

	public ?array $user = null;
	public array $otherUsers = [];
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
	private int $lastXp = 0;
}
