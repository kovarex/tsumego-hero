<?php

use App\Model\Entity\TimeModeRank;
use App\Model\Entity\TimeModeSession;
use App\Model\Table\TimeModeRankTable;
use App\Utility\AchievementChecker;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\BoardSelector;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Level;
use App\Utility\SgfParser;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

class ContextPreparator
{
	//Held for the whole process, so no second run can empty the tables in the middle of a run.
	private static $testDatabaseLock = null;

	public function __construct(?array $options = [])
	{
		self::takeTestDatabaseLock();

		// Delete all test data via raw PDO so FK check disable is guaranteed on the same connection
		/** @var DboSource $db */
		$db = ClassRegistry::init('User')->getDataSource();
		$pdo = $db->getConnection();
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach ([
			'classroom_assignment_result', 'classroom_assignment_problem', 'classroom_assignment', 'classroom_member', 'classroom',
			'tag_connection', 'tag', 'schedule', 'progress_deletion',
			'day_record', 'sgf_review', 'sgf', 'time_mode_attempt', 'time_mode_session', 'challenge_participant', 'challenge_problem', 'challenge',
			'tsumego_comment', 'tsumego_issue', 'admin_activity', 'admin_overview_seen', 'message',
			'achievement_condition', 'achievement_status', 'set_connection',
			'tsumego_attempt', 'tsumego_status', 'mistake_training_review', 'mistake_training_day', 'mistake_training_pool',
			'tsumego_variant', 'reject', 'user_avatar', 'user', 'time_mode_rank',
			'tsumego', 'set', 'phpbb_users', 'phpbb_topics',
		] as $table)
			$pdo->exec("DELETE FROM `$table`");
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
		User::clearDeletedCache();

		if (!array_key_exists('user', $options) && !array_key_exists('other-users', $options))
			$this->prepareThisUser(['name' => 'kovarex']);
		else
			$this->prepareThisUser(Util::extract('user', $options));


		$this->prepareOtherUsers(Util::extract('other-users', $options));
		$this->prepareSet(Util::extract('set', $options));
		$this->prepareThisTsumego(Util::extract('tsumego', $options));
		$this->prepareOtherTsumegos(Util::extract('tsumegos', $options));
		$this->prepareTimeModeRanks(Util::extract('time-mode-ranks', $options));
		$this->prepareTimeModeSessions(Util::extract('time-mode-sessions', $options));
		$this->prepareProgressDeletion(Util::extract('progress-deletions', $options));
		$this->prepareSchedule(Util::extract('schedule', $options));
		$this->prepareDayRecords(Util::extract('day-records', $options));
		$this->prepareAchievementConditions(Util::extract('achievement-conditions', $options));
		$this->ensureAdminActivityTypes();
		$this->prepareAdminActivities(Util::extract('admin-activities', $options));
		$this->prepareTags(Util::extract('tags', $options));
		$this->checkOptionsConsumed($options);
		if ($this->user)
			$this->lastXp = Level::getOverallXPGained($this->user);
	}

	//Every run empties every table before each test, so a second process would pull the rows out
	//from under the first one. The lock lives as long as this process does, which is exactly the
	//window in which the shared test database is in use. The kernel drops it however the process
	//ends, so a killed run leaves nothing behind to clean up.
	private static function takeTestDatabaseLock(): void
	{
		if (self::$testDatabaseLock !== null)
			return;

		$path = TMP . 'test-db.lock';
		$handle = @fopen($path, 'c');
		if ($handle === false)
			throw new RuntimeException('Cannot create the test database lock file at ' . $path);

		if (!flock($handle, LOCK_EX | LOCK_NB))
		{
			$holder = trim((string) @file_get_contents($path));
			throw new RuntimeException('Another test run is using the test database'
				. ($holder === '' ? '.' : ' (pid ' . $holder . ').')
				. ' Stop it before starting a new run.');
		}

		ftruncate($handle, 0);
		fwrite($handle, (string) getmypid());
		fflush($handle);
		self::$testDatabaseLock = $handle;
	}

