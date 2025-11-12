<?php

App::uses('TsumegoUtil', 'Utility');
App::uses('AppException', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('TsumegoButtons', 'Utility');

class SetsController extends AppController {
	public $helpers = ['Html', 'Form'];

	public $title = 'tsumego-hero.com';

	/**
	 * @param int|null $id Set ID
	 * @return void
	 */
	public function duplicates($id = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('Sgf');
		$this->loadModel('Duplicate');
		$this->loadModel('SetConnection');

		$this->Session->write('page', 'sandbox');
		$this->Session->write('title', 'Tsumego Hero - Duplicates');

		$tIds = [];
		$d2 = [];

		if (isset($this->params['url']['unmark'])) {
			$unmark = $this->Duplicate->find('all', ['conditions' => ['dGroup' => $this->params['url']['unmark']]]);
			if (!$unmark) {
				$unmark = [];
			}
			foreach ($unmark as $item) {
				$this->Duplicate->delete($item['Duplicate']['id']);
			}
		}

		$ts = TsumegoUtil::collectTsumegosFromSet($id);
		$set = $this->Set->findById($ts[0]['Tsumego']['set_id']);
		foreach ($ts as $item) {
			$tIds[] = $item['Tsumego']['id'];
		}
		$d0 = $this->Duplicate->find('all', ['conditions' => ['tsumego_id' => $tIds]]);
		if (!$d0) {
			$d0 = [];
		}

		$d = [];
		$d0Count = count($d0);
		for ($i = 0; $i < $d0Count; $i++) {
			$d01 = $this->Duplicate->find('all', [
				'conditions' => [
					'dGroup' => $d0[$i]['Duplicate']['dGroup'],
					'NOT' => ['tsumego_id' => $d0[$i]['Duplicate']['tsumego_id']],
				],
			]);
			if (!$d01) {
				$d01 = [];
			}
			array_push($d, $d0[$i]);
			foreach ($d01 as $item) {
				$d[] = $item;
			}
		}
		$dNew = [];
		$dCount = count($d);
		for ($i = 0; $i < $dCount; $i++) {
			$dNewMatch = false;
			$dNewCount2 = count($dNew);
			for ($j = 0; $j < $dNewCount2; $j++) {
				if ($i != $j) {
					if ($d[$i]['Duplicate']['id'] == $dNew[$j]['Duplicate']['id']) {
						$dNewMatch = true;
					}
				}
			}
			if (!$dNewMatch) {
				array_push($dNew, $d[$i]);
			}
		}
		$d = $dNew;
		$similarArr = [];
		$similarArrInfo = [];
		$similarArrBoardSize = [];

		$counter2 = 0;
		$counter = -1;

		$currentGroup = -1;
		$dCount = count($d);
		for ($i = 0; $i < $dCount; $i++) {
			if ($currentGroup != $d[$i]['Duplicate']['dGroup']) {
				$counter++;
				$d2[$counter] = [];
			}
			$td = $this->Tsumego->findById($d[$i]['Duplicate']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $td['Tsumego']['id']]]);
			if (!$scT) {
				continue;
			}
			$td['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$setx = $this->Set->findById($td['Tsumego']['set_id']);
			$td['Tsumego']['title'] = $setx['Set']['title'] . ' - ' . $td['Tsumego']['num'];
			$td['Tsumego']['dGroup'] = $d[$i]['Duplicate']['dGroup'];

			array_push($d2[$counter], $td);
			$currentGroup = $d[$i]['Duplicate']['dGroup'];

			$sgf = $this->Sgf->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $td['Tsumego']['id']]]);
			if (!$sgf) {
				continue;
			}
			$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);

			array_push($similarArr, $sgfArr[0]);
			array_push($similarArrInfo, $sgfArr[2]);
			array_push($similarArrBoardSize, $sgfArr[3]);
		}

		$this->set('id', $id);
		$this->set('set', $set);
		$this->set('ts', $ts);
		$this->set('d', $d);
		$this->set('d2', $d2);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
	}

	/**
	 * @return void
	 */
	public function duplicatesearch() {
		$this->loadModel('Tsumego');
		$this->loadModel('Duplicate');
		$this->Session->write('page', 'sandbox');
		$this->Session->write('title', 'Duplicate Search Results');
		$s = $this->Set->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				'OR' => [
					['public' => 1],
					['public' => 0],
				],
			],
		]);
		if (!$s) {
			$s = [];
		}
		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++) {
			//$ts = $this->Tsumego->find('all', array('conditions' => array('set_id' => $s[$i]['Set']['id'])));
			$ts = TsumegoUtil::collectTsumegosFromSet($s[$i]['Set']['id']);
			$tsIds = [];
			foreach ($ts as $item) {
				$tsIds[] = $item['Tsumego']['id'];
			}
			$d = $this->Duplicate->find('all', ['conditions' => ['tsumego_id' => $tsIds]]);
			if (!$d) {
				$d = [];
			}
			$s[$i]['Set']['dNum'] = count($d);
		}

		$ds1 = file_get_contents('ds1.txt');
		$all = $this->Tsumego->find('all', ['order' => 'id ASC']);

		//$this->set('progress', $progress);
		$this->set('s', $s);
	}

	/**
	 * @return void
	 */
	public function beta() {
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('Favorite');
		$this->loadModel('Comment');
		$this->loadModel('SetConnection');

		$this->Session->write('page', 'sandbox');
		$this->Session->write('title', 'Tsumego Hero - Collections');
		$setsNew = [];

		if (isset($this->params['url']['restore'])) {
			$restore = $this->Set->findById($this->params['url']['restore']);
			if ($restore['Set']['public'] == -1) {
				$restore['Set']['public'] = 0;
				$this->Set->save($restore);
			}
		}

		$sets = $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 0],
		]);
		if (!$sets) {
			$sets = [];
		}
		$u = $this->User->findById(Auth::getUserID());

		if (Auth::isLoggedIn()) {
			$uts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$uts) {
				$uts = [];
			}
			$tsumegoStatusMap = [];
			$utsCount4 = count($uts);
			for ($l = 0; $l < $utsCount4; $l++) {
				$tsumegoStatusMap[$uts[$l]['TsumegoStatus']['tsumego_id']] = $uts[$l]['TsumegoStatus']['status'];
			}
		}
		$overallCounter = 0;

		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$i]['Set']['id']);
			$sets[$i]['Set']['anz'] = count($ts);
			$counter = 0;
			$elo = 0;
			$tsCount3 = count($ts);
			for ($k = 0; $k < $tsCount3; $k++) {

				$elo += $ts[$k]['Tsumego']['rating'];
				if (Auth::isLoggedIn()) {
					if (isset($tsumegoStatusMap[$ts[$k]['Tsumego']['id']])) {
						if ($tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'C') {
							$counter++;
						}
					}
				}
			}
			if (count($ts) > 0) {
				$elo = $elo / count($ts);
			} else {
				$elo = 0;
			}
			$date = new DateTime($sets[$i]['Set']['created']);
			$month = date('F', strtotime($sets[$i]['Set']['created']));
			$setday = $date->format('d. ');
			$setyear = $date->format('Y');
			if ($setday[0] == 0) {
				$setday = substr($setday, -3);
			}
			$sets[$i]['Set']['created'] = $date->format('Ymd');
			$sets[$i]['Set']['createdDisplay'] = $setday . $month . ' ' . $setyear;
			$percent = 0;
			if (count($ts) > 0) {
				$percent = $counter / count($ts) * 100;
			}
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

		$accessList = $this->User->find('all', ['conditions' => ['completed' => 1]]);
		if (!$accessList) {
			$accessList = [];
		}
		$access = [];
		foreach ($accessList as $item) {
			$access[] = $item['User']['name'];
		}

		$adminsList = $this->User->find('all', ['order' => 'id ASC', 'conditions' => ['isAdmin >' => 0]]);
		if (!$adminsList) {
			$adminsList = [];
		}
		$admins = [];
		foreach ($adminsList as $item) {
			$admins[] = $item['User']['name'];
		}

		$this->set('admins', $admins);
		$this->set('access', $access);
		$this->set('sets', $sets);
		$this->set('setsNew', $setsNew);
		$this->set('overallCounter', $overallCounter);
	}

	/**
	 * @param int|null $tid Tsumego ID
	 * @return void
	 */
	public function create($tid = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$redirect = false;
		$t = [];
		if (isset($this->data['Set'])) {
			$s = $this->Set->find('all', ['order' => 'id DESC']);
			if (!$s) {
				$s = [];
			}
			$ss = [];
			$sCount = count($s);
			for ($i = 0; $i < $sCount; $i++) {
				if ($s[$i]['Set']['id'] < 6472) {
					array_push($ss, $s[$i]);
				}
			}

			$seed = str_split('abcdefghijklmnopqrstuvwxyz0123456789');
			shuffle($seed);
			$rand = '';
			foreach (array_rand($seed, 6) as $k) {
				$rand .= $seed[$k];
			}
			$hashName = '6473k339312/_' . $rand . '_' . $this->data['Set']['title'];
			$hashName2 = '_' . $rand . '_' . $this->data['Set']['title'];

			$set = [];
			$set['Set']['id'] = $ss[0]['Set']['id'] + 1;
			$set['Set']['title'] = $this->data['Set']['title'];
			$set['Set']['public'] = 0;
			$set['Set']['image'] = 'b1.png';
			$set['Set']['difficulty'] = 4;
			$set['Set']['author'] = 'various creators';
			$set['Set']['order'] = 999;

			$this->Set->create();
			$this->Set->save($set);

			$tMax = $this->Tsumego->find('first', ['order' => 'id DESC']);

			$t = [];
			$t['Tsumego']['id'] = $tMax['Tsumego']['id'] + 1;
			$t['Tsumego']['set_id'] = 206;
			$t['Tsumego']['num'] = 1;
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
	public function remove($id) {
		$this->loadModel('Tsumego');
		$redirect = false;

		if (isset($this->data['Set'])) {
			if (strpos(';' . $this->data['Set']['hash'], '6473k339312-') == 1) {
				$setID = (int) str_replace('6473k339312-', '', $this->data['Set']['hash']);

				$s = $this->Set->findById($setID);
				if ($s['Set']['public'] == 0 || $s['Set']['public'] == -1) {
					$this->Set->delete($setID);
				}
				$ts = TsumegoUtil::collectTsumegosFromSet($setID);
				if (count($ts) < 50) {
					foreach ($ts as $item) {
						$this->Tsumego->delete($item['Tsumego']['id']);
					}
				}
				$redirect = true;
			}
		}
		//$this->set('t', $t);
		$this->set('redirect', $redirect);
	}

	/**
	 * @param int $tid Tsumego ID
	 * @return void
	 */
	public function add($tid) {
		$this->loadModel('Tsumego');

		if (isset($this->data['Tsumego'])) {
			$t = [];
			$t['Tsumego']['num'] = $this->data['Tsumego']['num'];
			$t['Tsumego']['difficulty'] = $this->data['Tsumego']['difficulty'];
			$t['Tsumego']['variance'] = $this->data['Tsumego']['variance'];
			$t['Tsumego']['description'] = $this->data['Tsumego']['description'];
			$this->Tsumego->save($t);
		}
		$ts = TsumegoUtil::collectTsumegosFromSet($tid);
		$this->set('t', $ts[0]);
	}

	public function index(): void {
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('Favorite');
		$this->loadModel('AchievementCondition');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('SetConnection');
		$this->loadModel('UserContribution');
		$this->Session->write('page', 'set');
		$this->Session->write('title', 'Tsumego Hero - Collections');

		$setTiles = [];
		$difficultyTiles = [];
		$tagTiles = [];
		$tsumegoStatusMap = [];
		$sets = [];
		$ranksArray = [];
		$tagList = [];
		$setsWithPremium = [];
		$overallCounter = 0;
		$searchCounter = 0;
		$achievementUpdate = [];
		$tsumegoFilters = new TsumegoFilters();

		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]) ?: [];
		foreach ($swp as $item) {
			$setsWithPremium[] = $item['Set']['id'];
		}

		//setTiles
		$setsRaw = $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 1],
		]) ?: [];
		foreach ($setsRaw as $set) {
			if (Auth::hasPremium() || !$set['Set']['premium']) {
				$setTiles [] = $set['Set']['title'];
			}
		}

		//difficultyTiles
		$dt = $this->getExistingRanksArray();
		foreach ($dt as $item) {
			$difficultyTiles[] = $item['rank'];
		}

		//tagTiles
		$tags = $this->TagName->find('all', [
			'conditions' => [
				'approved' => 1,
				'NOT' => ['name' => 'Tsumego'],
			],
		]);

		$tagTiles = [];
		foreach ($tags as $tag) {
			$tagTiles[] = $tag['TagName']['name'];
		}

		if (Auth::isLoggedIn()) {
			$tsumegoStatusMap = TsumegoUtil::getMapForCurrentUser();
		} else {
			$noLoginUts = [];
			$noLoginCount = count($this->Session->read('noLogin') ?? []);
			for ($i = 0; $i < $noLoginCount; $i++) {
				$noLoginUts[$this->Session->read('noLogin')[$i]] = $this->Session->read('noLoginStatus')[$i];
			}
			$tsumegoStatusMap = $noLoginUts;
		}

		//sets
		if ($tsumegoFilters->query == 'topics') {
			$rankConditions = [];
			if (!empty($tsumegoFilters->ranks)) {
				$fromTo = [];
				foreach ($tsumegoFilters->ranks as $rank) {
					$fromTo [] = RatingBounds::coverRank($rank, '15k')->getConditions();
				}
				$rankConditions['OR'] = $fromTo;
			}
			$setsRaw = $this->Set->find('all', ['order' => 'order ASC',
				'conditions' => [
					empty($tsumegoFilters->setIDs) ? null : ['id' => $tsumegoFilters->setIDs],
					'public' => true]]) ?: [];

			$achievementUpdate = [];
			$setsRawCount = count($setsRaw);
			for ($i = 0; $i < $setsRawCount; $i++) {
				$ts = TsumegoUtil::collectTsumegosFromSet($setsRaw[$i]['Set']['id'], $rankConditions);
				$currentIds = [];
				$tsCount2 = count($ts);
				for ($j = 0; $j < $tsCount2; $j++) {
					array_push($currentIds, $ts[$j]['Tsumego']['id']);
				}
				$setAmount = count($ts);
				if (count($tsumegoFilters->tags) > 0) {
					$idsTemp = [];
					$tsTagsFiltered = $this->Tag->find('all', [
						'conditions' => [
							'tsumego_id' => $currentIds,
							'tag_name_id' => $tsumegoFilters->tagIDs,
						],
					]);
					if (!$tsTagsFiltered) {
						$tsTagsFiltered = [];
					}
					$tsTagsFilteredCount2 = count($tsTagsFiltered);
					for ($j = 0; $j < $tsTagsFilteredCount2; $j++) {
						array_push($idsTemp, $tsTagsFiltered[$j]['Tag']['tsumego_id']);
					}
					$currentIds = array_unique($idsTemp);
					$setAmount = count($currentIds);
				}
				if (!in_array($setsRaw[$i]['Set']['id'], $setsWithPremium) || Auth::hasPremium()) {
					$searchCounter += $setAmount;
				}

				$s = [];
				$s['id'] = $setsRaw[$i]['Set']['id'];
				$s['name'] = $setsRaw[$i]['Set']['title'];
				$s['amount'] = $setAmount;
				$s['color'] = $setsRaw[$i]['Set']['color'];
				$s['premium'] = $setsRaw[$i]['Set']['premium'];
				$s['currentIds'] = $currentIds;
				if (count($currentIds) > 0) {
					array_push($sets, $s);
				}
			}

			$sets = $this->partitionCollections($sets, $tsumegoFilters->collectionSize, $tsumegoStatusMap);
			if ($tsumegoFilters->collectionSize >= 200) {
				$setsCount = count($sets);
				for ($i = 0; $i < $setsCount; $i++) {
					if ($sets[$i]['solved'] >= 100) {
						$overallCounter++;
					}
				}
			}
		} else {
			$setsRawCount = count($setsRaw);
			for ($i = 0; $i < $setsRawCount; $i++) {
				$s = [];
				$s['id'] = $setsRaw[$i]['Set']['id'];
				$s['name'] = $setsRaw[$i]['Set']['title'];
				array_push($sets, $s);
			}
		}
		if (Auth::isLoggedIn()) {
			if ($overallCounter >= 10) {
				$aCondition = $this->AchievementCondition->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'set',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
				}
				$aCondition['AchievementCondition']['category'] = 'set';
				$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
				$aCondition['AchievementCondition']['value'] = $overallCounter;
				$this->AchievementCondition->save($aCondition);
			}
			Auth::saveUser();
			$achievementUpdate = $this->checkSetCompletedAchievements();
			if (count($achievementUpdate) > 0) {
				$this->updateXP(Auth::getUserID(), $achievementUpdate);
			}
		}
		//difficulty
		if ($tsumegoFilters->query == 'difficulty') {
			$ranksArray = $this->getExistingRanksArray();
			$newRanksArray = [];
			$setConditions = [];
			if (!empty($tsumegoFilters->setIDs)) {
				$setConditions['set_id'] = $tsumegoFilters->setIDs;
			}
			if (!empty($tsumegoFilters->ranks)) {
				$ranksArray2 = [];
				$ranksCounter = 0;
				foreach ($tsumegoFilters->ranks as $rank) {
					$ranksArrayCount2 = count($ranksArray);
					for ($j = 0; $j < $ranksArrayCount2; $j++) {
						if ($rank == $ranksArray[$j]['rank']) {
							$ranksArray2[$ranksCounter]['rank'] = $ranksArray[$j]['rank'];
							$ranksArray2[$ranksCounter]['color'] = $ranksArray[$j]['color'];
							$ranksCounter++;
						}
					}
				}
				$ranksArray = $ranksArray2;
			}
			foreach ($ranksArray as $rank) {
				$condition = "";
				RatingBounds::coverRank($rank['rank'], '15k')->addSqlConditions($condition);
				if (!Auth::hasPremium()) {
					Util::addSqlCondition($condition, '`set`.premium = false');
				}
				Util::addSqlCondition($condition, 'tsumego.deleted is NULL');
				if (!empty($tsumegoFilters->setIDs)) {
					Util::addSqlCondition($condition, 'set.id IN (' . implode(',', $tsumegoFilters->setIDs) . ')');
				}
				$setConnectionIDs = ClassRegistry::init('Tsumego')->query(
					"SELECT set_connection.id "
					. "FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id"
					. " JOIN `set` ON `set`.id=set_connection.set_id WHERE " . $condition
				);
				$currentIds = [];
				foreach ($setConnectionIDs as $setConnectionID) {
					$currentIds [] = $setConnectionID['set_connection']['id'];
				}
				$setAmount = count($setConnectionIDs);

				if (count($tsumegoFilters->tags) > 0) {
					$idsTemp = [];
					$tsTagsFiltered = $this->Tag->find('all', [
						'conditions' => [
							'tsumego_id' => $currentIds,
							'tag_name_id' => $tsumegoFilters->tagIDs,
						],
					]) ?: [];
					$tsTagsFilteredCount2 = count($tsTagsFiltered);
					for ($j = 0; $j < $tsTagsFilteredCount2; $j++) {
						array_push($idsTemp, $tsTagsFiltered[$j]['Tag']['tsumego_id']);
					}

					$currentIds = array_unique($idsTemp);
					$setAmount = count($currentIds);
				}

				$searchCounter += $setAmount;

				$rTemp = [];
				$rTemp['id'] = $rank['rank'];
				$rTemp['name'] = $rank['rank'];
				$rTemp['amount'] = $setAmount;
				$rTemp['currentIds'] = $currentIds;
				$rTemp['color'] = $rank['color'];
				if (!empty($currentIds)) {
					$newRanksArray [] = $rTemp;
				}
			}
			$ranksArray = $this->partitionCollections($newRanksArray, $tsumegoFilters->collectionSize, $tsumegoStatusMap);
		} else {
			$ranksArray = $this->getExistingRanksArray();
			foreach ($ranksArray as &$rank) {
				$rank['id'] = $rank['rank'];
				$rank['name'] = $rank['rank'];
			}
		}
		//tags
		if ($tsumegoFilters->query == 'tags') {
			$query = "
WITH tag_counts AS (
  SELECT
    tag_name.id AS tag_id,
    tag_name.name AS tag_name,
    tag_name.color AS tag_color,
    COUNT(tsumego.id) AS total_count
  FROM tsumego
  JOIN tag ON tag.tsumego_id = tsumego.id
  JOIN tag_name ON tag_name.id = tag.tag_name_id".(empty($tsumegoFilters->tagIDs) ? '' : ' AND tag_name.id IN (' . implode(',', $tsumegoFilters->tagIDs) . ')')."
  GROUP BY tag_name.id
),
numbered AS (
  SELECT
    tag_name.id AS tag_id,
    tag_name.name AS tag_name,
    tag_name.color AS tag_color,
    tsumego.id AS tsumego_id,
    ROW_NUMBER() OVER (PARTITION BY tag_name.id ORDER BY tsumego.id) AS rn,
    tsumego_status.status
  FROM tsumego
  JOIN tag ON tag.tsumego_id = tsumego.id
  JOIN tag_name ON tag_name.id = tag.tag_name_id
  LEFT JOIN tsumego_status
      ON tsumego_status.user_id = " . Auth::getUserID() . "
      AND tsumego_status.tsumego_id = tsumego.id
),
partitioned AS (
  SELECT
    n.tag_name AS name,
    n.tag_color AS color,
    t.total_count,
    CASE
      WHEN t.total_count <= " . $tsumegoFilters->collectionSize . " THEN -1
      ELSE FLOOR(n.rn / " . $tsumegoFilters->collectionSize . ")
    END AS partition_number,
    COUNT(*) AS usage_count,
    COUNT(CASE WHEN n.status IN ('S', 'W') THEN 1 END) AS solved_count
  FROM numbered n
  JOIN tag_counts t ON t.tag_id = n.tag_id
  GROUP BY n.tag_name, n.tag_color, t.total_count, partition_number
)
SELECT *
FROM partitioned
ORDER BY total_count DESC, partition_number";

			$tagsRaw = ClassRegistry::init('Tsumego')->query($query);
			foreach ($tagsRaw as $key => $tagRaw) {
				$tagRaw = $tagRaw['partitioned'];
				$tag = [];
				$tag['id'] = $key;
				$tag['amount'] = $tagRaw['usage_count'];
				$tag['name'] = $tagRaw['name'];
				$partition = $tagRaw['partition_number'];
				$colorValue =  1 - (($partition == -1) ? 0 : -($partition * 0.15));
				$tag['color'] = str_replace('[o]', (string) $colorValue, $this->getTagColor($tagRaw['color']));
				$tag['solved'] = $tagRaw['solved_count'];
				$tag['partition'] = $partition;
				$tagList [] = $tag;
			}
		}
		if ($tsumegoFilters->query == 'topics' && empty($tsumegoFilters->sets)) {
			$queryRefresh = false;
		} elseif ($tsumegoFilters->query == 'difficulty' && empty($tsumegoFilters->ranks)) {
			$queryRefresh = false;
		} elseif ($tsumegoFilters->query == 'tags' && empty($tsumegoFilters->tags)) {
			$queryRefresh = false;
		} else {
			$queryRefresh = true;
		}

		$this->set('sets', $sets);
		$this->set('ranksArray', $ranksArray);
		$this->set('tagList', $tagList);
		$this->set('setTiles', $setTiles);
		$this->set('difficultyTiles', $difficultyTiles);
		$this->set('tagTiles', $tagTiles);
		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('searchCounter', $searchCounter);
		$this->set('hasPremium', Auth::hasPremium());
		$this->set('queryRefresh', $queryRefresh);
	}

	private function partitionCollections($list, $size, $tsumegoStatusMap) {
		$newList = [];
		$listCount = count($list);
		for ($i = 0; $i < $listCount; $i++) {
			$amountTags = $list[$i]['amount'];
			$amountCounter = 0;
			$amountFrom = 0;
			$amountTo = $size - 1;
			while ($amountTags > $size) {
				$newList = $this->partitionCollection($newList, $list[$i], $size, $tsumegoStatusMap, $amountFrom, $amountTo + 1, $amountCounter, true);
				$amountTags -= $size;
				$amountCounter++;
				$amountFrom += $size;
				$amountTo += $size;
			}
			$amountTo = $amountFrom + $amountTags;
			$newList = $this->partitionCollection($newList, $list[$i], $amountTags, $tsumegoStatusMap, $amountFrom, $amountTo, $amountCounter, false);
		}

		return $newList;
	}

	private function partitionCollection($newList, $list, $size, $tsumegoStatusMap, $from, $to, $amountCounter, $inLoop) {
		$tl = [];
		$tl['id'] = $list['id'];
		$colorValue = 1;
		if (!$inLoop && $amountCounter == 0) {
			$tl['partition'] = -1;
		} else {
			$tl['partition'] = $amountCounter;
			$step = 1.5;
			$colorValue = 1 - ($amountCounter * 0.1 * $step);
		}
		$tl['name'] = $list['name'];
		$tl['amount'] = $size;
		$tl['color'] = str_replace('[o]', (string) $colorValue, $list['color']);
		if (isset($list['premium'])) {
			$tl['premium'] = $list['premium'];
		} else {
			$tl['premium'] = 0;
		}
		$currentIds = [];
		for ($i = $from; $i < $to; $i++) {
			array_push($currentIds, $list['currentIds'][$i]);
		}
		$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $tsumegoStatusMap);
		$tl['difficulty'] = $difficultyAndSolved['difficulty'];
		$tl['solved'] = $difficultyAndSolved['solved'];
		array_push($newList, $tl);

		return $newList;
	}

	private function getDifficultyAndSolved($currentTagIds, $tsumegoStatusMap) {
		$tagTsumegoDifficulty = $this->Tsumego->find('all', ['conditions' => ['id' => $currentTagIds]]);
		if (!$tagTsumegoDifficulty) {
			$tagTsumegoDifficulty = [];
		}
		$tagDifficultyResult = 0;
		$statusCounter = 0;
		$tagTsumegoDifficultyCount2 = count($tagTsumegoDifficulty);
		for ($j = 0; $j < $tagTsumegoDifficultyCount2; $j++) {
			$tagDifficultyResult += $tagTsumegoDifficulty[$j]['Tsumego']['rating'];
			if (isset($tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']])) {
				if ($tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']] == 'C') {
					$statusCounter++;
				}
			}
		}
		if (count($tagTsumegoDifficulty) > 0) {
			$tagDifficultyResult = $tagDifficultyResult / count($tagTsumegoDifficulty);
		} else {
			$tagDifficultyResult = 0;
		}
		$tagDifficultyResult = Rating::getReadableRankFromRating($tagDifficultyResult);
		$return = [];
		$return['difficulty'] = $tagDifficultyResult;
		if (count($currentTagIds) > 0) {
			$return['solved'] = round($statusCounter / count($currentTagIds) * 100, 2);
		} else {
			$return['solved'] = 0;
		}

		return $return;
	}

	/**
	 * @param int|null $id Set ID
	 * @return void
	 */
	public function ui($id = null) {
		$s = $this->Set->findById($id);
		$redirect = false;

		if (isset($_FILES['adminUpload'])) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$randstring = 'set_';
			for ($i = 0; $i < 6; $i++) {
				$randstring .= $characters[rand(0, strlen($characters))];
			}
			$filename = $randstring . '_' . $_FILES['adminUpload']['name'];

			$errors = [];
			$file_name = $_FILES['adminUpload']['name'];
			$file_size = $_FILES['adminUpload']['size'];
			$file_tmp = $_FILES['adminUpload']['tmp_name'];
			$file_type = $_FILES['adminUpload']['type'];
			$array = explode('.', $_FILES['adminUpload']['name']);
			$file_ext = strtolower(end($array));
			$extensions = ['png', 'jpg'];

			if (in_array($file_ext, $extensions) === false) {
				$errors[] = 'png/jpg allowed.';
			}
			if ($file_size > 2097152) {
				$errors[] = 'The file is too large.';
			}

			if (empty($errors) == true) {
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

	public function view(string|int|null $id = null, int $partition = 1): void {
		// transferring from 1 indexed for humans to 0 indexed for us programmers.
		$partition = $partition - 1;
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Favorite');
		$this->loadModel('AdminActivity');
		$this->loadModel('Joseki');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('ProgressDeletion');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('TagName');
		$this->loadModel('Tag');
		$this->loadModel('User');
		$this->loadModel('UserContribution');

		if (is_null($id)) {
			throw new AppException("Set to view not specified");
		}

		if ($id != '1') {
			$this->Session->write('page', 'set');
		} else {
			$this->Session->write('page', 'favs');
		}
		$josekiOrder = 0;
		$tsIds = [];
		$refreshView = false;
		$avgTime = 0;
		$accuracy = 0;
		$formChange = false;
		$achievementUpdate = [];
		$viewType = 'topics';
		$tsumegoStatusMap = [];
		$setDifficulty = 1200;
		$currentIds = [];
		$allVcActive = false;
		$allVcInactive = false;
		$allArActive = false;
		$allArInactive = false;
		$allPassActive = false;
		$allPassInactive = false;
		$pdCounter = 0;
		$acS = null;
		$acA = null;

		$setsWithPremium = [];
		foreach ($this->Set->find('all', ['conditions' => ['premium' => 1]]) ?: [] as $setWithPremium) {
			$setsWithPremium [] = $setWithPremium['Set']['id'];
		}

		$tsumegoFilters = new TsumegoFilters();
		if (Auth::isLoggedIn()) {

			$tsumegoStatusMap = TsumegoUtil::getMapForCurrentUser();

			if (Auth::isAdmin()) {
				$aad = $this->AdminActivity->find('first', ['order' => 'id DESC']);
				if ($aad['AdminActivity']['file'] == '/delete') {
					$scDelete = $this->SetConnection->find('first', ['order' => 'created DESC', 'conditions' => ['tsumego_id' => $aad['AdminActivity']['tsumego_id']]]);
					$this->SetConnection->delete($scDelete['SetConnection']['id']);
					$this->Tsumego->delete($aad['AdminActivity']['tsumego_id']);
					$aad['AdminActivity']['file'] = 'description';
					$this->AdminActivity->save($aad);
				}
			}
		}
		if (isset($this->params['url']['add'])) {
			$overallCount = $this->Tsumego->find('first', ['order' => 'id DESC']);
			$scTcount = $this->SetConnection->find('first', ['conditions' => ['set_id' => $id, 'num' => 1]]);
			$setCount = $this->Tsumego->findById($scTcount['SetConnection']['tsumego_id']);
			$setCount['Tsumego']['id'] = $overallCount['Tsumego']['id'] + 1;
			$setCount['Tsumego']['set_id'] = $scTcount['SetConnection']['set_id'];
			$setCount['Tsumego']['num'] += 1;
			$setCount['Tsumego']['variance'] = 100;
			if (Auth::getUserID() == 72) {
				$setCount['Tsumego']['author'] = 'Joschka Zimdars';
			} elseif (Auth::getUserID() == 1206) {
				$setCount['Tsumego']['author'] = 'Innokentiy Zabirov';
			} elseif (Auth::getUserID() == 3745) {
				$setCount['Tsumego']['author'] = 'Dennis Olevanov';
			} else {
				$setCount['Tsumego']['author'] = Auth::getUser()['name'];
			}
			$this->Tsumego->create();
			$this->Tsumego->save($setCount);
			$adminActivity = [];
			$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
			$adminActivity['AdminActivity']['tsumego_id'] = 0;
			$adminActivity['AdminActivity']['file'] = 'description';
			$set = $this->Set->findById($id);
			$adminActivity['AdminActivity']['answer'] = 'Added problem for ' . $set['Set']['title'];
			$this->AdminActivity->save($adminActivity);
		}

		if (isset($this->params['url']['show'])) {
			if ($this->params['url']['show'] == 'order') {
				$josekiOrder = 1;
			}
			if ($this->params['url']['show'] == 'num') {
				$josekiOrder = 0;
			}
		}
		if ($id != '1') {
			if (is_numeric($id)) {
				$viewType = 'topics';
			} else {
				try {
					Rating::getRankFromReadableRank($id);
					$viewType = 'difficulty';
				} catch (Exception $e) {
					$viewType = 'tags';
				}
			}
			$tsumegoFilters->query = $viewType;

			$this->Session->write('lastSet', $id);
			$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $id);

			if ($viewType == 'difficulty') {
				$set = [];
				$set['Set']['id'] = $id;
				$set['Set']['title'] = $id . $tsumegoButtons->getPartitionTitleSuffix();
				$set['Set']['image'] = $id . 'Rank.png';
				$set['Set']['multiplier'] = 1;
				$set['Set']['public'] = 1;
				$elo = AppController::getTsumegoElo($id);
				$set['Set']['difficulty'] = $elo;

				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $tsumegoStatusMap);
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($tsumegoButtons);
			} elseif ($viewType == 'tags') {
				$set['Set']['id'] = $id;
				$set['Set']['image'] = '';
				$set['Set']['multiplier'] = 1;
				$set['Set']['public'] = 1;
				$setConditions = [];
				$rankConditions = [];
				$tagIds = [];
				$tagName = $this->TagName->findByName($id);
				if ($tagName && isset($tagName['TagName']['description'])) {
					$set['Set']['description'] = $tagName['TagName']['description'];
				}
				if (!empty($tsumegoFilters->setIDs)) {
					$tsumegoIDs = [];
					foreach ($tsumegoFilters->setIDs as $setID) {
						foreach (TsumegoUtil::collectTsumegosFromSet($setID) as $tsumego) {
							$tsumegoIDs [] = $tsumego['Tsumego']['id'];
						}
					}
					$setConditions['tsumego_id'] = $tsumegoIDs;
				}
				$tagsx = $this->Tag->find('all', [
					'order' => 'id ASC',
					'conditions' => [
						'tag_name_id' => $tagName['TagName']['id'],
						'approved' => 1,
						$setConditions,
					],
				]) ?: [];
				foreach ($tagsx as $tag) {
					$tagIds [] = $tag['Tag']['tsumego_id'];
				}

				if (!Auth::hasPremium()) {
					$currentIdsNew = [];
					$pTest = $this->Tsumego->find('all', ['conditions' => ['id' => $tagIds]]) ?: [];
					$pTestCount2 = count($pTest);
					for ($j = 0; $j < $pTestCount2; $j++) {
						if (!in_array($pTest[$j]['Tsumego']['set_id'], $setsWithPremium)) {
							array_push($currentIdsNew, $pTest[$j]['Tsumego']['id']);
						}
					}
					$tagIds = $currentIdsNew;
				}

				if (!empty($tsumegoFilters->ranks)) {
					$fromTo = [];
					foreach ($tsumegoFilters->ranks as $rank) {
						$ft = [];
						$ft['rating >='] = AppController::getTsumegoElo($rank);
						$ft['rating <'] = $ft['rating >='] + 100;
						if ($rank == '15k') {
							$ft['rating >='] = 50;
						}
						$fromTo [] = $ft;
					}
					$rankConditions['OR'] = $fromTo;
				}
				$ts = $this->Tsumego->find('all', [
					'order' => 'id ASC',
					'conditions' => [
						'id' => $tagIds,
						'public' => 1,
						$rankConditions,
					],
				]) ?: [];

				// TODO: this is very temporary and very unoptimal way to fill the tsumego buttons from tsumego array
				// it will be cleaned when there is time to change the code above
				$tsumegoButtons = new TsumegoButtons();
				foreach ($ts as $key => $t) {
					if ($setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditins' => ['tsumego_id' => $t['Tsumego']['id']]])) {
						$tsumegoStatus = Auth::isLoggedIn() ? ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'user_id' => Auth::getUserID()]]) : null;
						$tsumegoButtons [] = new TsumegoButton(
							$t['Tsumego']['id'],
							$setConnection['SetConnection']['id'],
							$key,
							$tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : 'N',
							$t['Tsumego']['pass'],
							$t['Tsumego']['alternative_response']
						);
					}
				}


				$tsumegoButtons->partitionByParameter($partition, $tsumegoFilters->collectionSize);
				$currentIds = [];
				foreach ($tsumegoButtons as $tsumegoButton) {
					$currentIds[] = $tsumegoButton->tsumegoID;
				}
				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $tsumegoStatusMap);
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($tsumegoButtons);
				$set['Set']['title'] = $id . $tsumegoButtons->getPartitionTitleSuffix();
			} else {
				$currentIds = [];
				foreach ($tsumegoButtons as $tsumegoButton) {
					$currentIds [] =  $tsumegoButton->tsumegoID;
				}
				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $tsumegoStatusMap);
				$set = ClassRegistry::init('Set')->findById($id);
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($tsumegoButtons);
				$set['Set']['title'] = $set['Set']['title'] . $tsumegoButtons->getPartitionTitleSuffix();
				$allArActive = true;
				$allArInactive = true;
				$allPassActive = true;
				$allPassInactive = true;
				foreach ($tsumegoButtons as $tsumegoButton) {
					if (!$tsumegoButton->alternativeResponse) {
						$allArActive = false;
					}
					if (!$tsumegoButton->passEnabled) {
						$allPassActive = false;
					}
				}
				foreach ($tsumegoButtons as $tsumegoButton) {
					$tsIds [] = $tsumegoButton->tsumegoID;
				}
				if ($set['Set']['public'] == 0) {
					$this->Session->write('page', 'sandbox');
				}
				$this->set('isFav', false);
				if (isset($this->params['url']['sort'])) {
					if ($this->params['url']['sort'] == 1) {
						$tsId = [];
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsId [] = $tsumegoButton->tsumegoID;
						}
						$nr = 1;
						$tsIdCount = count($tsId);
						for ($i = 0; $i < $tsIdCount; $i++) {
							$tsu = $this->Tsumego->findById($tsId[$i]);
							if ($tsu['Tsumego']['num'] != $nr) {
								rename('6473k339312/joseki/' . $tsu['Tsumego']['num'] . '.sgf', '6473k339312/joseki/' . $tsu['Tsumego']['num'] . 'x.sgf');
							}
							$nr++;
						}
						$nr = 1;
						$tsIdCount = count($tsId);
						for ($i = 0; $i < $tsIdCount; $i++) {
							$tsu = $this->Tsumego->findById($tsId[$i]);
							if ($tsu['Tsumego']['num'] != $nr) {
								rename('6473k339312/joseki/' . $tsu['Tsumego']['num'] . 'x.sgf', '6473k339312/joseki/' . $nr . '.sgf');
								$tsu['Tsumego']['num'] = $nr;
								$this->Tsumego->save($tsu);
							}
							$nr++;
						}
					}
				}
				if (isset($this->params['url']['rename'])) {
					if ($this->params['url']['rename'] == 1) {
						$tsId = [];
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsId [] = $tsumegoButton->tsumegoID;
						}
						$nr = 1;
						foreach ($tsId as $id) {
							$j = $this->Joseki->find('first', ['conditions' => ['tsumego_id' => $id]]);
							$j['Joseki']['order'] = $nr;
							$this->Joseki->save($j);
							$nr++;
						}
					}
				}
				if (isset($this->data['Set']['title'])) {
					if ($set['Set']['title'] != $this->data['Set']['title']) {
						$formChange = true;
					}
					if ($set['Set']['title2'] != $this->data['Set']['title2']) {
						$formChange = true;
					}
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['title'] = $this->data['Set']['title'];
					$changeSet['Set']['title2'] = $this->data['Set']['title2'];
					$this->set('data', $changeSet['Set']['title']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set ' . $set['Set']['title'];
				}
				if (isset($this->data['Set']['description'])) {
					if ($set['Set']['description'] != $this->data['Set']['description']) {
						$formChange = true;
					}
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['description'] = $this->data['Set']['description'];
					$this->set('data', $changeSet['Set']['description']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set ' . $set['Set']['title'];
				}
				if (isset($this->data['Set']['setDifficulty'])) {
					if ($this->data['Set']['setDifficulty'] != 1200 && $this->data['Set']['setDifficulty'] >= 900 && $this->data['Set']['setDifficulty'] <= 2900) {
						$setDifficultyTsumegoSet = TsumegoUtil::collectTsumegosFromSet($set['Set']['id']);
						$setDifficulty = $this->data['Set']['setDifficulty'];
						$setDifficultyTsumegoSetCount = count($setDifficultyTsumegoSet);
						for ($i = 0; $i < $setDifficultyTsumegoSetCount; $i++) {
							$setDifficultyTsumegoSet[$i]['Tsumego']['rating'] = $this->data['Set']['setDifficulty'];
							$this->Tsumego->save($setDifficultyTsumegoSet[$i]);
						}
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Edited rating data for set ' . $set['Set']['title'];
					}
				}
				if (isset($this->data['Set']['color'])) {
					if ($set['Set']['color'] != $this->data['Set']['color']) {
						$formChange = true;
					}
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['color'] = $this->data['Set']['color'];
					$this->set('data', $changeSet['Set']['color']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set ' . $set['Set']['title'];
					$this->AdminActivity->save($adminActivity);
				}
				if (isset($this->data['Set']['order'])) {
					if ($set['Set']['order'] != $this->data['Set']['order']) {
						$formChange = true;
					}
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['order'] = $this->data['Set']['order'];
					$this->set('data', $changeSet['Set']['order']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set ' . $set['Set']['title'];
					$this->AdminActivity->save($adminActivity);
				}
				if (isset($this->data['Settings'])) {
					if ($this->data['Settings']['r39'] == 'on') {
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
							$tsumego['alternative_response'] = true;
							ClassRegistry::init('Tsumego')->save($tsumego);
						}
						$allArActive = true;
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned on alternative response mode for set ' . $set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if ($this->data['Settings']['r39'] == 'off') {
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
							$tsumego['alternative_response'] = false;
							ClassRegistry::init('Tsumego')->save($tsumego);
						}
						$allArInactive = true;
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned off alternative response mode for set ' . $set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if ($this->data['Settings']['r43'] == 'yes') {
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
							$tsumego['pass'] = true;
							ClassRegistry::init('Tsumego')->save($tsumego);
						}
						$allPassActive = true;
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Enabled passing for set ' . $set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if ($this->data['Settings']['r43'] == 'no') {
						foreach ($tsumegoButtons as $tsumegoButton) {
							$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoButton->tsumegoID);
							$tsumego['pass'] = false;
							ClassRegistry::init('Tsumego')->save($tsumego);
						}
						$allPassInactive = true;
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoButtons[0]->tsumegoID;
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Disabled passing for set ' . $set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					$this->set('formRedirect', true);
				}
				if (isset($this->data['Tsumego'])) {
					if (!$formChange) {
						$scFormChange = $this->SetConnection->find('first', ['order' => 'num DESC', 'conditions' => ['set_id' => $id]]);
						$tFormChange = $this->Tsumego->findById($scFormChange['SetConnection']['tsumego_id']);
						if ($scFormChange == null) {
							$scFormChange = [];
							$scFormChange['SetConnection']['set_id'] = $id;
							$scFormChange['SetConnection']['num'] = $this->data['Tsumego']['num'];
							$tFormChange = [];
							$tFormChange['Tsumego']['rating'] = 1000;
						}
						$tf = [];
						$tf['Tsumego']['num'] = $this->data['Tsumego']['num'];
						$tf['Tsumego']['difficulty'] = 40;
						$tf['Tsumego']['set_id'] = $id;
						$tf['Tsumego']['variance'] = $this->data['Tsumego']['variance'];
						$tf['Tsumego']['description'] = $this->data['Tsumego']['description'];
						$tf['Tsumego']['hint'] = $this->data['Tsumego']['hint'];
						$tf['Tsumego']['author'] = $this->data['Tsumego']['author'];
						$tf['Tsumego']['rating'] = $tFormChange['Tsumego']['rating'];
						if (is_numeric($this->data['Tsumego']['num'])) {
							if ($this->data['Tsumego']['num'] >= 0) {
								$this->Tsumego->create();
								$this->Tsumego->save($tf);
								$tfSetHighestId = $this->Tsumego->find('first', ['order' => 'id DESC']);
								$tfSetConnection = [];
								$tfSetConnection['SetConnection']['set_id'] = $id;
								$tfSetConnection['SetConnection']['tsumego_id'] = $tfSetHighestId['Tsumego']['id'];
								$tfSetConnection['SetConnection']['num'] = $this->data['Tsumego']['num'];
								$this->SetConnection->create();
								$this->SetConnection->save($tfSetConnection);
							}
						}
					}
				}
			}

			//favs
		} else {
			$allUts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$allUts) {
				$allUts = [];
			}
			$idMap = [];
			$statusMap = [];
			$allUtsCount = count($allUts);
			for ($i = 0; $i < $allUtsCount; $i++) {
				array_push($idMap, $allUts[$i]['TsumegoStatus']['tsumego_id']);
				array_push($statusMap, $allUts[$i]['TsumegoStatus']['status']);
			}
			$fav = $this->Favorite->find('all', ['order' => 'created', 'direction' => 'DESC', 'conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$fav) {
				$fav = [];
			}
			if (count($fav) > 0) {
				$achievementUpdate = $this->checkSetAchievements(-1);
			}
			$ts = [];
			$difficultyCount = 0;
			$solvedCount = 0;
			$sizeCount = 0;
			$favCount = count($fav);
			for ($i = 0; $i < $favCount; $i++) {
				$tx = $this->Tsumego->find('first', ['conditions' => ['id' => $fav[$i]['Favorite']['tsumego_id']]]);
				$difficultyCount += $tx['Tsumego']['difficulty'];
				$utx = $this->findUt($fav[$i]['Favorite']['tsumego_id'], $allUts, $idMap);
				if ($utx['TsumegoStatus']['status'] == 'S' || $utx['TsumegoStatus']['status'] == 'W' || $utx['TsumegoStatus']['status'] == 'C') {
					$solvedCount++;
				}
				$sizeCount++;
				array_push($ts, $tx);
			}
			$allUtsCount = count($allUts);
			for ($i = 0; $i < $allUtsCount; $i++) {
				$tsCount2 = count($ts);
				for ($j = 0; $j < $tsCount2; $j++) {
					if ($allUts[$i]['TsumegoStatus']['tsumego_id'] == $ts[$j]['Tsumego']['id']) {
						$ts[$j]['Tsumego']['status'] = $allUts[$i]['TsumegoStatus']['status'];
					}
				}
			}
			$difficultyCount /= $sizeCount;
			if ($difficultyCount <= 2) {
				$difficultyCount = 1;
			} elseif ($difficultyCount > 2 && $difficultyCount <= 3) {
				$difficultyCount = 2;
			} elseif ($difficultyCount > 3 && $difficultyCount <= 4) {
				$difficultyCount = 3;
			} elseif ($difficultyCount > 4 && $difficultyCount <= 6) {
				$difficultyCount = 4;
			} elseif ($difficultyCount > 6) {
				$difficultyCount = 5;
			}
			$percent = $solvedCount / $sizeCount * 100;
			$set = [];
			$set['Set']['id'] = 1;
			$set['Set']['title'] = 'Favorites';
			$set['Set']['title2'] = null;
			$set['Set']['author'] = Auth::getUser()['name'];
			$set['Set']['description'] = '';
			$set['Set']['difficulty'] = $difficultyCount;
			$set['Set']['image'] = 'fav';
			$set['Set']['order'] = 0;
			$set['Set']['public'] = 1;
			$set['Set']['created'] = 20180322;
			$set['Set']['t'] = '222';
			$set['Set']['anz'] = (int) 50;
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

		// temporary stan fix until all of the ways buttons are filled are unified
		if (!isset($tsumegoButtons)) {
			$tsumegoButtons = new TsumegoButtons();
		}

		if ($tsumegoButtons->description) {
			$set['Set']['description'] = $tsumegoButtons->description;
		}

		$this->Session->write('title', $set['Set']['title'] . ' on Tsumego Hero');
		$set['Set']['anz'] = count($tsumegoButtons);

		if (Auth::isLoggedIn() && $viewType == 'topics') {
			$ur = $this->TsumegoAttempt->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]) ?: [];
			foreach ($tsumegoButtons as $tsumegoButton) {
				$urTemp = [];
				$urSum = '';
				$tsumegoButton->seconds = 0;
				$urCount2 = count($ur);
				for ($j = 0; $j < $urCount2; $j++) {
					if ($tsumegoButton->tsumegoID == $ur[$j]['TsumegoAttempt']['tsumego_id']) {
						array_push($urTemp, $ur[$j]);
						if ($ur[$j]['TsumegoAttempt']['solved']) {
							$tsumegoButton->seconds = $ur[$j]['TsumegoAttempt']['seconds'];
						}
						if (!$ur[$j]['TsumegoAttempt']['solved']) {
							$mis = $ur[$j]['TsumegoAttempt']['misplays'];
							if ($mis == 0) {
								$mis = 1;
							}
							while ($mis > 0) {
								$urSum .= 'F';
								$mis--;
							}
						} else {
							$urSum .= $ur[$j]['TsumegoAttempt']['solved'];
						}
					}
				}
				$tsumegoButton->performance = $urSum;
			}
		}
		$tfs = [];
		if ($viewType == 'topics') {
			$tfs = TsumegoUtil::collectTsumegosFromSet($id);
		}
		$scoring = true;
		if (Auth::isLoggedIn() && $viewType == 'topics') {
			if (isset($this->data['Comment']['reset'])) {
				if ($this->data['Comment']['reset'] == 'reset') {
					$uts = $this->TsumegoStatus->find('all', [
						'conditions' => [
							'user_id' => Auth::getUserID(),
							'tsumego_id' => $currentIds,
						],
					]);
					if (!$uts) {
						$uts = [];
					}
					$ur = $this->TsumegoAttempt->find('all', [
						'conditions' => [
							'user_id' => Auth::getUserID(),
							'tsumego_id' => $currentIds,
						],
					]);
					if (!$ur) {
						$ur = [];
					}
					$urCount = count($ur);
					for ($i = 0; $i < $urCount; $i++) {
						$this->TsumegoAttempt->delete($ur[$i]['TsumegoAttempt']['id']);
					}
					//$loggedInUserUts = $this->Session->read('loggedInUser.uts');
					$utsCount = count($uts);
					for ($i = 0; $i < $utsCount; $i++) {
						$this->TsumegoStatus->delete($uts[$i]['TsumegoStatus']['id']);
						//unset($loggedInUserUts[$uts[$i]['TsumegoStatus']['tsumego_id']]);
					}
					//$this->Session->write('loggedInUser.uts', $loggedInUserUts);
					$pr = [];
					$pr['ProgressDeletion']['user_id'] = Auth::getUserID();
					$pr['ProgressDeletion']['set_id'] = $id;
					$this->ProgressDeletion->create();
					$this->ProgressDeletion->save($pr);
					$refreshView = true;
				}
			}
			$pd = $this->ProgressDeletion->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'set_id' => $id,
				],
			]) ?: [];
			$pdCounter = 0;
			$pdCount = count($pd);
			for ($i = 0; $i < $pdCount; $i++) {
				$date = date_create($pd[$i]['ProgressDeletion']['created']);
				$pd[$i]['ProgressDeletion']['d'] = $date->format('Y') . '-' . $date->format('m');
				if (date('Y-m') == $pd[$i]['ProgressDeletion']['d']) {
					$pdCounter++;
				}
			}
			$urSecCounter = 0;
			$urSecAvg = 0;
			$pSsum = 0;
			$pFsum = 0;
			$tsCount = count($tsumegoButtons);
			foreach ($tsumegoButtons as $tsumegoButton) {
				$tss = 0;
				if ($tsumegoButton->seconds == 0) {
					if (TsumegoUtil::isSolvedStatus($tsumegoButton->status)) {
						$tss = 60;
					} else {
						$tss = 0;
					}
				} else {
					$tss = $tsumegoButton->seconds;
				}
				$urSecAvg += $tss;
				$urSecCounter++;

				$tss2 = 'F';
				if ($tsumegoButton->performance == '') {
					if (TsumegoUtil::isSolvedStatus($tsumegoButton->status)) {
						$tss2 = 'F';
					} else {
						$tss2 = '';
					}
				} else {
					$tss2 = $tsumegoButton->performance;
				}
				$pS = substr_count($tss2, '1');
				$pF = substr_count($tss2, 'F');
				$pSsum += $pS;
				$pFsum += $pF;
			}
			if ($urSecCounter == 0) {
				$avgTime = 60;
			} else {
				$avgTime = round($urSecAvg / $urSecCounter, 2);
			}
			if ($pSsum + $pFsum == 0) {
				$accuracy = 0;
			} else {
				$accuracy = round($pSsum / ($pSsum + $pFsum) * 100, 2);
			}
			$avgTime2 = $avgTime;
			$achievementUpdate2 = [];
			$achievementUpdate1 = [];
			if ($set['Set']['solved'] >= 100) {
				if ($set['Set']['id'] != 210) {
					$this->updateAchievementConditions($set['Set']['id'], $avgTime2, $accuracy);
					$achievementUpdate1 = $this->checkSetAchievements($set['Set']['id']);
				}
				if ($id == 50 || $id == 52 || $id == 53 || $id == 54) {
					$achievementUpdate2 = $this->setAchievementSpecial('cc1');
				} elseif ($id == 41 || $id == 49 || $id == 65 || $id == 66) {
					$achievementUpdate2 = $this->setAchievementSpecial('cc2');
				} elseif ($id == 186 || $id == 187 || $id == 196 || $id == 203) {
					$achievementUpdate2 = $this->setAchievementSpecial('cc3');
				} elseif ($id == 190 || $id == 193 || $id == 198) {
					$achievementUpdate2 = $this->setAchievementSpecial('1000w1');
				} elseif ($id == 216) {
					$achievementUpdate2 = $this->setAchievementSpecial('1000w2');
				}
				$achievementUpdate = array_merge($achievementUpdate1, $achievementUpdate2);
			}
			if (count($achievementUpdate) > 0) {
				$this->updateXP(Auth::getUserID(), $achievementUpdate);
			}

			$acS = $this->AchievementCondition->find('first', [
				'order' => 'value ASC',
				'conditions' => [
					'set_id' => $id,
					'user_id' => Auth::getUserID(),
					'category' => 's',
				],
			]);
			$acA = $this->AchievementCondition->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'set_id' => $id,
					'user_id' => Auth::getUserID(),
					'category' => '%',
				],
			]);
		} else {
			$scoring = false;
		}
		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		foreach ($tsumegoButtons as $tsumegoButton) {
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $tsumegoButton->tsumegoID]]);
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			$tooltipSgfs [] = $tArr[0];
			$tooltipInfo [] = $tArr[2];
			$tooltipBoardSize [] = $tArr[3];
		}

		$allTags = $this->TagName->find('all') ?: [];
		$allTagsSorted = [];
		$allTagsKeys = [];
		$allTagsCount = count($allTags);
		for ($i = 0; $i < $allTagsCount; $i++) {
			array_push($allTagsSorted, $allTags[$i]['TagName']['name']);
			$allTagsKeys[$allTags[$i]['TagName']['name']] = $allTags[$i];
		}
		sort($allTagsSorted);
		$s2Tags = [];
		$allTagsSortedCount = count($allTagsSorted);
		for ($i = 0; $i < $allTagsSortedCount; $i++) {
			array_push($s2Tags, $allTagsKeys[$allTagsSorted[$i]]);
		}

		$allTags = $s2Tags;

		if (Auth::isAdmin()) {
			if (isset($_COOKIE['addTag']) && $_COOKIE['addTag'] != 0) {
				if ($this->params['url']['hash'] == '32bb90e8976aab5298d5da10fe66f21d') {
					$newAddTag = explode('-', $_COOKIE['addTag']);
					$tagSetId = (int) $newAddTag[0];
					$newTagName = $this->TagName->find('first', ['conditions' => ['name' => str_replace($tagSetId . '-', '', $_COOKIE['addTag'])]]);
					$tagSc = TsumegoUtil::collectTsumegosFromSet($tagSetId);
					$tagScCount = count($tagSc);
					for ($i = 0; $i < $tagScCount; $i++) {
						$tagAlreadyThere = $this->Tag->find('first', [
							'conditions' => [
								'tsumego_id' => $tagSc[$i]['Tsumego']['id'],
								'tag_name_id' => $newTagName['TagName']['id'],
							],
						]);
						if ($tagAlreadyThere == null) {
							$saveTag = [];
							$saveTag['Tag']['tag_name_id'] = $newTagName['TagName']['id'];
							$saveTag['Tag']['tsumego_id'] = $tagSc[$i]['Tsumego']['id'];
							$saveTag['Tag']['user_id'] = Auth::getUserID();
							$saveTag['Tag']['approved'] = 1;
							$this->Tag->create();
							$this->Tag->save($saveTag);
						}
					}
					$this->set('removeCookie', 'addTag');
				}
			}
		}

		if ($viewType == 'topics') {
			$this->set('tfs', $tfs[count($tfs) - 1]);
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
		$this->set('viewType', $viewType);
		$this->set('allTags', $allTags);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('set', $set);
		$this->set('josekiOrder', $josekiOrder);
		$this->set('refreshView', $refreshView);
		$this->set('avgTime', $avgTime);
		$this->set('accuracy', $accuracy);
		$this->set('scoring', $scoring);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('setDifficulty', $setDifficulty);
	}

	public function download_archive(): void {
		$s = $this->Set->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'OR' => [
					['public' => 1],
					['public' => 0],
				],
			],
		]);

		$text = file_get_contents('download_archive.txt');

		$text2 = $text + 1;
		file_put_contents('download_archive.txt', $text2);

		$this->set('text', $text);
		$this->set('s', $s);
	}

	/**
	 * @param int|null $id Set ID
	 * @return void
	 */
	public function download_archive2($id = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$this->loadModel('Sgf');

		$title = '';
		$t = [];

		if (Auth::getUserID() == 72) {
			$s = $this->Set->findById($id);
			$title = $s['Set']['title'] . ' ' . $s['Set']['title2'];

			$title = str_replace(':', '', $title);

			if ($s['Set']['public'] != 1) {
				$title .= ' (sandbox)';
			}

			mkdir('download_archive/' . $title);

			$ts = [];
			$scTs = $this->SetConnection->find('all', ['conditions' => ['set_id' => $id]]);
			if (!$scTs) {
				$scTs = [];
			}

			$scTsCount = count($scTs);
			for ($i = 0; $i < $scTsCount; $i++) {
				$scT = $this->Tsumego->findById($scTs[$i]['SetConnection']['tsumego_id']);
				$scT['Tsumego']['set_id'] = $scTs[$i]['SetConnection']['set_id'];
				$scT['Tsumego']['num'] = $scTs[$i]['SetConnection']['num'];
				$scT['Tsumego']['duplicateLink'] = '';
				$scTs2 = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $scT['Tsumego']['id']]]) ?: [];
				$scTs2Count2 = count($scTs2);
				for ($j = 0; $j < $scTs2Count2; $j++) {
					if (count($scTs2) > 1 && $scTs2[$j]['SetConnection']['set_id'] == $s['Set']['id']) {
						$scT['Tsumego']['duplicateLink'] = '?sid=' . $scT['Tsumego']['set_id'];
					}
				}

				array_push($ts, $scT);
			}

			$tsBuffer = [];
			$tsBufferLowest = 10000;
			$tsBufferHighest = 0;
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				$tsBuffer[$ts[$i]['Tsumego']['num']] = $ts[$i];
				if ($ts[$i]['Tsumego']['num'] < $tsBufferLowest) {
					$tsBufferLowest = $ts[$i]['Tsumego']['num'];
				}
				if ($ts[$i]['Tsumego']['num'] > $tsBufferHighest) {
					$tsBufferHighest = $ts[$i]['Tsumego']['num'];
				}
			}

			$t = [];
			for ($i = $tsBufferLowest; $i <= $tsBufferHighest; $i++) {
				if (isset($tsBuffer[$i])) {
					array_push($t, $tsBuffer[$i]);
				}
			}

			$tCount = count($t);
			for ($i = 0; $i < $tCount; $i++) {
				$t[$i]['Tsumego']['title'] = $s['Set']['title'] . ' ' . $t[$i]['Tsumego']['num'];
				$sgf = $this->Sgf->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $t[$i]['Tsumego']['id']]]);
				$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
				//$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
				$t[$i]['Tsumego']['sgf'] = $sgf['Sgf']['sgf'];

				file_put_contents('download_archive/' . $title . '/' . $t[$i]['Tsumego']['title'] . '.sgf', $t[$i]['Tsumego']['sgf']);
			}
		}
		$text = file_get_contents('download_archive.txt');

		$sAll = $this->Set->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'OR' => [
					['public' => 1],
					['public' => 0],
				],
			],
		]);

		$this->set('s', $sAll);
		$this->set('text', $text);
		$this->set('title', $title);
		$this->set('t', $t);
	}

	/**
	 * @param int $sid Set ID
	 * @param float $avgTime Average time
	 * @param float $accuracy Accuracy percentage
	 * @return void
	 */
	public function updateAchievementConditions($sid, $avgTime, $accuracy) {
		$uid = Auth::getUserID();
		$acS = $this->AchievementCondition->find('first', ['order' => 'value ASC', 'conditions' => ['set_id' => $sid, 'user_id' => $uid, 'category' => 's']]);
		$acA = $this->AchievementCondition->find('first', ['order' => 'value DESC', 'conditions' => ['set_id' => $sid, 'user_id' => $uid, 'category' => '%']]);

		if ($acS == null) {
			$aCond = [];
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $avgTime;
			$aCond['AchievementCondition']['category'] = 's';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		} else {
			if ($avgTime < $acS['AchievementCondition']['value']) {
				$acS['AchievementCondition']['value'] = $avgTime;
				$this->AchievementCondition->save($acS);
			}
		}
		if ($acA == null) {
			$aCond = [];
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $accuracy;
			$aCond['AchievementCondition']['category'] = '%';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		} else {
			if ($accuracy > $acA['AchievementCondition']['value']) {
				$acA['AchievementCondition']['value'] = $accuracy;
				$this->AchievementCondition->save($acA);
			}
		}
	}

	/**
	 * @return void
	 */
	public function beta2() {
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');

		$this->Session->write('page', 'sandbox');
		$this->Session->write('title', 'Deleted Collections');

		if (isset($this->params['url']['remove'])) {
			$remove = $this->Set->findById($this->params['url']['remove']);
			if ($remove['Set']['public'] == 0) {
				$remove['Set']['public'] = -1;
				$this->Set->save($remove);
			}
		}

		$setsX = $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => -1],
		]);
		if (!$setsX) {
			$setsX = [];
		}

		$secretPoints = 0;
		$removeMap = [];
		$globalSolvedCounter = 0;
		$percent = 0;

		$sets = [];
		$setsXCount = count($setsX);
		/*
		for ($i = 0; $i < $setsXCount; $i++) {
		if (!isset($removeMap[$setsX[$i]['Set']['id']])) {
		array_push($sets, $setsX[$i]);
		}
		}
		*/
		if (Auth::isLoggedIn()) {
			$tsumegoStatusMap = TsumegoUtil::getMapForCurrentUser();
		}

		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$i]['Set']['id']);
			$sets[$i]['Set']['anz'] = count($ts);
			$counter = 0;

			if (Auth::isLoggedIn()) {
				$tsCount3 = count($ts);
				for ($k = 0; $k < $tsCount3; $k++) {
					if (isset($tsumegoStatusMap[$ts[$k]['Tsumego']['id']])
					&& ($tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'C')) {
						$counter++;
						$globalSolvedCounter++;
					}
				}
			} else {
				if ($this->Session->check('noLogin')) {
					$noLogin = $this->Session->read('noLogin');
					$noLoginStatus = $this->Session->read('noLoginStatus');
					$noLoginCount6 = count($noLogin);
					for ($g = 0; $g < $noLoginCount6; $g++) {
						$tsCount5 = count($ts);
						for ($f = 0; $f < $tsCount5; $f++) {
							if ($ts[$f]['Tsumego']['id'] == $noLogin[$g]) {
								$ts[$f]['Tsumego']['status'] = $noLoginStatus[$g];
								if ($noLoginStatus[$g] == 'S' || $noLoginStatus[$g] == 'W' || $noLoginStatus[$g] == 'C') {
									$counter++;
								}
							}
						}
					}
				}
			}

			$date = new DateTime($sets[$i]['Set']['created']);
			$month = date('F', strtotime($sets[$i]['Set']['created']));
			$setday = $date->format('d. ');
			$setyear = $date->format('Y');
			if ($setday[0] == 0) {
				$setday = substr($setday, -3);
			}
			$sets[$i]['Set']['created'] = $date->format('Ymd');
			$sets[$i]['Set']['createdDisplay'] = $setday . $month . ' ' . $setyear;

			if (count($ts) > 0) {
				$percent = $counter / count($ts) * 100;
			}
			$sets[$i]['Set']['solvedNum'] = $counter;
			$sets[$i]['Set']['solved'] = round($percent, 1);
			$sets[$i]['Set']['solvedColor'] = $this->getSolvedColor($sets[$i]['Set']['solved']);
			$sets[$i]['Set']['topicColor'] = $sets[$i]['Set']['color'];
			$sets[$i]['Set']['difficultyColor'] = $this->getDifficultyColor($sets[$i]['Set']['difficulty']);
			$sets[$i]['Set']['sizeColor'] = $this->getSizeColor($sets[$i]['Set']['anz']);
			$sets[$i]['Set']['dateColor'] = $this->getDateColor($sets[$i]['Set']['created']);
		}
		$this->set('sets', $setsX);
	}

	private function findUt($id = null, $allUts = null, $map = null) {
		$currentUt = array_search($id, $map);
		$ut = $allUts[$currentUt];
		if ($currentUt == 0) {
			if ($id != $map[0]) {
				$ut = null;
			}
		}

		return $ut;
	}

	private function getDifficultyColor($difficulty = null) {
		if ($difficulty == 1) {
			return '#33cc33';
		}
		if ($difficulty == 2) {
			return '#709533';
		}
		if ($difficulty == 3) {
			return '#2e3370';
		}
		if ($difficulty == 4) {
			return '#ac5d33';
		}
		if ($difficulty == 5) {
			return '#e02e33';
		}

		return 'white';
	}

	private function getSizeColor($size = null) {
		$colors = [];
		array_push($colors, '#cc6600');
		array_push($colors, '#ac4e26');
		array_push($colors, '#963e3e');
		array_push($colors, '#802e58');
		array_push($colors, '#60167d');
		if ($size < 30) {
			return $colors[0];
		}
		if ($size < 60) {
			return $colors[1];
		}
		if ($size < 110) {
			return $colors[2];
		}
		if ($size < 202) {
			return $colors[3];
		}

		return $colors[4];
	}

	private function getDateColor($date = null) {
		$current = '20180705';
		$dist = $current - $date;

		if ($dist < 7) {
			return '#0033cc';
		}
		if ($dist < 100) {
			return '#0f33ad';
		}
		if ($dist < 150) {
			return '#1f338f';
		}
		if ($dist < 200) {
			return '#2e3370';
		}
		if ($dist < 300) {
			return '#3d3352';
		}
		if ($dist < 400) {
			return '#4c3333';
		}
		if ($dist < 500) {
			return '#57331f';
		}

		return '#663300';
	}

	private function getSolvedColor($percent = null) {
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
		for ($i = 0; $i < $colorsCount; $i++) {
			if ($percent <= $steps) {
				return $colors[$i];
			}
			$steps += 2.5;
		}

		return '#333333';
	}

	private function getExistingRanksArray() {
		$this->loadModel('Tsumego');
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
		$nine = $this->Tsumego->find('first', [
			'conditions' => [
				'rating >=' => AppController::getTsumegoElo('9d'),
			],
		]);
		if ($nine != null) {
			$ranksArray[23]['rank'] = '9d';
			$ranksArray[23]['color'] = 'rgba(244, 88, 88, [o])';
		}

		return $ranksArray;
	}
	private function getTagColor($pos) {
		$c = [];
		$c[0] = 'rgba(217, 135, 135, [o])';
		$c[1] = 'rgba(135, 149, 101, [o])';
		$c[2] = 'rgba(190, 151, 131, [o])';
		$c[3] = 'rgba(188, 116, 45, [o])';
		$c[4] = 'rgba(153, 111, 31, [o])';
		$c[5] = 'rgba(159, 54, 0, [o])';
		$c[6] = 'rgba(153, 151, 31, [o])';
		$c[7] = 'rgba(114, 9, 183, [o])';
		$c[8] = 'rgba(149, 77, 63, [o])';
		$c[9] = 'rgba(179, 181, 37, [o])';
		$c[10] = 'rgba(137, 153, 31, [o])';
		$c[11] = 'rgba(145, 61, 91, [o])';
		$c[12] = 'rgba(79, 68, 68, [o])';
		$c[13] = 'rgba(182, 137, 199, [o])';
		$c[14] = 'rgba(166, 88, 125, [o])';
		$c[15] = 'rgba(45, 37, 79, [o])';
		$c[16] = 'rgba(154, 50, 138, [o])';
		$c[17] = 'rgba(102, 51, 122, [o])';
		$c[18] = 'rgba(184, 46, 126, [o])';
		$c[19] = 'rgba(119, 50, 154, [o])';
		$c[20] = 'rgba(187, 70, 196, [o])';
		$c[21] = 'rgba(125, 8, 8, [o])';
		$c[22] = 'rgba(136, 67, 56, [o])';
		$c[23] = 'rgba(190, 165, 136, [o])';
		$c[24] = 'rgba(128, 118, 123, [o])';

		return $c[$pos];
	}

}
