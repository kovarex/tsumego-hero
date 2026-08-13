<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoUtil', 'Utility');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('TsumegoButton', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('SetsSelector', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('Progress', 'Utility');
App::uses('SetEditRenderer', 'Utility');
App::uses('SetImage', 'Utility');

class SetsController extends AppController
{
	public $helpers = ['Html', 'Form'];

	public $title = 'tsumego-hero.com';

	/**
	 * @return void
	 */
	public function sandbox()
	{
		if (!Auth::isAdmin() && !Auth::hasPremium())
		{
			$this->redirect('/');
			return;
		}

		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('SetConnection');

		$this->set('_page', 'sandbox');
		$this->set('_title', 'Tsumego Hero - Collections');
		$setsNew = [];

		if (isset($this->params['url']['restore']))
		{
			$restore = $this->Set->findById($this->params['url']['restore']);
			if ($restore && $restore['Set']['public'] == -1)
			{
				$restore['Set']['public'] = 0;
				$this->Set->save($restore);
			}
		}

		$sets = $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 0, 'user_id IS NULL'],
		]) ?: [];

		if (Auth::isLoggedIn())
		{
			$uts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$uts)
				$uts = [];
			$tsumegoStatusMap = [];
			$utsCount4 = count($uts);
			for ($l = 0; $l < $utsCount4; $l++)
				$tsumegoStatusMap[$uts[$l]['TsumegoStatus']['tsumego_id']] = $uts[$l]['TsumegoStatus']['status'];
		}
		$overallCounter = 0;

		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++)
		{
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$i]['Set']['id']);
			$sets[$i]['Set']['anz'] = count($ts);
			$counter = 0;
			$elo = 0;
			$tsCount3 = count($ts);
			for ($k = 0; $k < $tsCount3; $k++)
			{

				$elo += $ts[$k]['Tsumego']['rating'];
				if (Auth::isLoggedIn())
					if (isset($tsumegoStatusMap[$ts[$k]['Tsumego']['id']]))
						if ($tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'C')
							$counter++;
			}
			if (count($ts) > 0)
				$elo = $elo / count($ts);
			else
				$elo = 0;
			$date = new DateTime($sets[$i]['Set']['created']);
			$month = date('F', strtotime($sets[$i]['Set']['created']));
			$setday = $date->format('d. ');
			$setyear = $date->format('Y');
			if ($setday[0] == 0)
				$setday = substr($setday, -3);
			$sets[$i]['Set']['created'] = $date->format('Ymd');
			$sets[$i]['Set']['createdDisplay'] = $setday . $month . ' ' . $setyear;
			$percent = 0;
			if (count($ts) > 0)
				$percent = Util::getPercentButAvoid100UntilComplete($counter, count($ts));
			$overallCounter += count($ts);
			$sets[$i]['Set']['solvedNum'] = $counter;
			$sets[$i]['Set']['solved'] = $percent;
			$sets[$i]['Set']['solvedColor'] = $this->getSolvedColor($sets[$i]['Set']['solved']);
			$sets[$i]['Set']['topicColor'] = $sets[$i]['Set']['color'];
			$sets[$i]['Set']['difficultyColor'] = $this->getDifficultyColor($sets[$i]['Set']['difficulty']);
			$sets[$i]['Set']['sizeColor'] = $this->getSizeColor($sets[$i]['Set']['anz']);
			$sets[$i]['Set']['dateColor'] = $this->getDateColor($sets[$i]['Set']['created']);

			$sn = [];
			$sn['id'] = $sets[$i]['Set']['id'];
			$sn['name'] = $sets[$i]['Set']['title'];
			$sn['amount'] = count($ts);
			$sn['color'] = $sets[$i]['Set']['color'];
			$sn['difficulty'] = Rating::getReadableRankFromRating($elo);
			$sn['solved'] = $percent;
			array_push($setsNew, $sn);
		}

		$adminsList = $this->User->find('all', ['order' => 'id ASC', 'conditions' => ['isAdmin >' => 0]]) ?: [];
		$admins = [];
		foreach ($adminsList as $item)
			$admins[] = $item['User']['name'];

		$this->set('admins', $admins);
		$this->set('sets', $sets);
		$this->set('setsNew', $setsNew);
		$this->set('overallCounter', $overallCounter);
	}

	public function mine()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');

		$this->_showUserSets(Auth::getUserID());
	}

	public function userSets($userId)
	{
		$this->_showUserSets((int) $userId);
	}

	private function _showUserSets(int $userId): void
	{
		$this->loadModel('User');

		$isOwn = ($userId === Auth::getUserID());
		$profileUser = $this->User->findById($userId);
		$pageTitle = $isOwn ? 'My Sets' : h($profileUser['User']['name']) . "'s Sets";
		$this->set('_title', 'Tsumego Hero - ' . $pageTitle);
		$this->set('profileUser', $profileUser ? $profileUser['User'] : null);
		$this->set('isOwn', $isOwn);

		// Own sets (or an admin browsing) show all sets; others only see public ones
		$publicFilter = ($isOwn || Auth::isAdmin()) ? '' : 'AND s.public = 1';

		$rows = Util::query("
SELECT
	s.id,
	s.title,
	s.color,
	COUNT(sc.tsumego_id) AS amount,
	COALESCE(SUM(t.rating), 0) AS elo_sum,
	COALESCE(SUM(CASE WHEN ts.status IN ('S','W','C') THEN 1 ELSE 0 END), 0) AS solved
FROM `set` s
LEFT JOIN (
	SELECT set_id, tsumego_id, MIN(num) AS num
	FROM set_connection
	GROUP BY set_id, tsumego_id
) sc ON sc.set_id = s.id
LEFT JOIN tsumego t ON t.id = sc.tsumego_id
LEFT JOIN tsumego_status ts ON ts.tsumego_id = sc.tsumego_id AND ts.user_id = ?
WHERE s.user_id = ? $publicFilter
GROUP BY s.id, s.title, s.color
ORDER BY s.order", [Auth::getUserID(), $userId]);

		$setsNew = [];
		foreach ($rows as $row)
		{
			$amount = (int) $row['amount'];
			$elo = $amount > 0 ? (float) $row['elo_sum'] / $amount : 0.0;
			$percent = $amount > 0 ? Util::getPercentButAvoid100UntilComplete((int) $row['solved'], $amount) : 0;

			$setsNew[] = [
				'id' => $row['id'],
				'name' => $row['title'],
				'amount' => $amount,
				'color' => $row['color'],
				'difficulty' => Rating::getReadableRankFromRating($elo),
				'solved' => $percent,
			];
		}

		$this->set('setsNew', $setsNew);
		$this->render('user_sets');
	}

	public function create()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');

		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$redirect = false;
		$t = [];

		if (isset($this->data['Set']))
		{
			$isSandbox = isset($this->params['url']['sandbox']) && Auth::isAdmin();

			$set = [];
			$set['Set']['title'] = $this->data['Set']['title'];
			$set['Set']['public'] = 0;
			$set['Set']['order'] = Constants::$DEFAULT_SET_ORDER;

			if ($isSandbox)
			{
				$set['Set']['image'] = 'b1.png';
				$set['Set']['author'] = 'various creators';
			}
			else
				$set['Set']['user_id'] = Auth::getUserID();

			$this->Set->create();
			$this->Set->save($set);

			if ($isSandbox)
			{
				$t = [];
				$t['Tsumego']['difficulty'] = 4;
				$t['Tsumego']['variance'] = 100;
				$t['Tsumego']['description'] = 'b to kill';
				$t['Tsumego']['author'] = Auth::getUser()['name'];
				$this->Tsumego->create();
				$this->Tsumego->save($t);

				$sc = [];
				$sc['SetConnection']['set_id'] = $this->Set->id;
				$sc['SetConnection']['tsumego_id'] = $this->Tsumego->id;
				$sc['SetConnection']['num'] = 1;
				$this->SetConnection->create();
				$this->SetConnection->save($sc);
			}

			$this->redirect('/sets/view/' . $this->Set->id);
			return;
		}
		$this->set('t', $t);
	}

	public function delete($id = null)
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');

		$setID = $id ?? ($this->data['Set']['id'] ?? null);
		if (!$setID)
			return $this->redirect('/');

		$s = $this->Set->findById((int) $setID);
		if (!$s)
		{
			CookieFlash::set('Set not found', 'error');
			return $this->redirect('/sets');
		}

		// Auth: set owner can delete their own sets; admin can delete sandbox sets
		$isOwner = ($s['Set']['user_id'] == Auth::getUserID());
		$isSandbox = ($s['Set']['user_id'] === null && $s['Set']['public'] == 0);
		if (!$isOwner && !(Auth::isAdmin() && $isSandbox))
		{
			CookieFlash::set('Not authorized', 'error');
			return $this->redirect('/sets');
		}

		$this->Set->delete($setID);

		// Remove the set's uploaded image folder
		$setImageDir = WWW_ROOT . 'img' . DS . 'sets' . DS . $setID;
		if (is_dir($setImageDir))
		{
			foreach (glob($setImageDir . DS . '*') ?: [] as $file)
				if (is_file($file))
					unlink($file);
			@rmdir($setImageDir);
		}

		if (Auth::isAdmin())
			$this->redirect('/sets/sandbox');
		else
			$this->redirect('/sets/mine');
	}

	/** @deprecated Use delete() instead */
	public function remove()
	{
		return $this->delete(null);
	}

	public function index(): void
	{
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('AchievementCondition');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('SetConnection');
		$this->loadModel('UserContribution');
		$this->set('_page', 'set');
		$this->set('_title', 'Tsumego Hero - Collections');

		$setTiles = [];
		$difficultyTiles = [];
		$sets = [];
		$tagList = [];

		$overallCounter = 0;
		$problemsCount = 0;
		$achievementUpdate = [];

		$tsumegoFilters = new TsumegoFilters();
		//setTiles
		$setsRaw = $this->Set->find('all', [
			'order' => ['Set.order', 'Set.id'],
			'conditions' => ['public' => 1],
		]) ?: [];
		foreach ($setsRaw as $set)
			$setTiles[] = $set['Set']['title'];

		//difficultyTiles
		$dt = SetsController::getExistingRanksArray();
		foreach ($dt as $item)
			$difficultyTiles[] = $item['rank'];

		//tagTiles
		$tags = $this->Tag->find('all', [
			'conditions' => [
				'approved' => 1,
				'NOT' => ['name' => 'Tsumego'],
			],
		]);

		$tagTiles = [];
		foreach ($tags as $tag)
			$tagTiles[] = $tag['Tag']['name'];

		$setsSelector = new SetsSelector($tsumegoFilters);

		if (Auth::isLoggedIn())
		{
			$aCondition = $this->AchievementCondition->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'set']]) ?: [];
			$aCondition['AchievementCondition']['category'] = 'set';
			$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			$aCondition['AchievementCondition']['value'] = $overallCounter;
			ClassRegistry::init('AchievementCondition')->save($aCondition);
			$achievementChecker = new AchievementChecker();
			$achievementChecker->checkSetCompletedAchievements();
			$achievementChecker->finalize();
			$this->set('achievementUpdate', $achievementChecker->updated);
			Auth::saveUser();
		}

		$ranksArray = SetsController::getExistingRanksArray();
		foreach ($ranksArray as &$rank)
		{
			$rank['id'] = $rank['rank'];
			$rank['name'] = $rank['rank'];
		}

		if ($tsumegoFilters->query == 'topics' && empty($tsumegoFilters->sets))
			$queryRefresh = false;
		elseif ($tsumegoFilters->query == 'difficulty' && empty($tsumegoFilters->ranks))
			$queryRefresh = false;
		elseif ($tsumegoFilters->query == 'tags' && empty($tsumegoFilters->tags))
			$queryRefresh = false;
		else
			$queryRefresh = true;



		$this->set('setsSelector', $setsSelector);
		$this->set('ranksArray', $ranksArray);
		$this->set('tagList', $tagList);
		$this->set('setTiles', $setTiles);
		$this->set('difficultyTiles', $difficultyTiles);
		$this->set('tagTiles', $tagTiles);
		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('queryRefresh', $queryRefresh);
	}

	public static function getDifficultyAndSolved($currentTagIds, $tsumegoStatusMap)
	{
		$tagTsumegoDifficulty = ClassRegistry::init('Tsumego')->find('all', ['conditions' => ['id' => $currentTagIds]]);
		if (!$tagTsumegoDifficulty)
			$tagTsumegoDifficulty = [];
		$tagDifficultyResult = 0;
		$statusCounter = 0;
		$tagTsumegoDifficultyCount2 = count($tagTsumegoDifficulty);
		for ($j = 0; $j < $tagTsumegoDifficultyCount2; $j++)
		{
			$tagDifficultyResult += $tagTsumegoDifficulty[$j]['Tsumego']['rating'];
			if (isset($tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']]))
				if ($tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'C')
					$statusCounter++;
		}
		if (count($tagTsumegoDifficulty) > 0)
			$tagDifficultyResult = $tagDifficultyResult / count($tagTsumegoDifficulty);
		else
			$tagDifficultyResult = 0;
		$tagDifficultyResult = Rating::getReadableRankFromRating($tagDifficultyResult);
		$return = [];
		$return['difficulty'] = $tagDifficultyResult;
		if (count($currentTagIds) > 0)
			$return['solved'] = Util::getPercentButAvoid100UntilComplete($statusCounter, count($currentTagIds));
		else
			$return['solved'] = 0;

		return $return;
	}

	/**
	 * Gets the first unsolved set connection ID from a collection of tsumego buttons.
	 * Falls back to the first button if all are solved.
	 *
	 * @param TsumegoButtons $tsumegoButtons Iterator of TsumegoButton objects
	 * @return int|null The setConnectionID of the first unsolved button, or first button if all solved, or null if empty
	 */
	private function getFirstUnsolvedSetConnectionId($tsumegoButtons)
	{
		if (empty($tsumegoButtons))
			return null;
		if ($firstUnsolvedButton = array_find((array) $tsumegoButtons, function ($tsumegoButton) {
			return !TsumegoUtil::isSolvedStatus($tsumegoButton->status);
		}))
			return $firstUnsolvedButton->setConnectionID;
		if ($firstRecentlyUnsolved = array_find((array) $tsumegoButtons, function ($tsumegoButton) {
			return !TsumegoUtil::isRecentlySolved($tsumegoButton->status);
		}))
			return $firstRecentlyUnsolved->setConnectionID;
		return $tsumegoButtons[0]->setConnectionID;
	}

	private function decodeQueryType($input)
	{
		if (is_numeric($input))
			return 'topics';
		try
		{
			Rating::getRankFromReadableRank($input);
			return 'difficulty';
		}
		catch (Exception $e)
		{
			return 'tags';
		}
	}

	public function addTsumego($setID)
	{
		if (!Auth::isLoggedIn())
		{
			$this->autoRender = false;
			$this->response->statusCode(401);
			return;
		}

		if ($setID === 'favorites')
		{
			$set = $this->_getOrCreateDefaultFavoritesSet();
			$setID = $set['Set']['id'];
		}
		else
		{
			$set = ClassRegistry::init('Set')->findById($setID);
			if (!$set)
			{
				$this->autoRender = false;
				$this->response->statusCode(404);
				return;
			}
		}
		$set = $set['Set'];

		if (!Auth::isAdmin() && $set['user_id'] != Auth::getUserID())
		{
			$this->autoRender = false;
			$this->response->statusCode(403);
			return;
		}

		$tsumegoId = (int) ($this->data['tsumego_id'] ?? 0);
		if (!$tsumegoId)
		{
			$this->autoRender = false;
			$this->response->statusCode(400);
			return;
		}
		if (!ClassRegistry::init('Tsumego')->findById($tsumegoId))
		{
			$this->autoRender = false;
			$this->response->statusCode(404);
			return;
		}

		$existing = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setID, 'tsumego_id' => $tsumegoId],
		]);
		if ($existing)
		{
			CookieFlash::set('Already in set', 'info');
			return $this->redirect('/sets/view/' . $setID);
		}

		$lastSc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setID],
			'order' => 'num DESC',
		]);
		$nextNum = $lastSc ? $lastSc['SetConnection']['num'] + 1 : 1;

		$sc = [];
		$sc['SetConnection']['set_id'] = $setID;
		$sc['SetConnection']['tsumego_id'] = $tsumegoId;
		$sc['SetConnection']['num'] = $nextNum;
		ClassRegistry::init('SetConnection')->create();
		ClassRegistry::init('SetConnection')->save($sc);

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$this->autoRender = false;
			$this->response->type('application/json');
			$this->response->body(json_encode(['contains' => true]));
			return;
		}

		CookieFlash::set('Added to set', 'success');
		return $this->redirect('/sets/view/' . $setID);
	}

	/**
	 * Create a new tsumego and add it to a set. Admin only.
	 */
	public function createAndAddTsumego($setID)
	{
		if (!Auth::isAdmin())
		{
			$this->autoRender = false;
			$this->response->statusCode(403);
			return;
		}

		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
		{
			$this->autoRender = false;
			$this->response->statusCode(404);
			return;
		}

		if (!isset($this->data['order']))
		{
			$this->autoRender = false;
			$this->response->statusCode(400);
			return;
		}

		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->getDataSource()->begin();

		try
		{
			$tsumego = [];
			$tsumego['num'] = $this->data['order'];
			$tsumego['author'] = Auth::getUser()['name'];
			$tsumegoModel->create();
			$tsumegoModel->save($tsumego);

			$tsumego['id'] = $tsumegoModel->id;
			$setConnection = [];
			$setConnection['set_id'] = $setID;
			$setConnection['tsumego_id'] = $tsumego['id'];
			$setConnection['num'] = $this->data['order'];
			ClassRegistry::init('SetConnection')->create();
			ClassRegistry::init('SetConnection')->save($setConnection);

			$fileUpload = isset($_FILES['adminUpload']) && $_FILES['adminUpload']['error'] === UPLOAD_ERR_OK ? $_FILES['adminUpload'] : null;
			$sgfDataOrFile = $this->data['sgf'] ?? $fileUpload;

			if ($sgfDataOrFile)
				ClassRegistry::init('Sgf')->uploadSgf($sgfDataOrFile, $tsumego['id'], Auth::getUserID(), Auth::isAdmin());
			$tsumegoModel->getDataSource()->commit();
			AdminActivityLogger::log(AdminActivityType::PROBLEM_ADD, $tsumegoModel->id, $setID);
		}
		catch (Exception $e)
		{
			$tsumegoModel->getDataSource()->rollback();
			CookieFlash::set('Unexpected error:' . $e->getMessage(), 'error');
		}
		return $this->redirect('/sets/view/' . $setID);
	}

	/**
	 * Get or create the user's default "Favorites" set.
	 */
	private function _getOrCreateDefaultFavoritesSet(): array
	{
		$userId = Auth::getUserID();
		$defaultSetId = Auth::getUser()['default_set_id'] ?? null;

		if ($defaultSetId)
		{
			$set = ClassRegistry::init('Set')->findById($defaultSetId);
			if ($set)
				return $set;
		}

		// No default set or it was deleted, create one
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save([
			'Set' => [
				'user_id' => $userId,
				'title' => 'Favorites',
				'public' => 0,
				'image' => null,
				'author' => Auth::getUser()['name'],
				'order' => Constants::$DEFAULT_SET_ORDER,
			],
		]);

		// Update user.default_set_id
		$userModel = ClassRegistry::init('User');
		$userModel->id = $userId;
		$userModel->saveField('default_set_id', $setModel->id);
		Auth::getUser()['default_set_id'] = $setModel->id;

		return $setModel->findById($setModel->id);
	}

	/**
	 * Remove a tsumego from a set.
	 */
	public function removeTsumego($setID)
	{
		if (!Auth::isLoggedIn())
		{
			$this->autoRender = false;
			$this->response->statusCode(401);
			return;
		}

		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
		{
			$this->autoRender = false;
			$this->response->statusCode(404);
			return;
		}

		// Auth: admin or set owner
		if (!Auth::isAdmin() && $set['Set']['user_id'] != Auth::getUserID())
		{
			$this->autoRender = false;
			$this->response->statusCode(403);
			return;
		}

		$tsumegoId = $this->data['tsumego_id'] ?? null;
		if (!$tsumegoId)
		{
			$this->autoRender = false;
			$this->response->statusCode(400);
			return;
		}

		ClassRegistry::init('SetConnection')->deleteAll([
			'set_id' => $setID,
			'tsumego_id' => (int) $tsumegoId,
		]);

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$this->autoRender = false;
			$this->response->type('application/json');
			$this->response->body(json_encode(['contains' => false]));
			return;
		}

		CookieFlash::set('Removed from set', 'success');
		return $this->redirect('/sets/view/' . $setID);
	}

	/**
	 * Swap the order of two adjacent set_connections.
	 */
	public function reorderTsumego($setID)
	{
		if (!Auth::isLoggedIn())
		{
			$this->autoRender = false;
			$this->response->statusCode(401);
			return;
		}

		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
		{
			$this->autoRender = false;
			$this->response->statusCode(404);
			return;
		}
		if (!Auth::isAdmin() && $set['Set']['user_id'] != Auth::getUserID())
		{
			$this->autoRender = false;
			$this->response->statusCode(403);
			return;
		}

		$tsumegoId = $_GET['tsumego_id'] ?? $this->data['tsumego_id'] ?? null;
		$dir = $_GET['dir'] ?? $this->data['dir'] ?? null;

		if (!$tsumegoId || !in_array($dir, ['up', 'down']))
		{
			$this->autoRender = false;
			$this->response->statusCode(400);
			return;
		}

		$scModel = ClassRegistry::init('SetConnection');
		$current = $scModel->find('first', [
			'conditions' => ['set_id' => $setID, 'tsumego_id' => (int) $tsumegoId],
		]);
		if (!$current)
		{
			$this->autoRender = false;
			$this->response->statusCode(404);
			return;
		}

		$currentNum = $current['SetConnection']['num'];
		$adjacentNum = $dir === 'up' ? $currentNum - 1 : $currentNum + 1;

		$adjacent = $scModel->find('first', [
			'conditions' => ['set_id' => $setID, 'num' => $adjacentNum],
		]);
		if (!$adjacent)
			return $this->redirect('/sets/view/' . $setID);

		// Swap num values
		$scModel->id = $current['SetConnection']['id'];
		$scModel->saveField('num', $adjacentNum);
		$scModel->id = $adjacent['SetConnection']['id'];
		$scModel->saveField('num', $currentNum);

		CookieFlash::set('Reordered', 'success');
		return $this->redirect('/sets/view/' . $setID);
	}

	public function view(string|int|null $id = null, int $partition = 1): void
	{
		// transferring from 1 indexed for humans to 0 indexed for us programmers.
		$partition = $partition - 1;

		// Redirect old /sets/view/favorites to user's default set
		if ($id === 'favorites')
		{
			if (!Auth::isLoggedIn())
			{
				$this->redirect('/');
				return;
			}
			$defaultSet = $this->_getOrCreateDefaultFavoritesSet();
			$this->redirect('/sets/view/' . $defaultSet['Set']['id']);
			return;
		}

		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('AdminActivity');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('ProgressDeletion');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('User');
		$this->loadModel('UserContribution');

		if ($id === null)
		{
			$this->redirect('/sets/mine');
			return;
		}

		if ($id != '1')
			$this->set('_page', 'set');
		else
			$this->set('_page', 'favs');
		$tsIds = [];
		$refreshView = false;
		$avgTime = 0;
		$accuracy = 0;
		$allVcActive = false;
		$allVcInactive = false;
		$allArActive = false;
		$allArInactive = false;
		$allPassActive = false;
		$allPassInactive = false;
		$pdCounter = 0;
		$acS = null;
		$acA = null;

		$queryType = self::decodeQueryType($id);

		if ($queryType == 'topics' && is_numeric($id))
		{
			$set = $this->Set->findById($id);
			if (!$set)
				throw new NotFoundException("Set not found");
		}

		if ($queryType == 'tags')
		{
			$tag = $this->Tag->findByName($id);
			if (!$tag)
				throw new NotFoundException("Tag not found");
		}

		$tsumegoFilters = new TsumegoFilters($queryType);
		if (Auth::isLoggedIn())
			if (Auth::isAdmin())
			{
				$aad = $this->AdminActivity->find('first', ['order' => 'id DESC']);
				// Check if last activity was a problem deletion - if so, actually delete it
				if (isset($aad['AdminActivity']['type']) && $aad['AdminActivity']['type'] == AdminActivityType::PROBLEM_DELETE)
				{
					$scDelete = $this->SetConnection->find('first', ['order' => 'created DESC', 'conditions' => ['tsumego_id' => $aad['AdminActivity']['tsumego_id']]]);
					$this->SetConnection->delete($scDelete['SetConnection']['id']);
					$this->Tsumego->delete($aad['AdminActivity']['tsumego_id']);
				}
			}
		Util::setCookie('lastSet', $id);
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $id);
		$this->set('startingSetConnectionID', $this->getFirstUnsolvedSetConnectionId($tsumegoButtons));

		if ($tsumegoFilters->query == 'difficulty')
		{
			$set = [];
			$set['Set']['id'] = $id;
			$set['Set']['title'] = $id . $tsumegoButtons->getPartitionTitleSuffix();
			$set['Set']['multiplier'] = 1;
			$set['Set']['public'] = 1;
			$elo = Rating::getRankMinimalRatingFromReadableRank($id);
			$set['Set']['difficulty'] = $elo;
		}
		elseif ($tsumegoFilters->query == 'tags')
		{
			$set = [];
			$set['Set']['id'] = $id;
			$set['Set']['image'] = '';
			$set['Set']['multiplier'] = 1;
			$set['Set']['public'] = 1;
			$tagName = $this->Tag->findByName($id);
			if ($tagName && isset($tagName['Tag']['description']))
				$set['Set']['description'] = $tagName['Tag']['description'];
			$set['Set']['title'] = $id . $tsumegoButtons->getPartitionTitleSuffix();
		}
		elseif ($tsumegoFilters->query == 'topics')
		{
			$set = ClassRegistry::init('Set')->findById($id);
			if (!$set)
				throw new NotFoundException("Set not found");

			// Owner check: private sets (public=0, user_id!=NULL) only visible to owner or admin
			if ($set['Set']['public'] == 0 && $set['Set']['user_id'] !== null)
				if (!Auth::isAdmin() && $set['Set']['user_id'] != Auth::getUserID())
					throw new NotFoundException("Set not found");

			$set['Set']['title'] = $set['Set']['title'] . $tsumegoButtons->getPartitionTitleSuffix();
			$allArActive = true;
			$allArInactive = true;
			$allPassActive = true;
			$allPassInactive = true;
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				if (!$tsumegoButton->alternativeResponse)
					$allArActive = false;
				if (!$tsumegoButton->passEnabled)
					$allPassActive = false;
			}
			foreach ($tsumegoButtons as $tsumegoButton)
				$tsIds [] = $tsumegoButton->tsumegoID;
			if ($set['Set']['public'] == 0 && $set['Set']['user_id'] === null)
				$this->set('_page', 'sandbox');
			$this->set('isFav', false);
			$this->set('isOwner', Auth::isLoggedIn() && $set['Set']['user_id'] == Auth::getUserID());
			if (isset($this->data['Set']['title']))
			{
				$this->Set->create();
				$changeSet = $set;
				$changeSet['Set']['title'] = $this->data['Set']['title'];
				$changeSet['Set']['title2'] = $this->data['Set']['title2'];
				$this->set('data', $changeSet['Set']['title']);
				$this->Set->save($changeSet, true);
				$oldTitle = $set['Set']['title'];
				$set = $this->Set->findById($id);
				if ($this->_isElevatedSetEdit($set))
					AdminActivityLogger::log(AdminActivityType::SET_TITLE_EDIT, null, $id, $oldTitle, $this->data['Set']['title']);
			}
			if (isset($this->data['Set']['description']))
			{
				$this->Set->create();
				$changeSet = $set;
				$changeSet['Set']['description'] = $this->_sanitizeDescription($this->data['Set']['description']);
				$this->set('data', $changeSet['Set']['description']);
				$this->Set->save($changeSet, true);
				$oldDescription = $set['Set']['description'];
				$set = $this->Set->findById($id);
				if ($this->_isElevatedSetEdit($set))
					AdminActivityLogger::log(AdminActivityType::SET_DESCRIPTION_EDIT, null, $id, $oldDescription, $this->data['Set']['description']);
			}
			if (isset($this->data['Set']['setDifficulty']) && Auth::isAdmin())
				if ($this->data['Set']['setDifficulty'] != 1200 && $this->data['Set']['setDifficulty'] >= 900 && $this->data['Set']['setDifficulty'] <= 2900)
				{
					$setDifficultyTsumegoSet = TsumegoUtil::collectTsumegosFromSet($set['Set']['id']);
					$setDifficulty = $this->data['Set']['setDifficulty'];
					$setDifficultyTsumegoSetCount = count($setDifficultyTsumegoSet);
					for ($i = 0; $i < $setDifficultyTsumegoSetCount; $i++)
					{
						$setDifficultyTsumegoSet[$i]['Tsumego']['rating']
							= Util::clampOptional(
								$this->data['Set']['setDifficulty'],
								$setDifficultyTsumegoSet[$i]['Tsumego']['minimum_rating'],
								$setDifficultyTsumegoSet[$i]['Tsumego']['maximum_rating']);
						$this->Tsumego->save($setDifficultyTsumegoSet[$i]);
					}
					AdminActivityLogger::log(AdminActivityType::SET_RATING_EDIT, null, $id);
				}
			if (isset($this->data['Set']['color']))
			{
				$this->Set->create();
				$changeSet = $set;
				$changeSet['Set']['color'] = $this->data['Set']['color'];
				$this->set('data', $changeSet['Set']['color']);
				$this->Set->save($changeSet, true);
				$oldColor = $set['Set']['color'];
				$set = $this->Set->findById($id);
				if ($this->_isElevatedSetEdit($set))
					AdminActivityLogger::log(AdminActivityType::SET_COLOR_EDIT, null, $id, $oldColor, $this->data['Set']['color']);
			}
			if (isset($this->data['Set']['order']))
			{
				$this->Set->create();
				$changeSet = $set;
				$changeSet['Set']['order'] = $this->data['Set']['order'];
				$this->set('data', $changeSet['Set']['order']);
				$this->Set->save($changeSet, true);
				$oldOrder = $set['Set']['order'];
				$set = $this->Set->findById($id);
				if ($this->_isElevatedSetEdit($set))
					AdminActivityLogger::log(AdminActivityType::SET_ORDER_EDIT, null, $id, $oldOrder, $this->data['Set']['order']);
			}
			// Handle image upload from the view page admin panel
			if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK
				&& (Auth::isAdmin() || (Auth::isLoggedIn() && $set['Set']['user_id'] == Auth::getUserID())))
			{
				$file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

				if (!in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp']))
					CookieFlash::set('png/jpg/webp allowed.', 'error');
				elseif ($_FILES['image']['size'] > 2097152)
					CookieFlash::set('The file is too large (max 2MB).', 'error');
				else
				{
					$setId = $set['Set']['id'];
					$oldImage = $set['Set']['image'];

					try
					{
						$processed = SetImage::process($_FILES['image']['tmp_name'], $file_ext);

						$setDir = WWW_ROOT . 'img' . DS . 'sets' . DS . $setId;
						if (!is_dir($setDir))
							mkdir($setDir, 0775, true);

						$filename = 'sets/' . $setId . '/' . substr($processed['hash'], 0, 16) . '.webp';
						$uploadPath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $filename);
						file_put_contents($uploadPath, $processed['data']);

						// Only delete user-uploaded images (inside sets/), never shared assets
						if ($oldImage && str_starts_with($oldImage, 'sets/') && $oldImage !== $filename)
						{
							$oldPath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $oldImage);
							if (file_exists($oldPath))
								unlink($oldPath);
						}

						$set['Set']['image'] = $filename;
						$this->Set->id = $setId;
						$this->Set->saveField('image', $filename);
						CookieFlash::set('Image uploaded', 'success');
					}
					catch (Exception $e)
					{
						CookieFlash::set('Image upload failed: ' . $e->getMessage(), 'error');
					}
				}
			}

			if (isset($this->data['Settings']))
			{
				if ($this->data['Settings']['r39'] == 'on')
				{
					foreach ($tsumegoButtons as $tsumegoButton)
					{
						$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
						$tsumego['alternative_response'] = true;
						ClassRegistry::init('Tsumego')->save($tsumego);
					}
					$allArActive = true;
					AdminActivityLogger::log(AdminActivityType::SET_ALTERNATIVE_RESPONSE, null, $id, null, '1');
				}
				if ($this->data['Settings']['r39'] == 'off')
				{
					foreach ($tsumegoButtons as $tsumegoButton)
					{
						$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
						$tsumego['alternative_response'] = false;
						ClassRegistry::init('Tsumego')->save($tsumego);
					}
					$allArInactive = true;
					AdminActivityLogger::log(AdminActivityType::SET_ALTERNATIVE_RESPONSE, null, $id, null, '0');
				}
				if ($this->data['Settings']['r43'] == 'yes')
				{
					foreach ($tsumegoButtons as $tsumegoButton)
					{
						$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
						$tsumego['pass'] = true;
						ClassRegistry::init('Tsumego')->save($tsumego);
					}
					$allPassActive = true;
					AdminActivityLogger::log(AdminActivityType::SET_PASS_MODE, null, $id, null, '1');
				}
				if ($this->data['Settings']['r43'] == 'no')
				{
					foreach ($tsumegoButtons as $tsumegoButton)
					{
						$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
						$tsumego['pass'] = false;
						ClassRegistry::init('Tsumego')->save($tsumego);
					}
					$allPassInactive = true;
					AdminActivityLogger::log(AdminActivityType::SET_PASS_MODE, null, $id, null, '0');
				}
				$this->set('formRedirect', true);
			}
		}
		else
			throw new BadRequestException('Unknown query type: ' . $tsumegoFilters->query);

		if ($tsumegoButtons->description)
			$set['Set']['description'] = $tsumegoButtons->description;

		$this->set('_title', $set['Set']['title'] . ' on Tsumego Hero');

		if (Auth::isLoggedIn() && $tsumegoFilters->query == 'topics')
		{
			$ur = $this->TsumegoAttempt->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]) ?: [];
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				$urTemp = [];
				$urSum = '';
				$tsumegoButton->seconds = 0;
				$solvedSeconds = []; // Track all successful solve times to find minimum (best)
				$urCount2 = count($ur);
				for ($j = 0; $j < $urCount2; $j++)
					if ($tsumegoButton->tsumegoID == $ur[$j]['TsumegoAttempt']['tsumego_id'])
					{
						array_push($urTemp, $ur[$j]);
						if ($ur[$j]['TsumegoAttempt']['solved'])
							$solvedSeconds[] = $ur[$j]['TsumegoAttempt']['seconds'];

						if (!$ur[$j]['TsumegoAttempt']['solved'])
						{
							$mis = $ur[$j]['TsumegoAttempt']['misplays'];
							if ($mis == 0)
								$mis = 1;
							while ($mis > 0)
							{
								$urSum .= 'F';
								$mis--;
							}
						}
						else
							$urSum .= $ur[$j]['TsumegoAttempt']['solved'];
					}
				// Use minimum (best) solve time from all successful attempts
				if (!empty($solvedSeconds))
					$tsumegoButton->seconds = min($solvedSeconds);
				$tsumegoButton->performance = $urSum;
			}
		}

		$problemSolvedPercent = $tsumegoButtons->getProblemsSolvedPercent();
		$setRating = $tsumegoButtons->getProblemsRating();
		$this->set('setRating', $setRating);

		$this->set('problemSolvedPercent', $problemSolvedPercent);

		$scoring = true;
		if (Auth::isLoggedIn() && $tsumegoFilters->query == 'topics')
		{
			$pd = $this->ProgressDeletion->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'set_id' => $id]]) ?: [];
			$pdCounter = 0;
			$pdCount = count($pd);
			for ($i = 0; $i < $pdCount; $i++)
			{
				$date = date_create($pd[$i]['ProgressDeletion']['created']);
				$pd[$i]['ProgressDeletion']['d'] = $date->format('Y') . '-' . $date->format('m');
				if (date('Y-m') == $pd[$i]['ProgressDeletion']['d'])
					$pdCounter++;
			}
			$urSecCounter = 0;
			$urSecAvg = 0;
			$pSsum = 0;
			$pFsum = 0;
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				if ($tsumegoButton->seconds == 0)
					if (TsumegoUtil::isSolvedStatus($tsumegoButton->status))
						$tss = 60;
					else
						$tss = 0;
				else
					$tss = $tsumegoButton->seconds;
				$urSecAvg += $tss;
				$urSecCounter++;

				if ($tsumegoButton->performance == '')
					if (TsumegoUtil::isSolvedStatus($tsumegoButton->status))
						$tss2 = 'F';
					else
						$tss2 = '';
				else
					$tss2 = $tsumegoButton->performance;
				$pS = substr_count($tss2, '1');
				$pF = substr_count($tss2, 'F');
				$pSsum += $pS;
				$pFsum += $pF;
			}
			if ($urSecCounter == 0)
				$avgTime = 60;
			else
				$avgTime = round($urSecAvg / $urSecCounter, 2);
			if ($pSsum + $pFsum == 0)
				$accuracy = 0;
			else
				$accuracy = round($pSsum / ($pSsum + $pFsum) * 100, 2);
			$avgTime2 = $avgTime;
			if ($problemSolvedPercent >= 100)
			{
				$achievementChecker = new AchievementChecker();
				if ($set['Set']['id'] != 210)
				{
					$this->updateAchievementConditions($set['Set']['id'], $avgTime2, $accuracy);
					$achievementChecker->checkSetAchievements($set['Set']['id'], $setRating);
				}
				if ($id == 50 || $id == 52 || $id == 53 || $id == 54)
					$achievementChecker->setAchievementSpecial('cc1');
				elseif ($id == 41 || $id == 49 || $id == 65 || $id == 66)
					$achievementChecker->setAchievementSpecial('cc2');
				elseif ($id == 186 || $id == 187 || $id == 196 || $id == 203)
					$achievementChecker->setAchievementSpecial('cc3');
				elseif ($id == 190 || $id == 193 || $id == 198)
					$achievementChecker->setAchievementSpecial('1000w1');
				elseif ($id == 216)
					$achievementChecker->setAchievementSpecial('1000w2');
				$achievementChecker->finalize();
				$this->set('achievementUpdate', $achievementChecker->updated);
			}

			$acS = $this->AchievementCondition->find('first', [
				'order' => 'value ASC',
				'conditions' => [
					'set_id' => $id,
					'user_id' => Auth::getUserID(),
					'category' => 's']]);
			$acA = $this->AchievementCondition->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'set_id' => $id,
					'user_id' => Auth::getUserID(),
					'category' => '%']]);
		}
		else
			$scoring = false;

		$allTags = $this->Tag->find('all') ?: [];
		$allTagsSorted = [];
		$allTagsKeys = [];
		$allTagsCount = count($allTags);
		for ($i = 0; $i < $allTagsCount; $i++)
		{
			array_push($allTagsSorted, $allTags[$i]['Tag']['name']);
			$allTagsKeys[$allTags[$i]['Tag']['name']] = $allTags[$i];
		}
		sort($allTagsSorted);
		$s2Tags = [];
		$allTagsSortedCount = count($allTagsSorted);
		for ($i = 0; $i < $allTagsSortedCount; $i++)
			array_push($s2Tags, $allTagsKeys[$allTagsSorted[$i]]);

		$allTags = $s2Tags;

		if ($tsumegoFilters->query == 'topics')
		{
			$this->set('allVcActive', $allVcActive);
			$this->set('allVcInactive', $allVcInactive);
			$this->set('allArActive', $allArActive);
			$this->set('allArInactive', $allArInactive);
			$this->set('allPassActive', $allPassActive);
			$this->set('allPassInactive', $allPassInactive);
			$this->set('pdCounter', $pdCounter);
			$this->set('acS', $acS);
			$this->set('acA', $acA);
		}

		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('allTags', $allTags);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('set', $set);
		$this->set('refreshView', $refreshView);
		$this->set('avgTime', $avgTime);
		$this->set('accuracy', $accuracy);
		$this->set('scoring', $scoring);
		$this->set('partition', $partition);
	}

	/**
	 * @param int $sid Set ID
	 * @param float $avgTime Average time
	 * @param float $accuracy Accuracy percentage
	 * @return void
	 */
	public function updateAchievementConditions($sid, $avgTime, $accuracy)
	{
		$uid = Auth::getUserID();
		$acS = $this->AchievementCondition->find('first', ['order' => 'value ASC', 'conditions' => ['set_id' => $sid, 'user_id' => $uid, 'category' => 's']]);
		$acA = $this->AchievementCondition->find('first', ['order' => 'value DESC', 'conditions' => ['set_id' => $sid, 'user_id' => $uid, 'category' => '%']]);

		if ($acS == null)
		{
			$aCond = [];
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $avgTime;
			$aCond['AchievementCondition']['category'] = 's';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		}
		elseif ($avgTime < $acS['AchievementCondition']['value'])
		{
			$acS['AchievementCondition']['value'] = $avgTime;
			$this->AchievementCondition->save($acS);
		}
		if ($acA == null)
		{
			$aCond = [];
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $accuracy;
			$aCond['AchievementCondition']['category'] = '%';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		}
		elseif ($accuracy > $acA['AchievementCondition']['value'])
		{
			$acA['AchievementCondition']['value'] = $accuracy;
			$this->AchievementCondition->save($acA);
		}
	}


	private function getDifficultyColor($difficulty = null)
	{
		if ($difficulty == 1)
			return '#33cc33';
		if ($difficulty == 2)
			return '#709533';
		if ($difficulty == 3)
			return '#2e3370';
		if ($difficulty == 4)
			return '#ac5d33';
		if ($difficulty == 5)
			return '#e02e33';

		return 'white';
	}

	private function getSizeColor($size = null)
	{
		$colors = [];
		array_push($colors, '#cc6600');
		array_push($colors, '#ac4e26');
		array_push($colors, '#963e3e');
		array_push($colors, '#802e58');
		array_push($colors, '#60167d');
		if ($size < 30)
			return $colors[0];
		if ($size < 60)
			return $colors[1];
		if ($size < 110)
			return $colors[2];
		if ($size < 202)
			return $colors[3];

		return $colors[4];
	}

	private function getDateColor($date = null)
	{
		$current = '20180705';
		$dist = $current - $date;

		if ($dist < 7)
			return '#0033cc';
		if ($dist < 100)
			return '#0f33ad';
		if ($dist < 150)
			return '#1f338f';
		if ($dist < 200)
			return '#2e3370';
		if ($dist < 300)
			return '#3d3352';
		if ($dist < 400)
			return '#4c3333';
		if ($dist < 500)
			return '#57331f';

		return '#663300';
	}

	private function getSolvedColor($percent = null)
	{
		$colors = [];

		array_push($colors, '#333333');
		array_push($colors, '#2e3d47');
		array_push($colors, '#2b4252');
		array_push($colors, '#29475c');
		array_push($colors, '#264c66');
		array_push($colors, '#245270');
		array_push($colors, '#21577a');
		array_push($colors, '#1f5c85');
		array_push($colors, '#1c618f');
		array_push($colors, '#1a6699');

		array_push($colors, '#176ba3');
		array_push($colors, '#1470ad');
		array_push($colors, '#1275b8');
		array_push($colors, '#0f7ac2');
		array_push($colors, '#0d80cc');
		array_push($colors, '#0a85d6');
		array_push($colors, '#088ae0');
		array_push($colors, '#058feb');
		array_push($colors, '#0394f5');
		array_push($colors, '#0099ff');

		array_push($colors, '#039cf8');
		array_push($colors, '#069ef2');
		array_push($colors, '#09a1eb');
		array_push($colors, '#0ca4e4');
		array_push($colors, '#10a6dd');
		array_push($colors, '#13a9d6');
		array_push($colors, '#16acd0');
		array_push($colors, '#19afc9');
		array_push($colors, '#1cb1c2');
		array_push($colors, '#1fb4bc');

		array_push($colors, '#22b7b5');
		array_push($colors, '#25b9ae');
		array_push($colors, '#28bca7');
		array_push($colors, '#2bbfa0');
		array_push($colors, '#2ec29a');
		array_push($colors, '#32c493');
		array_push($colors, '#35c78c');
		array_push($colors, '#38ca86');
		array_push($colors, '#3bcc7f');
		array_push($colors, '#3ecf78');
		$steps = 2.5;
		$colorsCount = count($colors);
		for ($i = 0; $i < $colorsCount; $i++)
		{
			if ($percent <= $steps)
				return $colors[$i];
			$steps += 2.5;
		}

		return '#333333';
	}

	public static function getExistingRanksArray()
	{
		$ranksArray = [];
		$ranksArray[0]['rank'] = '15k';
		$ranksArray[1]['rank'] = '14k';
		$ranksArray[2]['rank'] = '13k';
		$ranksArray[3]['rank'] = '12k';
		$ranksArray[4]['rank'] = '11k';
		$ranksArray[5]['rank'] = '10k';
		$ranksArray[6]['rank'] = '9k';
		$ranksArray[7]['rank'] = '8k';
		$ranksArray[8]['rank'] = '7k';
		$ranksArray[9]['rank'] = '6k';
		$ranksArray[10]['rank'] = '5k';
		$ranksArray[11]['rank'] = '4k';
		$ranksArray[12]['rank'] = '3k';
		$ranksArray[13]['rank'] = '2k';
		$ranksArray[14]['rank'] = '1k';
		$ranksArray[15]['rank'] = '1d';
		$ranksArray[16]['rank'] = '2d';
		$ranksArray[17]['rank'] = '3d';
		$ranksArray[18]['rank'] = '4d';
		$ranksArray[19]['rank'] = '5d';
		$ranksArray[20]['rank'] = '6d';
		$ranksArray[21]['rank'] = '7d';
		$ranksArray[22]['rank'] = '8d';
		$ranksArray[23]['rank'] = '9d';
		$ranksArray[0]['color'] = 'rgba(63,  201, 196, [o])';
		$ranksArray[1]['color'] = 'rgba(63, 190, 201, [o])';
		$ranksArray[2]['color'] = 'rgba(63, 173, 201, [o])';
		$ranksArray[3]['color'] = 'rgba(63, 157, 201, [o])';
		$ranksArray[4]['color'] = 'rgba(63, 141, 201, [o])';
		$ranksArray[5]['color'] = 'rgba(88, 158, 244, [o])';
		$ranksArray[6]['color'] = 'rgba(88, 140, 244, [o])';
		$ranksArray[7]['color'] = 'rgba(88, 122, 244, [o])';
		$ranksArray[8]['color'] = 'rgba(88, 103, 244, [o])';
		$ranksArray[9]['color'] = 'rgba(90, 88, 244, [o])';
		$ranksArray[10]['color'] = 'rgba(109, 88, 244, [o])';
		$ranksArray[11]['color'] = 'rgba(127, 88, 244, [o])';
		$ranksArray[12]['color'] = 'rgba(145, 88, 244, [o])';
		$ranksArray[13]['color'] = 'rgba(163, 88, 244, [o])';
		$ranksArray[14]['color'] = 'rgba(182, 88, 244, [o])';
		$ranksArray[15]['color'] = 'rgba(200, 88, 244, [o])';
		$ranksArray[16]['color'] = 'rgba(218, 88, 244, [o])';
		$ranksArray[17]['color'] = 'rgba(236, 88, 244, [o])';
		$ranksArray[18]['color'] = 'rgba(244, 88, 234, [o])';
		$ranksArray[19]['color'] = 'rgba(244, 88, 187, [o])';
		$ranksArray[20]['color'] = 'rgba(244, 88, 145, [o])';
		$ranksArray[21]['color'] = 'rgba(244, 88, 127, [o])';
		$ranksArray[22]['color'] = 'rgba(244, 88, 101, [o])';
		$ranksArray[23]['color'] = 'rgba(244, 88, 88, [o])';

		return $ranksArray;
	}

	public function resetProgress(int $setID, int $partition): mixed
	{
		$redirectUrl = '/sets/view/' . $setID . '/' . $partition;

		if (!Auth::isLoggedIn())
			return $this->redirect($redirectUrl);

		if ($this->data['reset-check'] != 'reset')
		{
			CookieFlash::set('Reset check wasn\'t correctly typed', 'error');
			return $this->redirect($redirectUrl);
		}

		$tsumegoFilters = new TsumegoFilters();
		if ($tsumegoFilters->collectionSize != 200)
		{
			CookieFlash::set('Reset is only possible for collection size 200', 'error');
			return $this->redirect($redirectUrl);
		}
		$tsumegoFilters->query = 'topics';
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition - 1, $setID);
		$tsumegoIDToClear = [];
		foreach ($tsumegoButtons as $tsumegoButton)
			$tsumegoIDToClear[] = $tsumegoButton->tsumegoID;

		if ($tsumegoButtons->getProblemsSolvedPercent() < 50)
			return $this->redirect($redirectUrl);

		Util::query("
DELETE tsumego_status FROM tsumego_status
WHERE tsumego_status.user_id = ? AND tsumego_status.tsumego_id IN(" . implode(',', $tsumegoIDToClear) . ")", [Auth::getUserID()]);
		$progresDeletion = [];
		$progresDeletion['user_id'] = Auth::getUserID();
		$progresDeletion['set_id'] = $setID;
		ClassRegistry::init('ProgressDeletion')->create();
		ClassRegistry::init('ProgressDeletion')->save($progresDeletion);
		return $this->redirect($redirectUrl);
	}

	public function changeCollectionSize(): mixed
	{
		$collectionSize = $this->data['collection_size'] ?? null;
		if ($collectionSize === null || $collectionSize === '')
		{
			CookieFlash::set('Collection size to change not provided', 'error');
			return $this->redirect('/sets');
		}
		$collectionSizeInt = filter_var($collectionSize, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 10, 'max_range' => 1000],
		]);
		if ($collectionSizeInt === false || $collectionSizeInt % 10 !== 0)
		{
			CookieFlash::set('Collection size must be a multiple of 10 between 10 and 1000', 'error');
			return $this->redirect('/sets');
		}
		Preferences::set('collection_size', $collectionSizeInt);
		return $this->redirect('/sets');
	}

	/**
	 * Sanitize set description HTML: strip images with external src.
	 */
	private function _sanitizeDescription(string $description): string
	{
		// Strip <img> tags with non-relative, non-data-URI src
		$description = preg_replace(
			'/<img[^>]+src=["\'](?!\/|data:image\/)[^"\']+["\'][^>]*>/i',
			'',
			$description
		);

		return $description;
	}

	/**
	 * True when an admin edits a set they do not own (elevated privilege).
	 */
	private function _isElevatedSetEdit(array $set): bool
	{
		return Auth::isAdmin() && $set['Set']['user_id'] != Auth::getUserID();
	}
}
