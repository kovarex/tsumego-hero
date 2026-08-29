<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoUtil', 'Utility');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('ConflictException', 'Lib/Error');
App::uses('TsumegoButton', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('SetsSelector', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('Progress', 'Utility');
App::uses('SetEditRenderer', 'Utility');
App::uses('SetImage', 'Utility');
App::uses('HtmlSanitizer', 'Utility');
App::uses('Constants', 'Utility');
App::uses('SetConnection', 'Model');

use App\Attribute\HttpPost;

class SetsController extends AppController
{
	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function sandbox()
	{
		$this->Authorization->authorize('Set');

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
			$sets[$i]['Set']['dateColor'] = $this->getDateColor(date('Ymd', strtotime($sets[$i]['Set']['created'])));

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
LEFT JOIN set_connection sc ON sc.set_id = s.id
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
		$this->Authorization->authorize('Set', 'create');

		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$redirect = false;
		$t = [];

		if (isset($this->data['Set']))
		{
			$isSandbox = isset($this->params['url']['sandbox']) && $this->Authorization->can('Set', 'createSandbox');

			$set = [];
			$set['Set']['title'] = $this->data['Set']['title'];
			$set['Set']['public'] = 0;
			if (isset($this->data['Set']['description']))
				$set['Set']['description'] = HtmlSanitizer::sanitize((string) $this->data['Set']['description']);
			if (isset($this->data['Set']['color']) && $this->data['Set']['color'] !== '')
				$set['Set']['color'] = $this->data['Set']['color'];
			else
				$set['Set']['color'] = '#5b9bd5';

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

			$this->redirect('/sets/edit/' . $this->Set->id);
			return;
		}
		$this->set('t', $t);
	}

	/**
	 * Edit a set: details, problems and (for admins) re-rate and solve-mode
	 * settings. Owner or admin only.
	 */
	public function edit($id = null)
	{
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');

		$set = $this->Set->findById((int) $id);
		if (!$set)
			throw new NotFoundException('Set not found');

		$this->Authorization->authorize($set);
		$canEditSettings = $this->Authorization->can('Set', 'editSettings');
		$isSandbox = ($set['Set']['public'] == 0 && $set['Set']['user_id'] === null);

		// Problems in this set, in display order
		$problems = Util::query("
SELECT sc.id AS set_connection_id, sc.num, sc.tsumego_id, t.rating,
	t.alternative_response, t.pass
FROM set_connection sc
JOIN tsumego t ON t.id = sc.tsumego_id
WHERE sc.set_id = ?
ORDER BY sc.num ASC", [(int) $id]);

		// Tsumego buttons (status colors, tooltips, board previews) for the problem list
		$tsumegoButtons = new TsumegoButtons(new TsumegoFilters('topics'), null, null, (int) $id);

		if (isset($this->data['Set']))
		{
			$changeSet = $set;
			if (array_key_exists('title', $this->data['Set']))
				$changeSet['Set']['title'] = $this->data['Set']['title'];
			if (array_key_exists('title2', $this->data['Set']))
				$changeSet['Set']['title2'] = $this->data['Set']['title2'];
			if (array_key_exists('description', $this->data['Set']))
				$changeSet['Set']['description'] = HtmlSanitizer::sanitize((string) $this->data['Set']['description']);
			if (array_key_exists('color', $this->data['Set']) && $this->data['Set']['color'] !== '')
				$changeSet['Set']['color'] = $this->data['Set']['color'];
			if (array_key_exists('order', $this->data['Set']) && $this->data['Set']['order'] !== '')
				$changeSet['Set']['order'] = (int) $this->data['Set']['order'];

			$this->Set->create();
			$this->Set->save($changeSet, true);

			$oldTitle = $set['Set']['title'];
			$oldDescription = $set['Set']['description'];
			$oldColor = $set['Set']['color'];
			$oldOrder = $set['Set']['order'];
			$set = $this->Set->findById((int) $id);
			if ($this->_isElevatedSetEdit($set))
			{
				if ($oldTitle != $set['Set']['title'])
					AdminActivityLogger::log(AdminActivityType::SET_TITLE_EDIT, null, (int) $id, $oldTitle, $set['Set']['title']);
				if ($oldDescription != $set['Set']['description'])
					AdminActivityLogger::log(AdminActivityType::SET_DESCRIPTION_EDIT, null, (int) $id, $oldDescription, $set['Set']['description']);
				if ($oldColor != $set['Set']['color'])
					AdminActivityLogger::log(AdminActivityType::SET_COLOR_EDIT, null, (int) $id, $oldColor, $set['Set']['color']);
				if ($oldOrder != $set['Set']['order'])
					AdminActivityLogger::log(AdminActivityType::SET_ORDER_EDIT, null, (int) $id, Util::strOrNull($oldOrder), Util::strOrNull($set['Set']['order']));
			}

			CookieFlash::set('Set saved', 'success');
		}

		// Re-rate every problem in this set (admin only)
		if ($canEditSettings && isset($this->data['Set']['setDifficulty']))
			if ($this->data['Set']['setDifficulty'] != 1200 && $this->data['Set']['setDifficulty'] >= 900 && $this->data['Set']['setDifficulty'] <= 2900)
			{
				foreach ($problems as $problem)
				{
					$tsumego = ClassRegistry::init('Tsumego')->findById($problem['tsumego_id']);
					$tsumego['Tsumego']['rating']
						= Util::clampOptional(
							$this->data['Set']['setDifficulty'],
							$tsumego['Tsumego']['minimum_rating'],
							$tsumego['Tsumego']['maximum_rating']);
					$this->Tsumego->save($tsumego);
				}
				AdminActivityLogger::log(AdminActivityType::SET_RATING_EDIT, null, (int) $id);
			}

		// Alternative response / pass mode for all problems (admin only)
		if ($canEditSettings && isset($this->data['Settings']))
		{
			if ($this->data['Settings']['r39'] == 'on')
			{
				foreach ($problems as $problem)
				{
					$tsumego = ClassRegistry::init('Tsumego')->findById($problem['tsumego_id']);
					$tsumego['Tsumego']['alternative_response'] = true;
					ClassRegistry::init('Tsumego')->save($tsumego);
				}
				AdminActivityLogger::log(AdminActivityType::SET_ALTERNATIVE_RESPONSE, null, (int) $id, null, '1');
			}
			if ($this->data['Settings']['r39'] == 'off')
			{
				foreach ($problems as $problem)
				{
					$tsumego = ClassRegistry::init('Tsumego')->findById($problem['tsumego_id']);
					$tsumego['Tsumego']['alternative_response'] = false;
					ClassRegistry::init('Tsumego')->save($tsumego);
				}
				AdminActivityLogger::log(AdminActivityType::SET_ALTERNATIVE_RESPONSE, null, (int) $id, null, '0');
			}
			if ($this->data['Settings']['r43'] == 'yes')
			{
				foreach ($problems as $problem)
				{
					$tsumego = ClassRegistry::init('Tsumego')->findById($problem['tsumego_id']);
					$tsumego['Tsumego']['pass'] = true;
					ClassRegistry::init('Tsumego')->save($tsumego);
				}
				AdminActivityLogger::log(AdminActivityType::SET_PASS_MODE, null, (int) $id, null, '1');
			}
			if ($this->data['Settings']['r43'] == 'no')
			{
				foreach ($problems as $problem)
				{
					$tsumego = ClassRegistry::init('Tsumego')->findById($problem['tsumego_id']);
					$tsumego['Tsumego']['pass'] = false;
					ClassRegistry::init('Tsumego')->save($tsumego);
				}
				AdminActivityLogger::log(AdminActivityType::SET_PASS_MODE, null, (int) $id, null, '0');
			}
		}

		// Handle image removal
		if (!empty($this->data['Set']['remove_image']))
		{
			$oldImage = $set['Set']['image'];
			if ($oldImage && str_starts_with($oldImage, 'sets/'))
			{
				$oldPath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $oldImage);
				if (file_exists($oldPath))
					unlink($oldPath);
			}
			$this->Set->id = (int) $id;
			$this->Set->saveField('image', '');
			$set['Set']['image'] = '';
			CookieFlash::set('Image removed', 'success');
		}

		// Handle image upload from the details form
		if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK)
		{
			$file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

			if (!in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp']))
				CookieFlash::set('png/jpg/webp allowed.', 'error');
			elseif ($_FILES['image']['size'] > 2097152)
				CookieFlash::set('The file is too large (max 2MB).', 'error');
			else
			{
				$setId = (int) $id;
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

		if (isset($this->data['Set']) || isset($this->data['Settings']))
			return $this->redirect('/sets/edit/' . (int) $id);

		// Solve-mode states for the admin settings panel
		$allArActive = true;
		$allArInactive = true;
		$allPassActive = true;
		$allPassInactive = true;
		foreach ($problems as $problem)
		{
			if (!$problem['alternative_response'])
				$allArActive = false;
			if ($problem['alternative_response'])
				$allArInactive = false;
			if (!$problem['pass'])
				$allPassActive = false;
			if ($problem['pass'])
				$allPassInactive = false;
		}

		$setRating = 0;
		if (count($problems) > 0)
			$setRating = round(array_sum(array_column($problems, 'rating')) / count($problems));

		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Edit Set: ' . $set['Set']['title']);
		$this->set('set', $set);
		$this->set('problems', $problems);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('canEditSettings', $canEditSettings);
		$this->set('canDelete', $this->Authorization->can($set, 'delete'));
		$this->set('isSandbox', $isSandbox);
		$this->set('allArActive', $allArActive);
		$this->set('allArInactive', $allArInactive);
		$this->set('allPassActive', $allPassActive);
		$this->set('allPassInactive', $allPassInactive);
		$this->set('setRating', $setRating);
	}

	#[HttpPost]
	public function delete($id = null)
	{
		$setID = $id ?? ($this->data['Set']['id'] ?? null);
		if (!$setID)
			throw new BadRequestException();

		$s = $this->Set->findById((int) $setID);
		if (!$s)
			throw new NotFoundException('Set not found');

		$this->Authorization->authorize($s);

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
			'order' => array_map('trim', explode(',', SetConnection::displayOrderForSetSql('Set'))),
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

	#[HttpPost]
	public function addTsumego($setID)
	{
		if ($setID === 'favorites')
		{
			$set = $this->_getOrCreateDefaultFavoritesSet();
			$setID = $set['Set']['id'];
		}
		else
		{
			$set = ClassRegistry::init('Set')->findById($setID);
			if (!$set)
				throw new NotFoundException('Set not found');
		}

		$this->Authorization->authorize($set);

		$tsumegoId = (int) ($this->data['tsumego_id'] ?? 0);
		if (!$tsumegoId)
			throw new BadRequestException();
		if (!ClassRegistry::init('Tsumego')->findById($tsumegoId))
			throw new NotFoundException('Tsumego not found');

		$existing = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setID, 'tsumego_id' => $tsumegoId],
		]);
		if ($existing)
			throw new ConflictException('Already in set');

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

		$achievementChecker = new AchievementChecker();
		$achievementChecker->checkFavoritesAchievement((int) $setID);
		$achievementChecker->finalize();

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			$this->autoRender = false;
			$this->response->type('application/json');
			// Include the resolved set so the client can keep its set list in
			// sync when the Favorites set is created on the fly (heart toggle).
			$this->response->body(json_encode([
				'contains' => true,
				'set_id' => $setID,
				'title' => $set['Set']['title'],
			]));
			return;
		}

		CookieFlash::set('Added to set', 'success');
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	/**
	 * Create a new tsumego and add it to a set. Admin only.
	 */
	#[HttpPost]
	public function createAndAddTsumego($setID)
	{
		$this->Authorization->authorize('Set');

		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
			throw new NotFoundException('Set not found');

		if (!isset($this->data['order']))
			throw new BadRequestException();

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
		return $this->redirect('/sets/edit/' . (int) $setID);
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
		Auth::saveUserField('default_set_id', $setModel->id);

		return $setModel->findById($setModel->id);
	}

	/**
	 * Remove a tsumego from a set.
	 */
	#[HttpPost]
	public function removeTsumego($setID)
	{
		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
			throw new NotFoundException('Set not found');

		$this->Authorization->authorize($set);

		$tsumegoId = $this->data['tsumego_id'] ?? null;
		if (!$tsumegoId)
			throw new BadRequestException();

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
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	/**
	 * Swap the order of two adjacent set_connections.
	 */
	#[HttpPost]
	public function reorderTsumego($setID)
	{
		$set = ClassRegistry::init('Set')->findById($setID);
		if (!$set)
			throw new NotFoundException('Set not found');
		$this->Authorization->authorize($set);

		$tsumegoId = $_GET['tsumego_id'] ?? $this->data['tsumego_id'] ?? null;
		$dir = $_GET['dir'] ?? $this->data['dir'] ?? null;

		if (!$tsumegoId || !in_array($dir, ['up', 'down']))
			throw new BadRequestException();

		$scModel = ClassRegistry::init('SetConnection');
		$current = $scModel->find('first', [
			'conditions' => ['set_id' => $setID, 'tsumego_id' => (int) $tsumegoId],
		]);
		if (!$current)
			throw new NotFoundException('Tsumego not in set');

		$currentNum = $current['SetConnection']['num'];
		$adjacentNum = $dir === 'up' ? $currentNum - 1 : $currentNum + 1;

		$adjacent = $scModel->find('first', [
			'conditions' => ['set_id' => $setID, 'num' => $adjacentNum],
		]);
		if (!$adjacent)
			throw new ConflictException();

		// Swap num values
		$scModel->id = $current['SetConnection']['id'];
		$scModel->saveField('num', $adjacentNum);
		$scModel->id = $adjacent['SetConnection']['id'];
		$scModel->saveField('num', $currentNum);

		CookieFlash::set('Reordered', 'success');
		return $this->redirect('/sets/edit/' . (int) $setID);
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
		$pdCounter = 0;
		$acS = null;
		$acA = null;
		$this->set('canEdit', false);

		$queryType = self::decodeQueryType($id);

		if ($queryType == 'topics' && is_numeric($id))
		{
			$set = $this->Set->findById($id);
			if (!$set)
				throw new NotFoundException("Set not found");
			$this->Authorization->authorize($set, "view");
		}

		if ($queryType == 'tags')
		{
			$tag = $this->Tag->findByName($id);
			if (!$tag)
				throw new NotFoundException("Tag not found");
		}

		$tsumegoFilters = new TsumegoFilters($queryType);
		if ($this->Authorization->can('Tsumego', 'edit'))
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
			$set['Set']['title'] = $id;
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
			$set['Set']['title'] = $id;
		}
		elseif ($tsumegoFilters->query == 'topics')
		{
			$set = ClassRegistry::init('Set')->findById($id);
			if (!$set)
				throw new NotFoundException("Set not found");
			$this->Authorization->authorize($set, "view");

			foreach ($tsumegoButtons as $tsumegoButton)
				$tsIds [] = $tsumegoButton->tsumegoID;
			if ($set['Set']['public'] == 0 && $set['Set']['user_id'] === null)
				$this->set('_page', 'sandbox');
			$this->set('isFav', false);
			$this->set('isOwner', Auth::isLoggedIn() && $set['Set']['user_id'] == Auth::getUserID());
			$this->set('canEdit', $this->Authorization->can($set, 'edit'));
		}
		else
			throw new BadRequestException('Unknown query type: ' . $tsumegoFilters->query);

		if ($tsumegoButtons->description)
			$set['Set']['description'] = $tsumegoButtons->description;

		$displayTitle = $set['Set']['title'] . $tsumegoButtons->getPartitionTitleSuffix();
		$this->set('_title', $displayTitle . ' on Tsumego Hero');
		$this->set('setTitle', $displayTitle);

		$ogDescription = trim(strip_tags($set['Set']['description'] ?? ''));
		if ($ogDescription === '')
		{
			if ($tsumegoFilters->query === 'topics')
				$ogDescription = $this->Set->getProblemCount($set['Set']['id']) . ' go problems';
			else
				$ogDescription = 'Interactive go problems';
		}

		$og = [
			'title' => $displayTitle,
			'description' => $ogDescription,
			'url' => Router::url('/sets/view/' . $set['Set']['id'], true),
			'type' => 'website',
			'site_name' => 'Tsumego',
		];

		$image = $set['Set']['image'] ?? '';
		if ($image !== '')
		{
			$og['image'] = Router::url('/img/' . $image, true);
			$og['image_alt'] = 'Cover image of ' . $displayTitle;
			$imagePath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $image);
			if (file_exists($imagePath))
			{
				$size = getimagesize($imagePath);
				if ($size)
				{
					$og['image_width'] = $size[0];
					$og['image_height'] = $size[1];
					$og['image_type'] = $size['mime'];
				}
			}
		}

		$this->set('og', $og);

		if (Auth::isLoggedIn() && $tsumegoFilters->query == 'topics')
		{
			$ur = $this->TsumegoAttempt->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]) ?: [];
			$urCount2 = count($ur);
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				$urSum = '';
				$tsumegoButton->seconds = 0;
				$solvedSeconds = []; // Track all successful solve times to find minimum (best)
				for ($j = 0; $j < $urCount2; $j++)
					if ($tsumegoButton->tsumegoID === (int) $ur[$j]['TsumegoAttempt']['tsumego_id'])
					{
						$isSolved = (bool) $ur[$j]['TsumegoAttempt']['solved'];
						$attemptSeconds = (int) $ur[$j]['TsumegoAttempt']['seconds'];
						if ($isSolved && $attemptSeconds > 0)
							$solvedSeconds[] = $attemptSeconds;

						$mis = (int) $ur[$j]['TsumegoAttempt']['misplays'];
						if (!$isSolved && $mis <= 0)
							$mis = 1;
						while ($mis > 0)
						{
							$urSum .= 'F';
							$mis--;
						}
						if ($isSolved)
							$urSum .= '1';
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

		if ($tsumegoFilters->query == 'topics')
		{
			$this->set('pdCounter', $pdCounter);
			$this->set('acS', $acS);
			$this->set('acA', $acA);
		}

		$this->set('tsumegoFilters', $tsumegoFilters);
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
	private function updateAchievementConditions($sid, $avgTime, $accuracy)
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

	#[HttpPost]
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
			'options' => ['min_range' => Constants::$MIN_COLLECTION_SIZE, 'max_range' => Constants::$MAX_COLLECTION_SIZE],
		]);
		if ($collectionSizeInt === false || $collectionSizeInt % Constants::$COLLECTION_SIZE_STEP !== 0)
		{
			CookieFlash::set('Collection size must be a multiple of ' . Constants::$COLLECTION_SIZE_STEP . ' between ' . Constants::$MIN_COLLECTION_SIZE . ' and ' . Constants::$MAX_COLLECTION_SIZE, 'error');
			return $this->redirect('/sets');
		}
		Preferences::set('collection_size', $collectionSizeInt);
		return $this->redirect('/sets');
	}

	/**
	 * True when an admin edits a set they do not own (elevated privilege).
	 */
	private function _isElevatedSetEdit(array $set): bool
	{
		return Auth::isAdmin() && $set['Set']['user_id'] != Auth::getUserID();
	}
}