	private function checkOptionsConsumed(?array $options)
	{
		if (!count($options))
			return;
		throw new Exception("Option " . implode(",", array_keys($options)) . " not recognized.");
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
		// Extract external_id first (used for multiple checks)
		$externalId = Util::extract('external_id', $userInput);
		$isGoogleUser = !empty($externalId);
		// Google users have NULL name (they can't use password login)
		// We still extract 'name' to consume it from the input array (prevents "Option not recognized" error)
		$inputName = Util::extract('name', $userInput);
		$user['name'] = $isGoogleUser ? null : ($inputName ?: 'kovarex');
		$user['display_name'] = Util::extract('display_name', $userInput) ?: ($isGoogleUser ? 'DN_G_' . substr($externalId, -6) : 'DN_' . $user['name']);
		$user['email'] = Util::extract('email', $userInput) ?: 'test@example.com';
		$user['password_hash'] = $isGoogleUser ? 'google_oauth' : '$2y$10$5.F2n794IrgFcLRBnE.rju1ZoJheRr1fVc4SYq5ICeaJG0C800TRG'; // hash of test
		$id = Util::extract('id', $userInput);
		if ($id)
			$user['id'] = (int) $id;
		$isAdmin = Util::extract('admin', $userInput) ?? false;
		$isOwner = Util::extract('owner', $userInput) ?? false;
		$user['user_role_id'] = $isOwner ? User::ROLE_OWNER : ($isAdmin ? User::ROLE_ADMIN : User::ROLE_PLAYER);
		$user['rating'] = Util::extract('rating', $userInput) ?: self::$DEFAULT_USER_RATING;
		$user['highest_rating'] = Util::extract('highest_rating', $userInput) ?: $user['rating'];
		$premium = Util::extract('premium', $userInput);
		$user['premium_id'] = $premium ? (int) $premium : null;
		$deleted = Util::extract('deleted', $userInput) ?: false;
		$user['solved'] = Util::extract('solved', $userInput) ?: 0;
		$user['xp'] = Util::extract('xp', $userInput) ?: 0;
		$user['daily_xp'] = Util::extract('daily_xp', $userInput) ?: 0;
		$user['daily_solved'] = Util::extract('daily_solved', $userInput) ?: 0;
		$user['external_id'] = Util::extract('external_id', $userInput) ?: null;
		$user['boards_bitmask'] = Util::extract('boards_bitmask', $userInput) ?: BoardSelector::$DEFAULT_BOARDS_BITMASK;
		// Google Sign-In fields
		$user['external_id'] = $externalId ?: null;
		$user['picture'] = Util::extract('picture', $userInput) ?: null;
		$user['avatar_setting_id'] = Util::extract('avatar_setting_id', $userInput) ?: User::AVATAR_BOARD;
		$user['uploaded_avatar_hash'] = Util::extract('uploaded_avatar_hash', $userInput) ?: null;
		$user['needs_display_name_change'] = Util::extract('needs_display_name_change', $userInput) ?: false;
		foreach ([
			'used_refinement',
			'used_sprint',
			'used_rejuvenation',
			'used_potion',
			'used_intuition',
			'used_revelation'] as $name)
				$user[$name] = Util::extract($name, $userInput) ?: 0;
		$user['level'] = Util::extract('level', $userInput) ?: 1;
		if (array_key_exists('health', $userInput))
		{
			$health = (int) Util::extract('health', $userInput);
			$user['damage'] = Util::getHealthBasedOnLevel($user['level']) - $health;
		}
		else
			$user['damage'] = Util::extract('damage', $userInput) ?? 0;
		$user['sprint_start'] = Util::extract('sprint_start', $userInput) ?: null;
		$user['mode_id'] = Util::extract('mode', $userInput) ?: Constants::$LEVEL_MODE;
		$user['player_color_setting'] = Util::extract('player_color_setting', $userInput) ?? User::PLAYER_COLOR_SETTING_RANDOM;
		$user['board_orientation_setting'] = Util::extract('board_orientation_setting', $userInput) ?? User::BOARD_ORIENTATION_SETTING_RANDOM;
		$user['date_format_id'] = Util::extract('date_format_id', $userInput) ?? User::DATE_FORMAT_ISO;
		$user['sound'] = (bool) (Util::extract('sound', $userInput) ?? false);
		$user['email_on_new_message'] = Util::extract('email_on_new_message', $userInput) ?? 1;
		if ($lastTimeModeCategoryID = Util::extract('last-time-mode-category-id', $userInput))
			$user['last_time_mode_category_id'] = $lastTimeModeCategoryID;
		if ($query = Util::extract('query', $userInput))
			$user['collection_grouping'] = $query;
		if ($collectionSize = Util::extract('collection_size', $userInput))
			$user['collection_size'] = $collectionSize;
		foreach (['filtered_sets', 'filtered_ranks', 'filtered_tags'] as $name)
			if ($filter = Util::extract($name, $userInput))
				$user[$name] = implode('@', $filter);
		$user['contribution_revelation_bonus'] = Util::extract('contribution_revelation_bonus', $userInput) ?: 0;

		// Save user
		$Model = ClassRegistry::init('User');
		$Model->create();
		$saveResult = $Model->save($user);
		if (!$saveResult)
			throw new Exception("Failed to save user: " . print_r($Model->validationErrors, true));
		// Find by display_name (works for both regular and Google users)
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['display_name' => $user['display_name']]])['User'];

		$this->prepareAchievementStatuses($user, Util::extract('achievement-statuses', $userInput));
		if ($deleted)
		{
			User::deleteAccount($user['id']);
			$user = ClassRegistry::init('User')->findById($user['id'])['User'];
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

	private function prepareSet(?array $setInput): void
	{
		if (!$setInput)
			return;

		$set = [];
		$set['title'] = Util::extract('title', $setInput) ?: 'Test Set';
		$set['public'] = Util::extract('public', $setInput) ?? 1;
		$set['order'] = Util::extract('order', $setInput) ?: Constants::$DEFAULT_SET_ORDER;
		ClassRegistry::init('Set')->create();
		ClassRegistry::init('Set')->save($set);
		$this->set = ClassRegistry::init('Set')->find('first', ['order' => 'id DESC'])['Set'];
		$this->checkOptionsConsumed($setInput);
	}

	private function prepareTsumego(array|int|null $tsumegoInput): array
	{
		if (is_int($tsumegoInput))
			return $this->prepareTsumego(['set_order' => $tsumegoInput]);

		$tsumego = [];
		$tsumego['description'] = Util::extract('description', $tsumegoInput) ?: 'test-tsumego';
		$tsumego['hint'] = Util::extract('hint', $tsumegoInput) ?: '';
		$tsumego['rating'] = Util::extract('rating', $tsumegoInput) ?: 1000;
		$tsumego['minimum_rating'] = Util::extract('minimum_rating', $tsumegoInput) ?: null;
		$tsumego['maximum_rating'] = Util::extract('maximum_rating', $tsumegoInput) ?: null;
		$tsumego['alternative_response'] = Util::extract('alternative_response', $tsumegoInput) ?? 1;
		$tsumego['pass'] = Util::extract('pass', $tsumegoInput) ?? 0;
		$tsumego['semeai_type'] = Util::extract('semeai_type', $tsumegoInput);
		$tsumego['minimum_liberties'] = Util::extract('minimum_liberties', $tsumegoInput);
		$tsumego['maximum_liberties'] = Util::extract('maximum_liberties', $tsumegoInput);
		$tsumego['liberty_count'] = Util::extract('liberty_count', $tsumegoInput);
		$tsumego['variance'] = Util::extract('variance', $tsumegoInput);
		$tsumego['deleted'] = Util::extract('deleted', $tsumegoInput);
		$tsumego['author'] = Util::extract('author', $tsumegoInput) ?: '';
		ClassRegistry::init('Tsumego')->create($tsumego);
		ClassRegistry::init('Tsumego')->save($tsumego);
		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => ['id' => 'DESC']])['Tsumego'];
		assert($tsumego['id']);

		$this->prepareTsumegoSetsFromJustNum(Util::extract('set_order', $tsumegoInput), $tsumego);
		$this->prepareTsumegoSets(Util::extract('sets', $tsumegoInput), $tsumego);
		$this->prepareTsumegoTags(Util::extract('tags', $tsumegoInput), $tsumego);
		$this->prepareTsumegoStatus(Util::extract('status', $tsumegoInput), $tsumego);
		$this->prepareTsumegoStatuses(Util::extract('statuses', $tsumegoInput), $tsumego);
		$this->prepareTsumegoAttempt(Util::extract('attempt', $tsumegoInput), $tsumego);
		$this->prepareTsumegoAttempts(Util::extract('attempts', $tsumegoInput), $tsumego);
		$this->prepareTsumegoSgf(Util::extract('sgf', $tsumegoInput), $tsumego);
		$this->prepareTsumegoSgfs(Util::extract('sgfs', $tsumegoInput), $tsumego);
		$this->prepareTsumegoComments(Util::extract('comments', $tsumegoInput), $tsumego);
		$this->prepareTsumegoIssues(Util::extract('issues', $tsumegoInput), $tsumego);
		$this->prepareTsumegoVariants(Util::extract('variants', $tsumegoInput), $tsumego);
		$this->checkOptionsConsumed($tsumegoInput);
		return $tsumego;
	}

	private function prepareThisTsumego(int|array|null $tsumego): void
	{
		if (is_null($tsumego))
			return;
		$this->tsumegos[] = $this->prepareTsumego($tsumego);
	}

	private function prepareOtherTsumegos(?array $tsumegos): void
	{
		foreach ($tsumegos as $tsumego)
		{
			$tsumego = $this->prepareTsumego($tsumego);
			$this->tsumegos [] = $tsumego;
		}
	}

	private function prepareTsumegoAttempt(?array $tsumegoAttemptInput, $tsumego): void
	{
		if (!$tsumegoAttemptInput)
			return;

		$tsumegoAttempt['user_id'] = Util::extract('user_id', $tsumegoAttemptInput) ?: $this->user['id'];
		$tsumegoAttempt['user_rating'] = Util::extract('user_rating', $tsumegoAttemptInput) ?: $this->user['rating'];
		$tsumegoAttempt['tsumego_id'] = $tsumego['id'];
		$tsumegoAttempt['gain'] = Util::extract('gain', $tsumegoAttemptInput) ?: 0;
		$tsumegoAttempt['seconds'] = Util::extract('seconds', $tsumegoAttemptInput) ?: 0;
		$tsumegoAttempt['solved'] = Util::extract('solved', $tsumegoAttemptInput) ?: false;
		$tsumegoAttempt['tsumego_rating'] = Util::extract('tsumego_rating', $tsumegoAttemptInput) ?: $tsumego['rating'];
		$tsumegoAttempt['misplays'] = Util::extract('misplays', $tsumegoAttemptInput) ?: 0;
		$tsumegoAttempt['created'] = Util::extract('created', $tsumegoAttemptInput) ?: date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoAttempt')->create($tsumegoAttempt);
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
		$this->checkOptionsConsumed($tsumegoAttemptInput);
	}

	private function prepareTsumegoAttempts(?array $tsumegoAttempts, $tsumego): void
	{
		if ($tsumegoAttempts)
			foreach ($tsumegoAttempts as $attemptInput)
				$this->prepareTsumegoAttempt($attemptInput, $tsumego);
	}

	private function prepareTsumegoSgf(mixed $tsumegoSgf, &$tsumego): void
	{
		if (!$tsumegoSgf)
			return;
		if (is_string($tsumegoSgf))
			$tsumegoSgf = ['data' => $tsumegoSgf];

		$sgf = [];
		ClassRegistry::init('Sgf')->create($sgf);
		$sgf['tsumego_id'] = $tsumego['id'];
		$sgf['sgf'] = Util::extract('data', $tsumegoSgf);
		$this->validateFixtureSgf($sgf['sgf'], $tsumego['id']);
		$sgf['sgf_status_id'] = Util::extractWithDefault('sgf_status_id', $tsumegoSgf, Sgf::STATUS_ACCEPTED);
		// an accepted version is the change of the user, unless it says who accepted it
		if ($sgf['sgf_status_id'] === Sgf::STATUS_ACCEPTED)
		{
			$sgf['accepted_by_user_id'] = Util::extractWithDefault('accepted_by_user_id', $tsumegoSgf, Auth::getUserID());
			$sgf['accepted'] = date('Y-m-d H:i:s');
		}
		$sgf['correct_moves'] = Util::extract('correct_moves', $tsumegoSgf);
		$sgf['first_move_color'] = Util::extract('first_move_color', $tsumegoSgf);
		$sgf['user_id'] = Auth::getUserID();
		$sgfModel = ClassRegistry::init('Sgf');
		$sgfModel->save($sgf);
		$savedSgf = $sgfModel->data['Sgf'];
		$savedSgf['id'] = $sgfModel->id;
		$tsumego['sgfs'][] = $savedSgf;
		$this->checkOptionsConsumed($tsumegoSgf);
	}

	private function validateFixtureSgf(string $sgf, int $tsumegoId): void
	{
		$error = SgfParser::validate($sgf);
		if ($error !== null)
			throw new Exception("Invalid fixture SGF for tsumego {$tsumegoId}: {$error}");

		$gameError = SgfParser::validateGame($sgf);
		if ($gameError !== null)
			throw new Exception("Invalid fixture SGF for tsumego {$tsumegoId}: {$gameError}");
	}

	private function prepareTsumegoSgfs(?array $tsumegoSgfs, &$tsumego): void
	{
		if (!$tsumegoSgfs)
			return;
		foreach ($tsumegoSgfs as $tsumegoSgf)
			$this->prepareTsumegoSgf($tsumegoSgf, $tsumego);
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
		$comment['position'] = Util::extract('position', $commentInput);
		if ($issueId !== null)
			$comment['tsumego_issue_id'] = $issueId;
		$created = Util::extract('created', $commentInput);
		if ($created)
			$comment['created'] = $created;
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

		// Create the issue
		ClassRegistry::init('TsumegoIssue')->create();
		$issue = [];
		$issue['tsumego_id'] = $tsumego['id'];
		$issue['user_id'] = $this->user['id'];
		$issue['tsumego_issue_status_id'] = Util::extract('status', $issueInput) ?: TsumegoIssue::$OPENED_STATUS;
		$created = Util::extract('created', $issueInput);
		if ($created)
			$issue['created'] = $created;
		$issueModel = ClassRegistry::init('TsumegoIssue');
		$issueModel->save($issue);

		// Get the created issue ID
		$issueId = $issueModel->id;

		// Store issue in array for test access
		$this->issues[] = ['id' => $issueId, 'tsumego_id' => $issue['tsumego_id'], 'user_id' => $issue['user_id']];

		// Create initial comment for the issue
		$message = Util::extract('message', $issueInput);
		if ($message)
			$this->prepareTsumegoComment(['message' => $message, 'created' => $created], $tsumego, $issueId);

		$this->checkOptionsConsumed($issueInput);
	}

	private function prepareTsumegoVariants(?array $tsumegoVariants, &$tsumego): void
	{
		if (!$tsumegoVariants)
			return;
		foreach ($tsumegoVariants as $variantInput)
			$this->prepareTsumegoVariant($variantInput, $tsumego);
	}

	private function prepareTsumegoVariant(array $variantInput, &$tsumego): void
	{
		$variant = array_merge(['tsumego_id' => $tsumego['id']], $variantInput);
		$variantModel = ClassRegistry::init('TsumegoVariant');
		$variantModel->create($variant);
		$variantModel->save($variant);
		$savedVariant = $variantModel->data['TsumegoVariant'];
		$savedVariant['id'] = $variantModel->id;
		$tsumego['variants'][] = $savedVariant;
	}

	private function prepareTsumegoStatuses($tsumegoStatuses, $tsumego): void
	{
		if (!$tsumegoStatuses)
			return;
		foreach ($tsumegoStatuses as $tsumegoStatus)
			$this->prepareTsumegoStatus($tsumegoStatus, $tsumego);
	}

	private function prepareTsumegoStatus($tsumegoStatus, $tsumego): void
	{
		$statusValue = $tsumegoStatus ? (is_string($tsumegoStatus) ? $tsumegoStatus : $tsumegoStatus['name']) : null;
		$updated = $tsumegoStatus ? (is_string($tsumegoStatus) ? null : ($tsumegoStatus['updated'] ?? null)) : null;
		$mistakeTrainingDue = $tsumegoStatus ? (is_string($tsumegoStatus) ? null : ($tsumegoStatus['mistake_training_due'] ?? null)) : null;
		$userID = $tsumegoStatus
			? (is_string($tsumegoStatus)
				? $this->user['id']
				: (self::getUserIdFromName(Util::extract('user', $tsumegoStatus)) ?: $this->user['id']))
			: null;

		$statusCondition = [
			'conditions' => [
				'user_id' => $userID,
				['tsumego_id' => $tsumego['id']]]];

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
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $userID;
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $tsumego['id'];
			ClassRegistry::init('TsumegoStatus')->create($originalTsumegoStatus);
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}

		// A status fixture with mistake_training_due now marks the problem as in
		// the training pool (the due column moved to mistake_training_pool), on the
		// rung (mistake_training_rung), with the lapses (mistake_training_lapses) and
		// with the last review (mistake_training_review_day,
		// mistake_training_review_result) it names.
		if ($mistakeTrainingDue !== null)
			$this->prepareMistakeTrainingPoolEntry($userID, (int) $tsumego['id'], $mistakeTrainingDue, $tsumegoStatus);
	}

	private function prepareMistakeTrainingPoolEntry(int $userId, int $tsumegoId, $mistakeTrainingDue, array $tsumegoStatus): void
	{
		$pool = TableRegistry::getTableLocator()->get('MistakeTrainingPool');
		$existing = $pool->find()->where(['user_id' => $userId, 'tsumego_id' => $tsumegoId])->first();
		if ($existing)
		{
			$existing->set('next_due', $mistakeTrainingDue);
			$pool->saveOrFail($existing);
			return;
		}
		$pool->saveOrFail($pool->newEntity([
			'user_id' => $userId,
			'tsumego_id' => $tsumegoId,
			'rung' => $tsumegoStatus['mistake_training_rung'] ?? 0,
			'lapses' => $tsumegoStatus['mistake_training_lapses'] ?? 0,
			'next_due' => $mistakeTrainingDue,
			'last_review_day' => $tsumegoStatus['mistake_training_review_day'] ?? null,
			'last_mistake_training_result_id' => $tsumegoStatus['mistake_training_review_result'] ?? null,
		]));
	}

	private function prepareTsumegoSetsFromJustNum(?int $num, &$tsumego): void
	{
		if (is_null($num))
			return;
		$this->prepareTsumegoSet(['name' => 'test set', 'num' => $num], $tsumego);
	}

	private function prepareTsumegoSets($setsInput, &$tsumego): void
	{
		if (!$setsInput)
			return;
		$this->tsumegoSets = [];
		foreach ($setsInput as $tsumegoSet)
			$this->prepareTsumegoSet($tsumegoSet, $tsumego);
	}

	private function prepareTsumegoSet($setInput, &$tsumego): void
	{
		if (!$setInput)
			return;

		$set = $this->getOrCreateTsumegoSet([
			'name' => Util::extractWithDefault('name', $setInput, 'test set'),
			'included_in_time_mode' => Util::extract('included_in_time_mode', $setInput),
			'public' => Util::extract('public', $setInput),
			'board_theme_index' => Util::extract('board_theme_index', $setInput),
			'user_id' => Util::extract('user_id', $setInput),
			'order' => Util::extractWithDefault('order', $setInput, null),
			'default' => Util::extract('default', $setInput),
			'description' => Util::extractWithDefault('description', $setInput, null),
			'deleted' => Util::extractWithDefault('deleted', $setInput, null)]);
		$setConnection = [];
		$setConnection['tsumego_id'] = $tsumego['id'];
		$setConnection['set_id'] = $set['id'];
		$setConnection['num'] = Util::extract('num', $setInput);
		ClassRegistry::init('SetConnection')->create();
		ClassRegistry::init('SetConnection')->save($setConnection);
		$setConnection = ClassRegistry::init('SetConnection')->find('first', ['order' => ['id' => 'DESC']])['SetConnection'];
		$tsumego['sets'] [] = $set;
		$tsumego['set-connections'] [] = $setConnection;
		$this->setConnections [] = $setConnection;
		$this->checkOptionsConsumed($setInput);
	}

	private function prepareTsumegoTags($tagsInput, &$tsumego): void
	{
		if (!$tagsInput)
			return;

		foreach ($tagsInput as $tagInput)
		{
			$tag = $this->getOrCreateTag([
				'name' => Util::extract('name', $tagInput),
				'approved' => Util::extract('tag_approved', $tagInput) ?: true,
				'is_hint' => Util::extract('is_hint', $tagInput) ?: 0]);
			$approved = Util::extract('approved', $tagInput);
			$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
			$tagConnection = $tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
				'tsumego_id' => $tsumego['id'],
				'user_id' => self::getUserIdFromName(Util::extract('user', $tagInput)) ?: $this->user['id'],
				'tag_id' => $tag['id'],
				'approved' => !is_null($approved) ? $approved : 1]));
			$tsumego['tags'] [] = $tag;
			$tsumego['tag-connections'] [] = $tagConnection;
			$this->checkOptionsConsumed($tagInput);
		}
	}

	private function getOrCreateTsumegoSet($input): array
	{
		$isDefault = false;
		$order = null;
		if (is_string($input))
		{
			$name = $input;
			$includedInTimeMode = false;
			$public = 1;
			$boardThemeIndex = 1;
			$userId = null;
			$description = null;
			$deleted = null;
		}
		else
		{
			$name = Util::extract('name', $input);
			$includedInTimeMode = Util::extract('included_in_time_mode', $input);
			$public = Util::extractWithDefault('public', $input, true);
			$boardThemeIndex = Util::extractWithDefault('board_theme_index', $input, null);
			$userId = Util::extract('user_id', $input);
			$order = Util::extractWithDefault('order', $input, null);
			$isDefault = Util::extract('default', $input) ?: false;
			$description = Util::extractWithDefault('description', $input, null);
			$deleted = Util::extractWithDefault('deleted', $input, null);
			$this->checkOptionsConsumed($input);
		}
		$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
		if (!$set)
		{
			$set = [];
			$set['title'] = $name;
			$set['included_in_time_mode'] = is_null($includedInTimeMode) ? true : $includedInTimeMode;
			$set['public'] = is_null($public) ? true : $public;
			$set['board_theme_index'] = $boardThemeIndex;
			$set['order'] = $order ?: Constants::$DEFAULT_SET_ORDER;
			if ($description !== null)
				$set['description'] = $description;
			if ($deleted !== null)
				$set['deleted'] = $deleted;
			if ($userId === 'self')
				$set['user_id'] = $this->user['id'];
			elseif ($userId !== null)
				$set['user_id'] = (int) $userId;
			ClassRegistry::init('Set')->create($set);
			ClassRegistry::init('Set')->save($set);
			// reloading so the generated id is retrieved
			$set  = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $name]]);
			$this->sets [] = $set['Set'];
		}
		if ($isDefault)
			$this->setDefaultSetId($set['Set']['id']);
		$this->checkSetClear($set['Set']['id']);
		return $set['Set'];
	}

	private function setDefaultSetId(int $setId): void
	{
		$this->user['default_set_id'] = $setId;
		ClassRegistry::init('User')->id = $this->user['id'];
		ClassRegistry::init('User')->saveField('default_set_id', $setId);
	}

	private function getOrCreateTag($tagInput): array
	{
		$name = Util::extract('name', $tagInput);
		$tag  = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $name]]);
		if (!$tag)
		{
			$tag = [];
			$tag['hint'] = Util::extractWithDefault('is_hint', $tagInput, 0);
			$tag['approved'] = Util::extractWithDefault('approved', $tagInput, true);
			$tag['user_id'] = $this->user['id'];
			$tag['description'] = Util::extractWithDefault('description', $tagInput, '');
			$tag['link'] = Util::extractWithDefault('link', $tagInput, '');
			$tag['name'] = $name;
			ClassRegistry::init('Tag')->create($tag);
			ClassRegistry::init('Tag')->save($tag);
			// reloading so the generated id is retrieved
			$tag  = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $name]]);
		}
		else
		{
			Util::extractWithDefault('description', $tagInput, '');
			Util::extractWithDefault('approved', $tagInput, true);
			Util::extractWithDefault('is_hint', $tagInput, 0);
			Util::extractWithDefault('link', $tagInput, '');
		}

		$this->checkOptionsConsumed($tagInput);
		$this->tags[] = $tag['Tag'];
		return $tag['Tag'];
	}

	private function checkSetClear(int $setID): void
	{
		if (isset($this->setsCleared[$setID]))
			return;
		$pdo = ClassRegistry::init('User')->getDataSource()->getConnection();
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		$pdo->exec("DELETE FROM `set_connection` WHERE `set_id` = " . intval($setID));
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
		$this->setsCleared[$setID] = true;
	}

	private function prepareTimeModeRanks($timeModeRanks): void
	{
		$allRanks = [
			'15k' => TimeModeRankTable::RANK_15K, '14k' => TimeModeRankTable::RANK_14K,
			'13k' => TimeModeRankTable::RANK_13K, '12k' => TimeModeRankTable::RANK_12K,
			'11k' => TimeModeRankTable::RANK_11K, '10k' => TimeModeRankTable::RANK_10K,
			'9k' => TimeModeRankTable::RANK_9K, '8k' => TimeModeRankTable::RANK_8K,
			'7k' => TimeModeRankTable::RANK_7K, '6k' => TimeModeRankTable::RANK_6K,
			'5k' => TimeModeRankTable::RANK_5K, '4k' => TimeModeRankTable::RANK_4K,
			'3k' => TimeModeRankTable::RANK_3K, '2k' => TimeModeRankTable::RANK_2K,
			'1k' => TimeModeRankTable::RANK_1K, '1d' => TimeModeRankTable::RANK_1D,
			'2d' => TimeModeRankTable::RANK_2D, '3d' => TimeModeRankTable::RANK_3D,
			'4d' => TimeModeRankTable::RANK_4D, '5d' => TimeModeRankTable::RANK_5D,
		];

		// If not explicitly specified, seed all ranks
		$ranksToSeed = $timeModeRanks ?? array_keys($allRanks);

		$table = TableRegistry::getTableLocator()->get('TimeModeRank');
		$this->timeModeRanks = [];
		foreach ($ranksToSeed as $rankInput)
		{
			$rankName = is_array($rankInput) ? $rankInput['name'] : $rankInput;
			if (!isset($allRanks[$rankName]))
				throw new Exception("Unknown rank name '$rankName'");
			$table->saveOrFail($table->newEntity(['id' => $allRanks[$rankName], 'name' => $rankName], ['accessibleFields' => ['id' => true]]));
			$this->timeModeRanks[] = ['id' => $allRanks[$rankName], 'name' => $rankName];
		}
	}

	private function prepareTimeModeSessions($timeModeSessions): void
	{
		if (!$timeModeSessions)
			return;

		$sessionTable = TableRegistry::getTableLocator()->get('TimeModeSession');
		foreach ($timeModeSessions as $timeModeSessionInput)
		{
			/** @var TimeModeRank|null $rank */
			$rank = TableRegistry::getTableLocator()->get('TimeModeRank')->find()->where(['name' => $timeModeSessionInput['rank']])->first();
			if (!$rank)
				throw new Exception('Rank ' . $timeModeSessionInput['rank'] . ' not found');
			/** @var TimeModeSession $session */
			$session = $sessionTable->saveOrFail($sessionTable->newEntity([
				'user_id' => Auth::getUserID(),
				'time_mode_category_id' => $timeModeSessionInput['category'],
				'time_mode_session_status_id' => $timeModeSessionInput['status'],
				'time_mode_rank_id' => $rank->id]));
			$this->timeModeSessions [] = $session;
			foreach (($timeModeSessionInput['attempts'] ?? []) as $attemptInput)
				$this->prepareTimeModeAttempts($attemptInput, $session->id);
		}
	}

	private function prepareTimeModeAttempts(array $attemptsInput, int $timeModeSessionID): void
	{
		$attempt = [];
		$attempt['time_mode_session_id'] = $timeModeSessionID;
		if (empty($this->tsumegos))
			throw new Exception("No tsumego assign to the time mode attempt");
		$attempt['tsumego_id'] = ContextPreparator::loadTsumegoID(Util::extractWithDefault('tsumego_id', $attemptsInput, $this->tsumegos[0]['id']));
		$attempt['order'] = Util::extract('order', $attemptsInput);
		$attempt['time_mode_attempt_status_id'] = Util::extract('status', $attemptsInput);
		$startedSecondsAgo = Util::extractWithDefault('started-seconds-ago', $attemptsInput, null);
		if ($startedSecondsAgo !== null)
			$attempt['started'] = date('Y-m-d H:i:s', time() - $startedSecondsAgo);
		$this->checkOptionsConsumed($attemptsInput);
		ClassRegistry::init('TimeModeAttempt')->create($attempt);
		ClassRegistry::init('TimeModeAttempt')->save($attempt);
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void
	{
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['id'],
				'tsumego_id' => $this->tsumegos[0]['id']]];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition)['TsumegoStatus'];
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['user_id'], $this->user['id']);
		$testCase->assertSame($this->resultTsumegoStatus['tsumego_id'], $this->tsumegos[0]['id']);
	}

	public function prepareProgressDeletion($progressDeletions)
	{
		foreach ($progressDeletions as $progressDeletionInput)
		{
			ClassRegistry::init('ProgressDeletion')->create();
			$progressDeletion = [];
			$progressDeletion['user_id'] = !empty($progressDeletionInput['user']) ? ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $progressDeletionInput['user']]])['User']['id'] : $this->user['id'];
			$progressDeletion['set_id'] = $this->getOrCreateTsumegoSet($progressDeletionInput['set'])['id'];
			$progressDeletion['set_partition'] = $progressDeletionInput['partition'] ?? null; // 0 based, null for the old records without it
			$progressDeletion['created'] = $progressDeletionInput['created'];
			ClassRegistry::init('ProgressDeletion')->save($progressDeletion);
		}
	}

	//Create schedule entries.
	//Each entry: ['tsumego' => 0, 'set' => 'target set', 'date' => '2026-09-01', 'published' => 0]
	//- tsumego: index into $this->tsumegos (default 0)
	//- set: target set name (resolved via getOrCreateTsumegoSet)
	//- date: publish date
	//- published: 0 or 1 (default 0)
	public function prepareSchedule(?array $scheduleEntries): void
	{
		if (!$scheduleEntries)
			return;

		$scheduleTable = TableRegistry::getTableLocator()->get('Schedule');
		foreach ($scheduleEntries as $entry)
		{
			$tsumegoIndex = $entry['tsumego'] ?? 0;
			$tsumegoId = $this->tsumegos[$tsumegoIndex]['id'];
			$targetSetId = $this->getOrCreateTsumegoSet($entry['set'])['id'];

			$scheduleTable->saveOrFail($scheduleTable->newEntity([
				'tsumego_id' => $tsumegoId,
				'set_id' => $targetSetId,
				'date' => $entry['date'],
				'published' => $entry['published'] ?? 0,
				'created_by_user_id' => $this->user['id'] ?? null,
			]));
		}
	}

	public function prepareDayRecords(?array $dayRecords): void
	{
		if (!$dayRecords)
			return;

		$dayRecordTable = TableRegistry::getTableLocator()->get('DayRecord');
		foreach ($dayRecords as $dayRecordInput)
		{
			$dayRecord = [];
			$dayRecord['user_id'] = Util::extract('user_id', $dayRecordInput) ?: $this->user['id'];
			$dayRecord['date'] = Util::extract('date', $dayRecordInput) ?: date('Y-m-d');
			$dayRecord['quote'] = Util::extract('quote', $dayRecordInput) ?: 'q01';
			$dayRecord['gems'] = Util::extract('gems', $dayRecordInput) ?: '0-0-0';
			$dayRecord['emerald_solves'] = Util::extract('emerald_solves', $dayRecordInput) ?: 0;
			$dayRecord['sapphire_solves'] = Util::extract('sapphire_solves', $dayRecordInput) ?: 0;
			$dayRecord['ruby_solves'] = Util::extract('ruby_solves', $dayRecordInput) ?: 0;

			$dayRecordTable->saveOrFail($dayRecordTable->newEntity($dayRecord));
			$this->checkOptionsConsumed($dayRecordInput);
		}
	}

	//Prepares achievement conditions for the test context.
	//Each condition is associated with the user created in this context.
	//$conditions: Array of achievement condition definitions
	//Each condition should have:
	//  - 'category' (required): The achievement category, an AchievementCondition::CATEGORY_ constant
	//  - 'value' (required): The condition value (e.g., 100 for percentage, 1 for boolean)
	//  - 'set_id' (optional): The set_id for set-specific conditions (defaults to null)
	//  - 'user_id' (optional): Override the user_id (defaults to $this->user['id'])
	public function prepareAchievementConditions(?array $conditions): void
	{
		if (!$conditions)
			return;

		foreach ($conditions as $conditionInput)
		{
			AchievementCondition::put(
				(int) (Util::extract('user_id', $conditionInput) ?: $this->user['id']),
				Util::extract('category', $conditionInput),
				Util::extract('value', $conditionInput),
				Util::extract('set_id', $conditionInput));
			$this->checkOptionsConsumed($conditionInput);
		}
	}

	public function prepareAchievementStatuses($user, ?array $statuses): void
	{
		if (!$statuses)
			return;

		$AchievementStatus = ClassRegistry::init('AchievementStatus');
		foreach ($statuses as $statusInput)
		{
			$status = [];
			$status['user_id'] = $user['id'];
			$status['achievement_id'] = Util::extract('id', $statusInput);
			$status['value'] = Util::extract('value', $statusInput) ?? 1;
			$status['created'] = Util::extract('created', $statusInput) ?: date('Y-m-d H:i:s');

			$AchievementStatus->create();
			$AchievementStatus->save($status);
			$this->checkOptionsConsumed($statusInput);
		}
	}

	//Ensures admin_activity_type table is populated with correct IDs matching AdminActivityLogger constants.
	//This is needed because the test database might have stale or incorrect IDs.
	private function ensureAdminActivityTypes(): void
	{

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
			AdminActivityType::AUTHOR_EDIT => 'Author Edit',
			AdminActivityType::RATING_EDIT => 'Rating Edit',
			AdminActivityType::MINIMUM_RATING_EDIT => 'Minimum Rating Edit',
			AdminActivityType::MAXIMUM_RATING_EDIT => 'Maximum Rating Edit',
			AdminActivityType::ACCEPT_TAG => 'Accept Tag',
			AdminActivityType::REJECT_TAG => 'Reject Tag',
			AdminActivityType::SGF_EDIT => 'SGF Edit',
			AdminActivityType::ADD_TAG => 'Add Tag',
			AdminActivityType::DELETE_TAG => 'Tag Delete',
			AdminActivityType::ACCEPT_PROPOSAL => 'Accept Proposal',
			AdminActivityType::REJECT_PROPOSAL => 'Reject Proposal',
			AdminActivityType::REOPEN_PROPOSAL => 'Reopen Proposal',
			AdminActivityType::TSUMEGO_MERGE => 'Tsumego Merge',
			AdminActivityType::DELETE_USER => 'Delete User',
			AdminActivityType::RESTORE_USER => 'Restore User',
			AdminActivityType::DISPLAY_NAME_CHANGE => 'Display Name Change',
			AdminActivityType::ROLE_CHANGE => 'Role Change',
			AdminActivityType::PROBLEM_RESTORE => 'Problem Restore',
			AdminActivityType::SET_DELETE => 'Set Delete',
			AdminActivityType::SET_RESTORE => 'Set Restore',
			AdminActivityType::ADD_TO_SCHEDULE => 'Add to Schedule',
			AdminActivityType::CANCEL_SCHEDULE => 'Cancel Schedule'];

		$adminActivityType = ClassRegistry::init('AdminActivityType');

		// Clear and repopulate with correct IDs (this table is not in the constructor wipe)
		$pdo = ClassRegistry::init('User')->getDataSource()->getConnection();
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		$pdo->exec('DELETE FROM `admin_activity_type`');
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

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
			$activity['tsumego_id'] = $this->loadTsumegoID(Util::extract('tsumego_id', $activityInput));

			// Support 'set_id' => true to use the first set from main tsumego
			$setId = Util::extract('set_id', $activityInput);
			if ($setId === true && !empty($this->tsumegos) && isset($this->tsumegos[0]['set-connections'][0]))
				$activity['set_id'] = $this->tsumegos[0]['set-connections'][0]['set_id'];
			else
				$activity['set_id'] = $setId;

			$activity['old_value'] = Util::extract('old_value', $activityInput);
			$activity['new_value'] = Util::extract('new_value', $activityInput);

			// Convert 'other:X' references in old_value/new_value to actual IDs
			if (is_string($activity['old_value']) && strpos($activity['old_value'], 'other:') === 0)
			{
				$index = (int) substr($activity['old_value'], 6);
				$activity['old_value'] = (string) ($this->tsumegos[$index]['id'] ?? null);
			}
			if (is_string($activity['new_value']) && strpos($activity['new_value'], 'other:') === 0)
			{
				$index = (int) substr($activity['new_value'], 6);
				$activity['new_value'] = (string) ($this->tsumegos[$index]['id'] ?? null);
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

	public function loadTsumegoID($input): ?int
	{
		if ($input === true)
			return $this->tsumegos[0]['id'];
		if (is_string($input) && strpos($input, 'other:') === 0)
			return $this->tsumegos[intval(substr($input, 6))]['id'];
		return $input;
	}

	public function unlockAchievementsWithoutEffect()
	{
		new AchievementChecker()->checkAll();
	}

	public function changeUserSoRejuvenationCanBeUsed()
	{
		Auth::saveUserField('level', HeroPowers::$REJUVENATION_MINIMUM_LEVEL);
	}

	public function changeUserSoIntuitionCanBeUsed()
	{
		Auth::saveUserField('level', HeroPowers::$INTUITION_MINIMUM_LEVEL);
	}

	public function changeUserSoSprintCanBeUsed()
	{
		Auth::saveUserFields([
			'level' => HeroPowers::$SPRINT_MINIMUM_LEVEL,
			'mode_id' => Constants::$LEVEL_MODE,
		]);
	}

	public ?array $user = null;
	public array $otherUsers = [];
	public ?array $set = null;
	public array $sets = [];
	public array $tsumegos = [];
	public array $issues = [];
	public ?int $mode = null;
	public ?array $resultTsumegoStatus = null;
	public ?array $tsumegoSets = null;
	public array $timeModeRanks = [];
	/** @var array<TimeModeSession> */
	public array $timeModeSessions = [];
	public array $tags = [];
	public array $setConnections = [];

	private array $setsCleared = []; // map of IDs of sets already cleared this run. Exists to avoid sets having leftovers from previous runs
	private int $lastXp = 0;

	public static float $DEFAULT_USER_RATING = 100;
}
