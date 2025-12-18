<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoUtil', 'Utility');
App::uses('AppException', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('SetsSelector', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('Progress', 'Utility');

class SetsController extends AppController
{
	public $helpers = ['Html', 'Form'];

	public $title = 'tsumego-hero.com';

	/**
	 * @return void
	 */
	public function sandbox()
	{
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Favorite');
		$this->loadModel('SetConnection');

		$this->set('_page', 'sandbox');
		$this->set('_title', 'Tsumego Hero - Collections');
		$setsNew = [];

		if (isset($this->params['url']['restore']))
		{
			$restore = $this->Set->findById($this->params['url']['restore']);
			if ($restore['Set']['public'] == -1)
			{
				$restore['Set']['public'] = 0;
				$this->Set->save($restore);
			}
		}

		$sets = $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 0],
		]) ?: [];
		$u = $this->User->findById(Auth::getUserID());

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
				$percent = $counter / count($ts) * 100;
			$overallCounter += count($ts);
			$sets[$i]['Set']['solvedNum'] = $counter;
			$sets[$i]['Set']['solved'] = round($percent, 1);
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
			$sn['solved'] = round($percent, 1);
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

	/**
	 * @param int|null $tid Tsumego ID
	 * @return void
	 */
	public function create($tid = null)
	{
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$redirect = false;
		$t = [];
		if (isset($this->data['Set']))
		{
			$s = $this->Set->find('all', ['order' => 'id DESC']);
			if (!$s)
				$s = [];
			$ss = [];
			$sCount = count($s);
			for ($i = 0; $i < $sCount; $i++)
				if ($s[$i]['Set']['id'] < 6472)
					array_push($ss, $s[$i]);

			$seed = str_split('abcdefghijklmnopqrstuvwxyz0123456789');
			shuffle($seed);
			$rand = '';
			foreach (array_rand($seed, 6) as $k)
				$rand .= $seed[$k];
			$hashName = '6473k339312/_' . $rand . '_' . $this->data['Set']['title'];
			$hashName2 = '_' . $rand . '_' . $this->data['Set']['title'];

			$set = [];
			$set['Set']['id'] = $ss[0]['Set']['id'] + 1;
			$set['Set']['title'] = $this->data['Set']['title'];
			$set['Set']['public'] = 0;
			$set['Set']['image'] = 'b1.png';
			$set['Set']['difficulty'] = 4;
			$set['Set']['author'] = 'various creators';
			$set['Set']['order'] = Constants::$DEFAULT_SET_ORDER;

			$this->Set->create();
			$this->Set->save($set);

			$tMax = $this->Tsumego->find('first', ['order' => 'id DESC']);

			$t = [];
			$t['Tsumego']['id'] = $tMax['Tsumego']['id'] + 1;
			$t['Tsumego']['difficulty'] = 4;
			$t['Tsumego']['variance'] = 100;
			$t['Tsumego']['description'] = 'b to kill';
			$t['Tsumego']['author'] = Auth::getUser()['name'];
			$this->Tsumego->create();
			$this->Tsumego->save($t);

			$sc = [];
			$sc['SetConnection']['set_id'] = $ss[0]['Set']['id'] + 1;
			$sc['SetConnection']['tsumego_id'] = $tMax['Tsumego']['id'] + 1;
			$sc['SetConnection']['num'] = 1;
			$this->SetConnection->create();
			$this->SetConnection->save($sc);

			mkdir($hashName, 0777);
			copy('6473k339312/__new/1.sgf', $hashName . '/1.sgf');

			$redirect = true;
		}
		$this->set('t', $t);
		$this->set('redirect', $redirect);
	}

	/**
	 * @param int $id Set ID
	 * @return void
	 */
	public function remove($id)
	{
		$this->loadModel('Tsumego');
		$redirect = false;

		if (isset($this->data['Set']))
			if (strpos(';' . $this->data['Set']['hash'], '6473k339312-') == 1)
			{
				$setID = (int) str_replace('6473k339312-', '', $this->data['Set']['hash']);

				$s = $this->Set->findById($setID);
				if ($s['Set']['public'] == 0 || $s['Set']['public'] == -1)
					$this->Set->delete($setID);
				$ts = TsumegoUtil::collectTsumegosFromSet($setID);
				if (count($ts) < 50)
					foreach ($ts as $item)
						$this->Tsumego->delete($item['Tsumego']['id']);
				$redirect = true;
			}
		//$this->set('t', $t);
		$this->set('redirect', $redirect);
	}

	/**
	 * @param int $tid Tsumego ID
	 * @return void
	 */
	public function add($tid)
	{
		$this->loadModel('Tsumego');

		if (isset($this->data['Tsumego']))
		{
			$t = [];
			$t['Tsumego']['difficulty'] = $this->data['Tsumego']['difficulty'];
			$t['Tsumego']['variance'] = $this->data['Tsumego']['variance'];
			$t['Tsumego']['description'] = $this->data['Tsumego']['description'];
			$this->Tsumego->save($t);
		}
		$ts = TsumegoUtil::collectTsumegosFromSet($tid);
		$this->set('t', $ts[0]);
	}

	public function index(): void
	{
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('Favorite');
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
		if ($tsumegoFilters->query == 'favorites')
			$tsumegoFilters->setQuery('topics');

		//setTiles
		$setsRaw = $this->Set->find('all', [
			'order' => ['Set.order', 'Set.id'],
			'conditions' => ['public' => 1],
		]) ?: [];
		foreach ($setsRaw as $set)
			if (Auth::hasPremium() || !$set['Set']['premium'])
				$setTiles [] = $set['Set']['title'];

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
		$this->set('hasPremium', Auth::hasPremium());
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
			$return['solved'] = round($statusCounter / count($currentTagIds) * 100, 2);
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

	/**
	 * @param int|null $id Set ID
	 * @return void
	 */
	public function ui($id = null)
	{
		$s = $this->Set->findById($id);
		$redirect = false;

		if (isset($_FILES['adminUpload']))
		{
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$randstring = 'set_';
			for ($i = 0; $i < 6; $i++)
				$randstring .= $characters[rand(0, strlen($characters))];
			$filename = $randstring . '_' . $_FILES['adminUpload']['name'];

			$errors = [];
			$file_name = $_FILES['adminUpload']['name'];
			$file_size = $_FILES['adminUpload']['size'];
			$file_tmp = $_FILES['adminUpload']['tmp_name'];
			$file_type = $_FILES['adminUpload']['type'];
			$array = explode('.', $_FILES['adminUpload']['name']);
			$file_ext = strtolower(end($array));
			$extensions = ['png', 'jpg'];

			if (in_array($file_ext, $extensions) === false)
				$errors[] = 'png/jpg allowed.';
			if ($file_size > 2097152)
				$errors[] = 'The file is too large.';

			if (empty($errors) == true)
			{
				$uploadfile = $_SERVER['DOCUMENT_ROOT'] . '/app/webroot/img/' . $filename;
				move_uploaded_file($file_tmp, $uploadfile);
			}

			$s['Set']['image'] = $filename;
			$this->Set->save($s);

			$redirect = true;
		}

		$this->set('id', $id);
		$this->set('s', $s);
		$this->set('redirect', $redirect);
	}

	private function decodeQueryType($input)
	{
		if (is_numeric($input))
			return 'topics';
		if ($input == 'favorites')
			return 'favorites';
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
		if (!isset($this->data['Tsumego']))
			return;

		$tsumegoModel = ClassRegistry::init('Tsumego');

		$tsumegoModel->getDataSource()->begin();

		try
		{
			$tsumego = [];
			$tsumego['num'] = $this->data['Tsumego']['num'];
			$tsumego['difficulty'] = 40;
			$tsumego['variance'] = $this->data['Tsumego']['variance'];
			$tsumego['description'] = $this->data['Tsumego']['description'];
			$tsumego['hint'] = $this->data['Tsumego']['hint'];
			$tsumego['author'] = $this->data['Tsumego']['author'];
			$tsumegoModel->create();
			$tsumegoModel->save($tsumego);

			$tsumego['id'] = $tsumegoModel->id;
			$setConnection = [];
			$setConnection['set_id'] = $setID;
			$setConnection['tsumego_id'] = $tsumego['id'];
			$setConnection['num'] = $this->data['Tsumego']['num'];
			ClassRegistry::init('SetConnection')->create();
			ClassRegistry::init('SetConnection')->save($setConnection);

			// Save SGF if provided (either from textarea or file upload)
			$fileUpload = isset($_FILES['adminUpload']) && $_FILES['adminUpload']['error'] === UPLOAD_ERR_OK ? $_FILES['adminUpload'] : null;
			$sgfDataOrFile = $this->data['Tsumego']['sgf'] ?? $fileUpload;

			if ($sgfDataOrFile)
				ClassRegistry::init('Sgf')->uploadSgf($sgfDataOrFile, $tsumego['id'], Auth::getUserID(), Auth::isAdmin());
			$tsumegoModel->getDataSource()->commit();
		}
		catch (Exception $e)
		{
			$tsumegoModel->getDataSource()->rollback();
			throw $e;
		}
		return $this->redirect('/sets/view/' . $setID);
	}

	public function view(string|int|null $id = null, int $partition = 1): void
	{
		// transferring from 1 indexed for humans to 0 indexed for us programmers.
		$partition = $partition - 1;
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Favorite');
		$this->loadModel('AdminActivity');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('ProgressDeletion');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('Tag');
		$this->loadModel('User');
		$this->loadModel('UserContribution');

		if (is_null($id))
			throw new AppException("Set to view not specified");

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

		$tsumegoFilters = new TsumegoFilters(self::decodeQueryType($id));
		if (Auth::isLoggedIn())
		{
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
		}
		if (isset($this->params['url']['add']))
		{
			$overallCount = $this->Tsumego->find('first', ['order' => 'id DESC']);
			$scTcount = $this->SetConnection->find('first', ['conditions' => ['set_id' => $id, 'num' => 1]]);
			$setCount = $this->Tsumego->findById($scTcount['SetConnection']['tsumego_id']);
			$setCount['Tsumego']['id'] = $overallCount['Tsumego']['id'] + 1;
			$setCount['Tsumego']['set_id'] = $scTcount['SetConnection']['set_id'];
			$setCount['Tsumego']['num'] += 1;
			$setCount['Tsumego']['variance'] = 100;
			if (Auth::getUserID() == 72)
				$setCount['Tsumego']['author'] = 'Joschka Zimdars';
			elseif (Auth::getUserID() == 1206)
				$setCount['Tsumego']['author'] = 'Innokentiy Zabirov';
			elseif (Auth::getUserID() == 3745)
				$setCount['Tsumego']['author'] = 'Dennis Olevanov';
			else
				$setCount['Tsumego']['author'] = Auth::getUser()['name'];
			$this->Tsumego->create();
			$this->Tsumego->save($setCount);
			$set = $this->Set->findById($id);
			AdminActivityLogger::log(AdminActivityType::PROBLEM_ADD, $this->Tsumego->id, $id, null, $set['Set']['title']);
		}

		Util::setCookie('lastSet', $id);
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $id);
		$this->set('startingSetConnectionID', $this->getFirstUnsolvedSetConnectionId($tsumegoButtons));

		if ($tsumegoFilters->query == 'difficulty')
		{
			$set = [];
			$set['Set']['id'] = $id;
			$set['Set']['title'] = $id . $tsumegoButtons->getPartitionTitleSuffix();
			$set['Set']['image'] = $id . 'Rank.png';
			$set['Set']['multiplier'] = 1;
			$set['Set']['public'] = 1;
			$elo = Rating::getRankMinimalRatingFromReadableRank($id);
			$set['Set']['difficulty'] = $elo;
		}
		elseif ($tsumegoFilters->query == 'tags')
		{
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
			if ($set['Set']['public'] == 0)
				$this->set('_page', 'sandbox');
			$this->set('isFav', false);
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
				AdminActivityLogger::log(AdminActivityType::SET_TITLE_EDIT, null, $id, $oldTitle, $this->data['Set']['title']);
			}
			if (isset($this->data['Set']['description']))
			{
				$this->Set->create();
				$changeSet = $set;
				$changeSet['Set']['description'] = $this->data['Set']['description'];
				$this->set('data', $changeSet['Set']['description']);
				$this->Set->save($changeSet, true);
				$oldDescription = $set['Set']['description'];
				$set = $this->Set->findById($id);
				AdminActivityLogger::log(AdminActivityType::SET_DESCRIPTION_EDIT, null, $id, $oldDescription, $this->data['Set']['description']);
			}
			if (isset($this->data['Set']['setDifficulty']))
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
				AdminActivityLogger::log(AdminActivityType::SET_ORDER_EDIT, null, $id, $oldOrder, $this->data['Set']['order']);
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
		elseif ($tsumegoFilters->query = 'favorites')
		{
			$allUts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
			$idMap = [];
			$statusMap = [];
			$allUtsCount = count($allUts);
			for ($i = 0; $i < $allUtsCount; $i++)
			{
				array_push($idMap, $allUts[$i]['TsumegoStatus']['tsumego_id']);
				array_push($statusMap, $allUts[$i]['TsumegoStatus']['status']);
			}
			$fav = $this->Favorite->find('all', ['order' => 'created', 'direction' => 'DESC', 'conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
			if (!empty($fav))
				$this->set('achievementUpdate', new AchievementChecker()->checkSetAchievements(-1)->finalize()->updated);
			$ts = [];
			$difficultyCount = 0;
			$solvedCount = 0;
			$sizeCount = 0;
			$favCount = count($fav);
			for ($i = 0; $i < $favCount; $i++)
			{
				$tx = $this->Tsumego->find('first', ['conditions' => ['id' => $fav[$i]['Favorite']['tsumego_id']]]);
				$difficultyCount += $tx['Tsumego']['difficulty'];
				$utx = $this->findUt($fav[$i]['Favorite']['tsumego_id'], $allUts, $idMap);
				if ($utx['TsumegoStatus']['status'] == 'S' || $utx['TsumegoStatus']['status'] == 'W' || $utx['TsumegoStatus']['status'] == 'C')
					$solvedCount++;
				$sizeCount++;
				array_push($ts, $tx);
			}
			$allUtsCount = count($allUts);
			for ($i = 0; $i < $allUtsCount; $i++)
			{
				$tsCount2 = count($ts);
				for ($j = 0; $j < $tsCount2; $j++)
					if ($allUts[$i]['TsumegoStatus']['tsumego_id'] == $ts[$j]['Tsumego']['id'])
						$ts[$j]['Tsumego']['status'] = $allUts[$i]['TsumegoStatus']['status'];
			}
			$percent = Util::getPercent($solvedCount, $sizeCount);
			$set = [];
			$set['Set']['id'] = 1;
			$set['Set']['title'] = 'Favorites';
			$set['Set']['title2'] = null;
			$set['Set']['author'] = Auth::getUser()['name'];
			$set['Set']['description'] = '';
			$set['Set']['image'] = 'fav';
			$set['Set']['order'] = 0;
			$set['Set']['public'] = 1;
			$set['Set']['created'] = 20180322;
			$set['Set']['createdDisplay'] = '22. March 2018';
			$set['Set']['solvedNum'] = $sizeCount;
			$set['Set']['solved'] = round($percent, 1);
			$set['Set']['solvedColor'] = '#eee';
			$set['Set']['topicColor'] = '#eee';
			$set['Set']['difficultyColor'] = '#eee';
			$set['Set']['sizeColor'] = '#eee';
			$set['Set']['dateColor'] = '#eee';
			$this->set('isFav', true);
		}

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

	private function findUt($id = null, $allUts = null, $map = null)
	{
		$currentUt = array_search($id, $map);
		$ut = $allUts[$currentUt];
		if ($currentUt == 0)
			if ($id != $map[0])
				$ut = null;

		return $ut;
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
		if (!Auth::isLoggedIn())
			return $this->redirect('/sets/view/' . $setID);

		if ($this->data['reset-check'] != 'reset')
		{
			CookieFlash::set('Reset check wasn\'t correctly typed', 'error');
			return $this->redirect('/sets/view/' . $setID);
		}

		$partition = $partition - 1;

		$tsumegoFilters = new TsumegoFilters();
		if ($tsumegoFilters->collectionSize != 200)
		{
			CookieFlash::set('Reset is only possible for collection size 200', 'error');
			return $this->redirect('/sets/view/' . $setID);
		}
		$tsumegoFilters->query = 'topics';
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $setID);
		$tsumegoIDToClear = [];
		foreach ($tsumegoButtons as $tsumegoButton)
			$tsumegoIDToClear[]= $tsumegoButton->tsumegoID;

		$problemsInSet = Util::query("
SELECT
	COUNT(DISTINCT tsumego.id) AS total
FROM tsumego
	JOIN set_connection ON set_connection.tsumego_id = tsumego.id AND set_connection.set_id = ?", [$setID])[0]["total"];
		if (TsumegoStatus::getProblemsSolvedInSet($setID) < $problemsInSet * 0.5)
			return $this->redirect('sets/' . $setID);

		Util::query("
DELETE tsumego_status FROM tsumego_status
WHERE tsumego_status.user_id = ? AND tsumego_status.tsumego_id IN(" .implode(',', $tsumegoIDToClear) . ")", [Auth::getUserID()]);
		$progresDeletion = [];
		$progresDeletion['user_id'] = Auth::getUserID();
		$progresDeletion['set_id'] = $setID;
		ClassRegistry::init('ProgressDeletion')->create();
		ClassRegistry::init('ProgressDeletion')->save($progresDeletion);
		return $this->redirect('/sets/view/' . $setID);
	}
}
