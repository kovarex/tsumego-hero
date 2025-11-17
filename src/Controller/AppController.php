<?php

App::uses('Auth', 'Utility');
App::uses('TsumegoFilters', 'Utility');

class AppController extends Controller {
	public $viewClass = 'App';

	public $components = [
		'Session',
		//'DebugKit.Toolbar',
		'Flash',
		'PlayResultProcessor',
		'TimeMode',
	];

	public static function processSGF($sgf) {
		$aw = strpos($sgf, 'AW');
		$ab = strpos($sgf, 'AB');
		$boardSizePos = strpos($sgf, 'SZ');
		$boardSize = 19;
		$sgfArr = str_split($sgf);
		if ($boardSizePos !== false) {
			$boardSize = $sgfArr[$boardSizePos + 3] . '' . $sgfArr[$boardSizePos + 4];
		}
		if (substr($boardSize, 1) == ']') {
			$boardSize = substr($boardSize, 0, 1);
		}

		$black = AppController::getInitialPosition($ab, $sgfArr, 'x');
		$white = AppController::getInitialPosition($aw, $sgfArr, 'o');
		$stones = array_merge($black, $white);

		$board = [];
		for ($i = 0; $i < 19; $i++) {
			$board[$i] = [];
			for ($j = 0; $j < 19; $j++) {
				$board[$i][$j] = '-';
			}
		}
		$lowestX = 18;
		$lowestY = 18;
		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++) {
			if ($stones[$i][0] < $lowestX) {
				$lowestX = $stones[$i][0];
			}
			if ($stones[$i][0] > $highestX) {
				$highestX = $stones[$i][0];
			}
			if ($stones[$i][1] < $lowestY) {
				$lowestY = $stones[$i][1];
			}
			if ($stones[$i][1] > $highestY) {
				$highestY = $stones[$i][1];
			}
		}
		if (18 - $lowestX < $lowestX) {
			$stones = AppController::xFlip($stones);
		}
		if (18 - $lowestY < $lowestY) {
			$stones = AppController::yFlip($stones);
		}
		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++) {
			if ($stones[$i][0] > $highestX) {
				$highestX = $stones[$i][0];
			}
			if ($stones[$i][1] > $highestY) {
				$highestY = $stones[$i][1];
			}
			$board[$stones[$i][0]][$stones[$i][1]] = $stones[$i][2];
		}
		$tInfo = [];
		$tInfo[0] = $highestX;
		$tInfo[1] = $highestY;
		$arr = [];
		$arr[0] = $board;
		$arr[1] = $stones;
		$arr[2] = $tInfo;
		$arr[3] = $boardSize;

		return $arr;
	}

	public static function xFlip($stones) {
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++) {
			$stones[$i][0] = 18 - $stones[$i][0];
		}

		return $stones;
	}

	public static function yFlip($stones) {
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++) {
			$stones[$i][1] = 18 - $stones[$i][1];
		}

		return $stones;
	}

	public static function getInitialPositionEnd($pos, $sgfArr) {
		$endCondition = 0;
		$currentPos1 = $pos + 2;
		$currentPos2 = $pos + 5;
		while ($sgfArr[$currentPos1] == '[' && $sgfArr[$currentPos2] == ']') {
			$endCondition = $currentPos2;
			$currentPos1 += 4;
			$currentPos2 += 4;
		}

		return $endCondition;
	}

	public static function getInitialPosition($pos, $sgfArr, $color) {
		$arr = [];
		$end = AppController::getInitialPositionEnd($pos, $sgfArr);
		for ($i = $pos + 2; $i < $end; $i++) {
			if ($sgfArr[$i] != '[' && $sgfArr[$i] != ']') {
				array_push($arr, strtolower($sgfArr[$i]));
			}
		}
		$alphabet = array_flip(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z']);
		$xy = true;
		$arr2 = [];
		$c = 0;
		$arrCount = count($arr);
		for ($i = 0; $i < $arrCount; $i++) {
			$arr[$i] = $alphabet[$arr[$i]];
			if ($xy) {
				$arr2[$c] = [];
				$arr2[$c][0] = $arr[$i];
			} else {
				$arr2[$c][1] = $arr[$i];
				$arr2[$c][2] = $color;
				$c++;
			}
			$xy = !$xy;
		}

		return $arr2;
	}

	protected function getInvisibleSets() {
		$this->loadModel('Set');
		$invisibleSets = [];
		$in = $this->Set->find('all', ['conditions' => ['public' => 0]]);
		if (!$in) {
			$in = [];
		}
		foreach ($in as $item) {
			$invisibleSets[] = $item['Set']['id'];
		}

		return $invisibleSets;
	}

	protected function getDeletedSets() {
		$dSets = [];
		$de = $this->Set->find('all', ['conditions' => ['public' => -1]]);
		if (!$de) {
			$de = [];
		}
		foreach ($de as $item) {
			$dSets[] = $item['Set']['id'];
		}

		return $dSets;
	}

	/**
	 * @return void
	 */
	public static function startPageUpdate() {
		$str = '';
		$latest = ClassRegistry::init('AchievementStatus')->find('all', ['limit' => 7, 'order' => 'created DESC']);
		if (!$latest) {
			$latest = [];
		}
		$latestCount = count($latest);
		for ($i = 0; $i < $latestCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($latest[$i]['AchievementStatus']['achievement_id']);
			$u = ClassRegistry::init('User')->findById($latest[$i]['AchievementStatus']['user_id']);
			if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null) {
				$startPageUser = AppController::checkPicture($u);
			} else {
				$startPageUser = $u['User']['name'];
			}
			$latest[$i]['AchievementStatus']['name'] = $a['Achievement']['name'];
			$latest[$i]['AchievementStatus']['color'] = $a['Achievement']['color'];
			$latest[$i]['AchievementStatus']['image'] = $a['Achievement']['image'];
			$latest[$i]['AchievementStatus']['user'] = $startPageUser;
			$str .= '<div class="quote1"><div class="quote1a"><a href="/achievements/view/' . $a['Achievement']['id'] . '"><img src="/img/' . $a['Achievement']['image'] . '.png" width="34px"></a></div>';
			$str .= '<div class="quote1b">Achievement gained by ' . $startPageUser . ':<br><div class=""><b>' . $a['Achievement']['name'] . '</b></div></div></div>';
		}
		file_put_contents('mainPageAjax.txt', $str);
	}

	/**
	 * @return void
	 */
	protected function uotd() { //routine1
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementCondition');
		$today = date('Y-m-d');
		$ux2 = $this->User->find('all', [
			'limit' => '8',
			'order' => 'reuse3 DESC',
			'conditions' => [
				'NOT' => ['id' => [33]],
			],
		]);
		if (!$ux2) {
			$ux2 = [];
		}
		$last = $this->DayRecord->find('all', ['limit' => '7', 'order' => 'date DESC']);
		if (!$last) {
			$last = [];
		}
		$lastUotds = [];
		$lastUsers = [];
		foreach ($last as $item) {
			$lastUotds[] = $item['DayRecord']['user_id'];
		}
		foreach ($ux2 as $item) {
			$lastUsers[] = $item['User']['id'];
		}
		$resultUser = 72;
		$lastUsersCount = count($lastUsers);
		for ($i = 0; $i < $lastUsersCount; $i++) {
			$foundUser = false;
			$lastUotdsCount = count($lastUotds);
			for ($j = 0; $j < $lastUotdsCount; $j++) {
				if ($lastUsers[$i] == $lastUotds[$j]) {
					$foundUser = true;
				}
			}
			if (!$foundUser) {
				$resultUser = $lastUsers[$i];

				break;
			}
		}
		$ux = $this->User->findById($resultUser);

		$recentlyUsed = [];
		$d = 1;
		while ($d <= 10) {
			$ru = $this->DayRecord->find('first', ['conditions' => ['date' => date('Y-m-d', strtotime('-' . $d . ' days'))]]);
			if ($ru) {
				array_push($recentlyUsed, $ru);
			}
			$d++;
		}
		$currentQuote = 'q01';
		$newQuote = 'q01';
		$quoteChosen = false;
		while (!$quoteChosen) {
			$newQuote = rand(1, 45);
			if ($newQuote < 10) {
				$newQuote = 'q0' . $newQuote;
			} else {
				$newQuote = 'q' . $newQuote;
			}

			$f = false;
			$recentlyUsedCount = count($recentlyUsed);
			for ($i = 0; $i < $recentlyUsedCount; $i++) {
				if ($newQuote == $recentlyUsed[$i]['DayRecord']['quote']) {
					$f = true;
				}
			}
			if (!$f) {
				$quoteChosen = true;
			}
		}
		$currentQuote = $newQuote;
		$dayUserRand = 1;
		$uotdChosen = false;
		while (!$uotdChosen) {
			$dayUserRand = rand(1, 39);
			$f = false;
			$recentlyUsedCount = count($recentlyUsed);
			for ($i = 0; $i < $recentlyUsedCount; $i++) {
				if ($dayUserRand == $recentlyUsed[$i]['DayRecord']['userbg']) {
					$f = true;
				}
			}
			if (!$f) {
				$uotdChosen = true;
			}
		}
		$activity = $this->TsumegoAttempt->find('all', ['limit' => 40000, 'conditions' => ['DATE(TsumegoAttempt.created)' => date('Y-m-d', strtotime('yesterday'))]]);
		if (!$activity) {
			$activity = [];
		}
		$visitedProblems = count($activity);

		//how many users today
		$usersNum = [];
		$activity = $this->User->find('all', ['limit' => 400, 'order' => 'created DESC']);
		if (!$activity) {
			$activity = [];
		}
		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++) {
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today) {
				array_push($usersNum, $activity[$i]['User']);
			}
		}
		$gemRand1 = rand(0, 2);
		$gemRand2 = rand(0, 2);
		$gemRand3 = rand(0, 2);

		$arch1 = ClassRegistry::init('Achievement')->findById(111);
		if ($gemRand1 == 0) {
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on an easy ddk problem.';
		} elseif ($gemRand1 == 1) {
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on a regular ddk problem.';
		} elseif ($gemRand1 == 2) {
			$arch1['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult ddk problem.';
		}
		ClassRegistry::init('Achievement')->save($arch1);
		$arch2 = ClassRegistry::init('Achievement')->findById(112);
		if ($gemRand2 == 0) {
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on an easy sdk problem.';
		} elseif ($gemRand2 == 1) {
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on a regular sdk problem.';
		} elseif ($gemRand2 == 2) {
			$arch2['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult sdk problem.';
		}
		ClassRegistry::init('Achievement')->save($arch2);
		$arch3 = ClassRegistry::init('Achievement')->findById(113);
		if ($gemRand3 == 0) {
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on an easy dan problem.';
		} elseif ($gemRand3 == 1) {
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on a regular dan problem.';
		} elseif ($gemRand3 == 2) {
			$arch3['Achievement']['description'] = 'Has a chance to trigger once a day on a difficult dan problem.';
		}
		ClassRegistry::init('Achievement')->save($arch3);

		$this->DayRecord->create();
		$dateUser = [];
		$dateUser['DayRecord']['user_id'] = $ux['User']['id'];
		$dateUser['DayRecord']['date'] = $today;
		$dateUser['DayRecord']['solved'] = $ux['User']['reuse3'];
		$dateUser['DayRecord']['quote'] = $currentQuote;
		$dateUser['DayRecord']['userbg'] = $dayUserRand;
		$dateUser['DayRecord']['tsumego'] = $this->getTsumegoOfTheDay();
		$dateUser['DayRecord']['newTsumego'] = $this->getNewTsumego();
		$dateUser['DayRecord']['usercount'] = count($usersNum);
		$dateUser['DayRecord']['visitedproblems'] = $visitedProblems;
		$dateUser['DayRecord']['gems'] = $gemRand1 . '-' . $gemRand2 . '-' . $gemRand3;
		$dateUser['DayRecord']['gemCounter1'] = 0;
		$dateUser['DayRecord']['gemCounter2'] = 0;
		$dateUser['DayRecord']['gemCounter3'] = 0;
		$this->DayRecord->save($dateUser);

		ClassRegistry::init('AchievementCondition')->create();
		$achievementCondition = [];
		$achievementCondition['AchievementCondition']['user_id'] = $ux['User']['id'];
		$achievementCondition['AchievementCondition']['set_id'] = 0;
		$achievementCondition['AchievementCondition']['category'] = 'uotd';
		$achievementCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);

		//delete duplicated DayRecords
		$dr = $this->DayRecord->find('all');
		if (!$dr) {
			$dr = [];
		}
		$duplicates = [];
		$drCount = count($dr);
		for ($i = 0; $i < $drCount; $i++) {
			$alreadyFound = [];
			for ($j = 0; $j < $drCount; $j++) {
				if ($i != $j && $dr[$j]['DayRecord']['date'] == $dr[$i]['DayRecord']['date']) {
					$found = false;
					$alreadyFoundCount = count($alreadyFound);
					for ($k = 0; $k < $alreadyFoundCount; $k++) {
						if ($alreadyFound[$k]['DayRecord']['id'] == $dr[$i]['DayRecord']['id'] || $alreadyFound[$k]['DayRecord']['id'] == $dr[$j]['DayRecord']['id']) {
							$found = true;
						}
					}
					if (!$found) {
						array_push($duplicates, $dr[$i]['DayRecord']['date']);
						array_push($alreadyFound, $dr[$i]);
					}
				}
			}
		}
		$duplicates = array_count_values($duplicates);
		foreach ($duplicates as $key => $value) {
			while ($duplicates[$key] > 1) {
				$drd = $this->DayRecord->find('first', ['conditions' => ['date' => $key]]);
				if ($drd) {
					$this->DayRecord->delete($drd['DayRecord']['id']);
				}
				$duplicates[$key]--;
			}
		}
	}

	public static function deleteUnusedStatuses(int $uid): void {
		$s = ClassRegistry::init('Set')->find('all', [
			'conditions' => [
				'OR' => [
					['public' => 1],
					['public' => 0],
				],
			],
		]) ?: [];
		$ids = [];
		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++) {
			$tSet = TsumegoUtil::collectTsumegosFromSet($s[$i]['Set']['id']);
			foreach ($tSet as $item) {
				$ids[] = $item['Tsumego']['id'];
			}
		}
		$ut = ClassRegistry::init('TsumegoStatus')->find('all', [
			'conditions' => [
				'user_id' => $uid,
				'NOT' => [
					'tsumego_id' => $ids,
				],
			],
		]);
		if (!$ut) {
			$ut = [];
		}
		$utCount = count($ut);
		for ($i = 0; $i < $utCount; $i++) {
			$test1 = ClassRegistry::init('Tsumego')->findById($ut[$i]['TsumegoStatus']['tsumego_id']);
			$test2 = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $test1['Tsumego']['id']]]);
			if (!$test2) {
				ClassRegistry::init('TsumegoStatus')->delete($ut[$i]['TsumegoStatus']['id']);

				continue;
			}
			$test3 = ClassRegistry::init('Set')->find('first', [
				'id' => $test2['SetConnection']['set_id'],
				'OR' => [
					['public' => 1],
					['public' => 0],
				],
			]);
			if ($test3 == null) {
				ClassRegistry::init('TsumegoStatus')->delete($ut[$i]['TsumegoStatus']['id']);
			}
		}
	}

	protected function saveSolvedNumber($uid) {
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$solvedUts2 = 0;
		$tsumegos = $this->SetConnection->find('all');
		if (!$tsumegos) {
			$tsumegos = [];
		}
		$uts = $this->TsumegoStatus->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
		if (!$uts) {
			$uts = [];
		}
		$setKeys = [];
		$setArray = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		if (!$setArray) {
			$setArray = [];
		}

		$setArrayCount = count($setArray);
		for ($i = 0; $i < $setArrayCount; $i++) {
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];
		}

		$scs = [];
		$tsumegosCount = count($tsumegos);
		for ($j = 0; $j < $tsumegosCount; $j++) {
			if (!isset($scs[$tsumegos[$j]['SetConnection']['tsumego_id']])) {
				$scs[$tsumegos[$j]['SetConnection']['tsumego_id']] = 1;
			} else {
				$scs[$tsumegos[$j]['SetConnection']['tsumego_id']]++;
			}
		}
		$utsCount = count($uts);
		for ($j = 0; $j < $utsCount; $j++) {
			if ($uts[$j]['TsumegoStatus']['status'] == 'S' || $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C') {
				if (isset($scs[$uts[$j]['TsumegoStatus']['tsumego_id']])) {
					$solvedUts2 += $scs[$uts[$j]['TsumegoStatus']['tsumego_id']];
				}
			}
		}
		Auth::getUser()['solved'] = $solvedUts2;
		Auth::saveUser();

		return $solvedUts2;
	}

	/**
	 * @return void
	 */
	protected function resetUserElos() {
		$this->loadModel('User');

		$u = $this->User->find('all', [
			'conditions' => [
				'id >=' => 15000,
				'id <=' => 19000,
			],
		]);
		if (!$u) {
			$u = [];
		}

		$uCount = count($u);
		for ($i = 0; $i < $uCount; $i++) {
			$u[$i]['User']['rating'] = 900;
			$u[$i]['User']['solved2'] = 0;
			$this->User->save($u[$i]);
		}
	}

	/**
	 * @param int $uid User ID
	 * @param string $action Action type
	 *
	 * @return void
	 */
	public static function handleContribution($uid, $action) {
		$uc = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null) {
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['added_tag'] = 0;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			ClassRegistry::init('UserContribution')->create();
		}
		$uc['UserContribution'][$action] += 1;
		$uc['UserContribution']['score']
		= $uc['UserContribution']['added_tag']
		+ $uc['UserContribution']['created_tag'] * 3
		+ $uc['UserContribution']['made_proposal'] * 5
		+ $uc['UserContribution']['reviewed'] * 2;
		ClassRegistry::init('UserContribution')->save($uc);
	}

	public static function getAllTags($not) {
		$a = [];
		$notApproved = ClassRegistry::init('TagName')->find('all', ['conditions' => ['approved' => 0]]);
		if (!$notApproved) {
			$notApproved = [];
		}
		$notCount = count($not);
		for ($i = 0; $i < $notCount; $i++) {
			array_push($a, $not[$i]['Tag']['tag_name_id']);
		}
		$notApprovedCount = count($notApproved);
		for ($i = 0; $i < $notApprovedCount; $i++) {
			array_push($a, $notApproved[$i]['TagName']['id']);
		}
		$tn = ClassRegistry::init('TagName')->find('all', [
			'conditions' => [
				'NOT' => ['id' => $a],
			],
		]);
		if (!$tn) {
			$tn = [];
		}
		$sorted = [];
		$keys = [];
		$tnCount = count($tn);
		for ($i = 0; $i < $tnCount; $i++) {
			array_push($sorted, $tn[$i]['TagName']['name']);
			$keys[$tn[$i]['TagName']['name']] = $tn[$i];
		}
		sort($sorted);
		$s2 = [];
		$sortedCount = count($sorted);
		for ($i = 0; $i < $sortedCount; $i++) {
			array_push($s2, $keys[$sorted[$i]]);
		}

		return $s2;
	}

	/**
	 * @return void
	 */
	protected function deleteUserBoards() {
		$this->loadModel('UserBoard');
		$this->UserBoard->deleteAll(['1 = 1']);
	}

	/**
	 * @return void
	 */
	protected function halfXP() {
		$this->loadModel('TsumegoStatus');
		$this->loadModel('DayRecord');
		$week = $this->TsumegoStatus->find('all', ['order' => 'created DESC', 'conditions' => ['status' => 'S']]);
		if (!$week) {
			$week = [];
		}
		$oneWeek = date('Y-m-d H:i:s', strtotime('-7 days'));
		$weekCount = count($week);
		for ($i = 0; $i < $weekCount; $i++) {
			if ($week[$i]['TsumegoStatus']['created'] < $oneWeek) {
				if ($week[$i]['TsumegoStatus']['status'] == 'S') {
					$week[$i]['TsumegoStatus']['status'] = 'W';
					//$this->TsumegoStatus->save($week[$i]);
				}
			}
		}
	}

	protected function getNewTsumego() {
		$this->loadModel('Schedule');
		$date = date('Y-m-d', strtotime('today'));
		$s = $this->Schedule->find('all', ['conditions' => ['date' => $date]]);
		if (!$s) {
			$s = [];
		}
		$id = 0;
		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++) {
			$id = $this->publishSingle($s[$i]['Schedule']['tsumego_id'], $s[$i]['Schedule']['set_id'], $s[$i]['Schedule']['date']);
			$s[$i]['Schedule']['tsumego_id'] = $id;
			$s[$i]['Schedule']['published'] = 1;
			$this->Schedule->save($s[$i]);
		}

		return $id;
	}

	protected function publishSingle($t = null, $to = null, $date = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('PublishDate');
		$ts = $this->Tsumego->findById($t);

		$id = $this->Tsumego->find('first', ['limit' => 1, 'order' => 'id DESC']);
		if (!$id) {
			return null;
		}
		$id = $id['Tsumego']['id'];
		$id += 1;

		$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts['Tsumego']['id']]]);
		if ($scT != null) {
			$scT['SetConnection']['set_id'] = $to;
			$scT['SetConnection']['tsumego_id'] = $id;
			$scT['SetConnection']['num'] = $ts['Tsumego']['num'];
			$this->SetConnection->save($scT);
		} else {
			$scT = [];
			$scT['SetConnection']['set_id'] = $to;
			$scT['SetConnection']['tsumego_id'] = $id;
			$scT['SetConnection']['num'] = $ts['Tsumego']['num'];
			$this->SetConnection->create();
			$this->SetConnection->save($scT);
		}

		$sid = $ts['Tsumego']['id'];
		$ts['Tsumego']['id'] = $id;
		$ts['Tsumego']['created'] = $date . ' 22:00:00';
		$ts['Tsumego']['solved'] = 0;
		$ts['Tsumego']['failed'] = 0;
		$ts['Tsumego']['userWin'] = 0;
		$ts['Tsumego']['userLoss'] = 0;
		$this->Tsumego->create();
		$this->Tsumego->save($ts);
		$this->Tsumego->delete($sid);

		$sgfs = $this->Sgf->find('all', ['conditions' => ['tsumego_id' => $t]]);
		if (!$sgfs) {
			$sgfs = [];
		}
		$sgfsCount = count($sgfs);
		for ($i = 0; $i < $sgfsCount; $i++) {
			$sgfs[$i]['Sgf']['tsumego_id'] = $id;
			$this->Sgf->save($sgfs[$i]);
		}
		$x = [];
		$x['PublishDate']['date'] = $date . ' 22:00:00';
		$x['PublishDate']['tsumego_id'] = $id;
		$this->PublishDate->create();
		$this->PublishDate->save($x);

		return $id;
	}

	protected function getTsumegoOfTheDay() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Schedule');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');

		$ut = $this->TsumegoRatingAttempt->find('all', ['limit' => 10000, 'order' => 'created DESC', 'conditions' => ['status' => 'S']]);
		if (!$ut) {
			$ut = [];
		}
		$out = $this->TsumegoAttempt->find('all', ['limit' => 30000, 'order' => 'created DESC', 'conditions' => ['gain >=' => 40]]);
		if (!$out) {
			$out = [];
		}

		$date = date('Y-m-d', strtotime('yesterday'));
		$s = $this->Schedule->find('all', ['conditions' => ['date' => $date]]);
		if (!$s) {
			$s = [];
		}
		$ids = [];
		$utCount = count($ut);
		for ($i = 0; $i < $utCount; $i++) {
			$date2 = new DateTime($ut[$i]['TsumegoRatingAttempt']['created']);
			$date2 = $date2->format('Y-m-d');
			if ($date === $date2) {
				array_push($ids, $ut[$i]['TsumegoRatingAttempt']['tsumego_id']);
			}
		}
		$ids = array_count_values($ids);
		$highest = 0;
		$best = [];
		foreach ($ids as $key => $value) {
			if ($value > $highest) {
				$highest = $value;
			}
		}
		foreach ($ids as $key => $value) {
			if ($value == $highest) {
				$x = [];
				$x[$key] = $value;
				array_push($best, $x);
			}
		}
		$ids2 = [];
		$out2 = [];
		$outCount = count($out);
		for ($i = 0; $i < $outCount; $i++) {
			$date2 = new DateTime($out[$i]['TsumegoAttempt']['created']);
			$date2 = $date2->format('Y-m-d');
			if ($date === $date2) {
				array_push($ids2, $out[$i]['TsumegoAttempt']['tsumego_id']);
				array_push($out2, $out[$i]);
			}
		}
		$ids2 = array_count_values($ids2);
		$highest = 0;
		$best2 = [];
		foreach ($ids2 as $key => $value) {
			if ($value > $highest) {
				$highest = $value;
			}
		}
		$done = false;
		$found = 0;
		$decrement = 0;
		$best3 = [];
		$findNum = 20;
		while (!$done) {
			foreach ($ids2 as $key => $value) {
				if ($value == $highest - $decrement) {
					array_push($best2, $key);
					array_push($best3, $value);
					$found++;
				}
			}
			$decrement++;
			if ($found < $findNum) {
				$done = false;
			} else {
				$done = true;
			}
		}
		$newBest = [];
		for ($j = 0; $j < $findNum; $j++) {
			$newBest[$j] = [];
		}
		$out2Count = count($out2);
		for ($i = 0; $i < $out2Count; $i++) {
			for ($j = 0; $j < $findNum; $j++) {
				if ($out2[$i]['TsumegoAttempt']['tsumego_id'] == $best2[$j]) {
					$x = [];
					$x['tid'] = $out2[$i]['TsumegoAttempt']['tsumego_id'];
					$tx = $this->Tsumego->findById($x['tid']);
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
					$tx['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$x['sid'] = $tx['Tsumego']['set_id'];
					$x['status'] = $out2[$i]['TsumegoAttempt']['solved'];
					$x['seconds'] = $out2[$i]['TsumegoAttempt']['seconds'];

					$newBest[$j][] = $x;
				}
			}
		}
		$newBestCount = count($newBest);
		for ($i = 0; $i < $newBestCount; $i++) {
			$sum = 0;
			$newBestICount = count($newBest[$i]);
			for ($j = 0; $j < $newBestICount; $j++) {
				if ($newBest[$i][$j]['seconds'] != null) {
					if ($newBest[$i][$j]['seconds'] > 300) {
						$newBest[$i][$j]['seconds'] = 300;
					}
					$sum += $newBest[$i][$j]['seconds'];
				}
			}
			$sum = $sum * count($newBest[$i]);
			$newBest[$i]['sum'] = $sum;
		}
		$highest = 0;
		$hid = 0;
		$newBestCount = count($newBest);
		for ($i = 0; $i < $newBestCount; $i++) {
			if ($newBest[$i]['sum'] > $highest && $newBest[$i][0]['sid'] != 104 && $newBest[$i][0]['sid'] != 105 && $newBest[$i][0]['sid'] != 117) {
				$yesterday = false;
				$sCount = count($s);
				for ($j = 0; $j < $sCount; $j++) {
					if ($newBest[$i][0]['tid'] == $s[$j]['Schedule']['tsumego_id']) {
						$yesterday = true;
					}
				}
				if (!$yesterday) {
					$highest = $newBest[$i]['sum'];
					$hid = $i;
				}
			}
		}

		return $newBest[$hid][0]['tid'];
	}

	protected function ratingMatch($elo) {
		if ($elo >= 3000) {
			$td = 10;//10d
		} elseif ($elo >= 2900) {
			$td = 10;//9d
		} elseif ($elo >= 2800) {
			$td = 10;//8d
		} elseif ($elo >= 2700) {
			$td = 10;//7d x2700
		} elseif ($elo >= 2600) {
			$td = 9;//6d x2600
		} elseif ($elo >= 2500) {
			$td = 8;//5d x2500
		} elseif ($elo >= 2400) {
			$td = 7;//4d
		} elseif ($elo >= 2300) {
			$td = 7;//3d x2350
		} elseif ($elo >= 2200) {
			$td = 6;//2d
		} elseif ($elo >= 2100) {
			$td = 6;//1d x2150
		} elseif ($elo >= 2000) {
			$td = 5;//1k
		} elseif ($elo >= 1900) {
			$td = 5;//2k x1950
		} elseif ($elo >= 1800) {
			$td = 4;//3k
		} elseif ($elo >= 1700) {
			$td = 4;//4k x1750
		} elseif ($elo >= 1600) {
			$td = 3;//5k
		} elseif ($elo >= 1500) {
			$td = 3;//6k x1500
		} elseif ($elo >= 1400) {
			$td = 3;//7k
		} elseif ($elo >= 1300) {
			$td = 2;//8k
		} elseif ($elo >= 1200) {
			$td = 2;//9k x1200
		} elseif ($elo >= 1100) {
			$td = 2;//10k
		} elseif ($elo >= 1000) {
			$td = 1;//11k
		} elseif ($elo >= 900) {
			$td = 1;//12k x900
		} elseif ($elo >= 800) {
			$td = 1;//13k
		} elseif ($elo >= 700) {
			$td = 1;//14k
		} elseif ($elo >= 600) {
			$td = 1;//15k
		} elseif ($elo >= 500) {
			$td = 1;//16k
		} elseif ($elo >= 400) {
			$td = 1;//17k
		} elseif ($elo >= 300) {
			$td = 1;//18k
		} elseif ($elo >= 200) {
			$td = 1;//19k
		} elseif ($elo >= 100) {
			$td = 1;//20k
		} else {
			$td = 1;
		}

		return $td;
	}

	protected function rating2($d) {
		if ($d == 10) {
			$elo = 2700;
		} elseif ($d == 9) {
			$elo = 2600;
		} elseif ($d == 8) {
			$elo = 2500;
		} elseif ($d == 7) {
			$elo = 2350;
		} elseif ($d == 6) {
			$elo = 2150;
		} elseif ($d == 5) {
			$elo = 1950;
		} elseif ($d == 4) {
			$elo = 1750;
		} elseif ($d == 3) {
			$elo = 1500;
		} elseif ($d == 2) {
			$elo = 1200;
		} elseif ($d == 1) {
			$elo = 900;
		} else {
			$elo = 1500;
		}

		return $elo;
	}

	public static function encrypt($str = null) {
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		return base64_encode(openssl_encrypt($str, $encrypt_method, $key, 0, $iv));
	}

	public static function decrypt($str = null) {
		$string = $str;
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		return openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
	}

	protected function checkPictureLarge($u) {
		if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null) {
			return '<img class="google-profile-image-large" src="/img/google/' . $u['User']['picture'] . '">' . substr($u['User']['name'], 3);
		}

		return $u['User']['name'];
	}
	public static function checkPicture($user) {
		if (substr($user['name'], 0, 3) == 'g__' && $user['external_id'] != null) {
			return '<img class="google-profile-image" src="/img/google/' . $user['picture'] . '">' . substr($user['name'], 3);
		}

		return $user['name'];
	}

	public static function getTsumegoRankx($t) {
		if ($t <= 0) {
			return '15k';
		}
		if ($t > 0 && $t <= 22) {
			$tRank = '5d';
		} elseif ($t <= 26.5) {
			$tRank = '4d';
		} elseif ($t <= 30) {
			$tRank = '3d';
		} elseif ($t <= 34) {
			$tRank = '2d';
		} elseif ($t <= 38) {
			$tRank = '1d';
		} elseif ($t <= 42) {
			$tRank = '1k';
		} elseif ($t <= 46) {
			$tRank = '2k';
		} elseif ($t <= 50) {
			$tRank = '3k';
		} elseif ($t <= 54.5) {
			$tRank = '4k';
		} elseif ($t <= 58.5) {
			$tRank = '5k';
		} elseif ($t <= 63) {
			$tRank = '6k';
		} elseif ($t <= 67) {
			$tRank = '7k';
		} elseif ($t <= 70.8) {
			$tRank = '8k';
		} elseif ($t <= 74.8) {
			$tRank = '9k';
		} elseif ($t <= 79) {
			$tRank = '10k';
		} elseif ($t <= 83.5) {
			$tRank = '11k';
		} elseif ($t <= 88) {
			$tRank = '12k';
		} elseif ($t <= 92) {
			$tRank = '13k';
		} elseif ($t <= 96) {
			$tRank = '14k';
		} else {
			$tRank = '15k';
		}

		return $tRank;
	}
	protected function adjustElo($v) {
		$add = 0;
		if ($v >= 1700) {
			$add = 110;
		} elseif ($v >= 1600) {
			$add = 110;
		} elseif ($v >= 1700) {
			$add = 100;
		} elseif ($v >= 1600) {
			$add = 90;
		} elseif ($v >= 1500) {
			$add = 80;
		} elseif ($v >= 1400) {
			$add = 70;
		} elseif ($v >= 1300) {
			$add = 60;
		} elseif ($v >= 1200) {
			$add = 60;
		} elseif ($v >= 1100) {
			$add = 50;
		} elseif ($v >= 1000) {
			$add = 50;
		} else {
			$add = 0;
		}

		return $v + $add;
	}

	public static function getTsumegoElo($rank, $p = null) {
		if ($p != null) {
			$p *= 100;
		} else {
			$p = 0;
		}
		$elo = 600;
		if ($rank == '9d') {
			$elo = round(2900 + $p);
		} elseif ($rank == '8d') {
			$elo = round(2800 + $p);
		} elseif ($rank == '7d') {
			$elo = round(2700 + $p);
		} elseif ($rank == '6d') {
			$elo = round(2600 + $p);
		} elseif ($rank == '5d') {
			$elo = round(2500 + $p);
		} elseif ($rank == '4d') {
			$elo = round(2400 + $p);
		} elseif ($rank == '3d') {
			$elo = round(2300 + $p);
		} elseif ($rank == '2d') {
			$elo = round(2200 + $p);
		} elseif ($rank == '1d') {
			$elo = round(2100 + $p);
		} elseif ($rank == '1k') {
			$elo = round(2000 + $p);
		} elseif ($rank == '2k') {
			$elo = round(1900 + $p);
		} elseif ($rank == '3k') {
			$elo = round(1800 + $p);
		} elseif ($rank == '4k') {
			$elo = round(1700 + $p);
		} elseif ($rank == '5k') {
			$elo = round(1600 + $p);
		} elseif ($rank == '6k') {
			$elo = round(1500 + $p);
		} elseif ($rank == '7k') {
			$elo = round(1400 + $p);
		} elseif ($rank == '8k') {
			$elo = round(1300 + $p);
		} elseif ($rank == '9k') {
			$elo = round(1200 + $p);
		} elseif ($rank == '10k') {
			$elo = round(1100 + $p);
		} elseif ($rank == '11k') {
			$elo = round(1000 + $p);
		} elseif ($rank == '12k') {
			$elo = round(900 + $p);
		} elseif ($rank == '13k') {
			$elo = round(800 + $p);
		} elseif ($rank == '14k') {
			$elo = round(700 + $p);
		} elseif ($rank == '15k' || $rank == '16k' || $rank == '17k' || $rank == '18k' || $rank == '19k' || $rank == '20k' || $rank == '21k') {
			$elo = round(600 + $p);
		}

		return $elo;
	}
	protected function getTsumegoRankVal($t) {
		if ($t <= 0) {
			return 0;
		}
		if ($t > 0 && $t <= 22) {
			return 22 - $t;
		}
		if ($t <= 26.5) {
			return 26.5 - $t;
		}
		if ($t <= 30) {
			return 30 - $t;
		}
		if ($t <= 34) {
			return 34 - $t;
		}
		if ($t <= 38) {
			return 38 - $t;
		}
		if ($t <= 42) {
			return 42 - $t;
		}
		if ($t <= 46) {
			return 46 - $t;
		}
		if ($t <= 50) {
			return 50 - $t;
		}
		if ($t <= 54.5) {
			return 54.5 - $t;
		}
		if ($t <= 58.5) {
			return 58.5 - $t;
		}
		if ($t <= 63) {
			return 63 - $t;
		}
		if ($t <= 67) {
			return 67 - $t;
		}
		if ($t <= 70.8) {
			return 70.8 - $t;
		}
		if ($t <= 74.8) {
			return 74.8 - $t;
		}
		if ($t <= 79) {
			return 79 - $t;
		}
		if ($t <= 83.5) {
			return 83.5 - $t;
		}
		if ($t <= 88) {
			return 88 - $t;
		}
		if ($t <= 92) {
			return 92 - $t;
		}
		if ($t <= 96) {
			return 96 - $t;
		}

		return 100 - $t;
	}
	protected function getTsumegoRankMax($t) {
		if ($t <= 0) {
			return 100 - 96;
		}
		if ($t > 0 && $t <= 22) {
			return 22;
		}
		if ($t <= 26.5) {
			return 26.5 - 22;
		}
		if ($t <= 30) {
			return 30 - 26.5;
		}
		if ($t <= 34) {
			return 34 - 30;
		}
		if ($t <= 38) {
			return 38 - 34;
		}
		if ($t <= 42) {
			return 42 - 38;
		}
		if ($t <= 46) {
			return 46 - 42;
		}
		if ($t <= 50) {
			return 50 - 46;
		}
		if ($t <= 54.5) {
			return 54.5 - 50;
		}
		if ($t <= 58.5) {
			return 58.5 - 54.5;
		}
		if ($t <= 63) {
			return 63 - 58.5;
		}
		if ($t <= 67) {
			return 67 - 63;
		}
		if ($t <= 70.8) {
			return 70.8 - 67;
		}
		if ($t <= 74.8) {
			return 74.8 - 70.8;
		}
		if ($t <= 79) {
			return 79 - 74.8;
		}
		if ($t <= 83.5) {
			return 83.5 - 79;
		}
		if ($t <= 88) {
			return 88 - 83.5;
		}
		if ($t <= 92) {
			return 92 - 88;
		}
		if ($t <= 96) {
			return 96 - 92;
		}

		return 100 - 96;
	}

	public static function saveDanSolveCondition($solvedTsumegoRank, $tId): void {
		if ($solvedTsumegoRank == '1d' || $solvedTsumegoRank == '2d' || $solvedTsumegoRank == '3d' || $solvedTsumegoRank == '4d' || $solvedTsumegoRank == '5d') {
			$danSolveCategory = 'danSolve' . $solvedTsumegoRank;
			$danSolveCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => $danSolveCategory,
				],
			]);
			if (!$danSolveCondition) {
				$danSolveCondition = [];
				$danSolveCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$danSolveCondition['AchievementCondition']['category'] = $danSolveCategory;
			$danSolveCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			$danSolveCondition['AchievementCondition']['set_id'] = $tId;
			$danSolveCondition['AchievementCondition']['value']++;

			ClassRegistry::init('AchievementCondition')->save($danSolveCondition);
		}
	}

	public static function updateSprintCondition(bool $trigger = false): void {
		if (Auth::isLoggedIn()) {
			$sprintCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'sprint',
				],
			]);
			if (!$sprintCondition) {
				$sprintCondition = [];
				$sprintCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$sprintCondition['AchievementCondition']['category'] = 'sprint';
			$sprintCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			if ($trigger) {
				$sprintCondition['AchievementCondition']['value']++;
			} else {
				$sprintCondition['AchievementCondition']['value'] = 0;
			}
			ClassRegistry::init('AchievementCondition')->save($sprintCondition);
		}
	}

	public static function updateGoldenCondition(bool $trigger = false): void {
		$goldenCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'golden',
			],
		]);
		if (!$goldenCondition) {
			$goldenCondition = [];
			$goldenCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->create();
		}
		$goldenCondition['AchievementCondition']['category'] = 'golden';
		$goldenCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		if ($trigger) {
			$goldenCondition['AchievementCondition']['value']++;
		} else {
			$goldenCondition['AchievementCondition']['value'] = 0;
		}
		ClassRegistry::init('AchievementCondition')->save($goldenCondition);
	}

	public static function setPotionCondition(): void {
		$potionCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'potion',
			],
		]);
		if (!$potionCondition) {
			$potionCondition = [];
			ClassRegistry::init('AchievementCondition')->create();
		}
		$potionCondition['AchievementCondition']['category'] = 'potion';
		$potionCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		$potionCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($potionCondition);
	}

	public static function updateGems(string $rank): void {
		$datex = new DateTime('today');
		$dateGem = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => $datex->format('Y-m-d')]]);
		if ($dateGem != null) {
			$gems = explode('-', $dateGem['DayRecord']['gems']);
			$gemValue = '';
			$gemValue2 = '';
			$gemValue3 = '';
			$condition1 = 500;
			$condition2 = 200;
			$condition3 = 5;
			$found1 = false;
			$found2 = false;
			$found3 = false;
			if ($rank == '15k' || $rank == '14k' || $rank == '13k' || $rank == '12k' || $rank == '11k' || $rank == '10k') {
				if ($gems[0] == 0) {
					$gemValue = '15k';
				} elseif ($gems[0] == 1) {
					$gemValue = '12k';
				} elseif ($gems[0] == 2) {
					$gemValue = '10k';
				}
				if ($rank == $gemValue) {
					$dateGem['DayRecord']['gemCounter1']++;
					if ($dateGem['DayRecord']['gemCounter1'] == $condition1) {
						$found1 = true;
					}
				}
			} elseif ($rank == '9k' || $rank == '8k' || $rank == '7k' || $rank == '6k' || $rank == '5k' || $rank == '4k' || $rank == '3k' || $rank == '2k' || $rank == '1k') {
				if ($gems[1] == 0) {
					$gemValue = '9k';
					$gemValue2 = 'x';
					$gemValue3 = 'y';
				} elseif ($gems[1] == 1) {
					$gemValue = '6k';
					$gemValue2 = '5k';
					$gemValue3 = '4k';
				} elseif ($gems[1] == 2) {
					$gemValue = 'x';
					$gemValue2 = '2k';
					$gemValue3 = '1k';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3) {
					$dateGem['DayRecord']['gemCounter2']++;
					if ($dateGem['DayRecord']['gemCounter2'] == $condition2) {
						$found2 = true;
					}
				}
			} elseif ($rank == '1d' || $rank == '2d' || $rank == '3d' || $rank == '4d' || $rank == '5d' || $rank == '6d' || $rank == '7d') {
				if ($gems[2] == 0) {
					$gemValue = '1d';
					$gemValue2 = '2d';
					$gemValue3 = '3d';
				} elseif ($gems[2] == 1) {
					$gemValue = '2d';
					$gemValue2 = '3d';
					$gemValue3 = '4d';
				} elseif ($gems[2] == 2) {
					$gemValue = '5d';
					$gemValue2 = '6d';
					$gemValue3 = '7d';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3) {
					$dateGem['DayRecord']['gemCounter3']++;
					if ($dateGem['DayRecord']['gemCounter3'] == $condition3) {
						$found3 = true;
					}
				}
			}
			if ($found1) {
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'emerald',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'emerald';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				} else {
					$dateGem['DayRecord']['gemCounter1']--;
				}
			} elseif ($found2) {
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'sapphire',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'sapphire';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				} else {
					$dateGem['DayRecord']['gemCounter2']--;
				}
			} elseif ($found3) {
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'ruby',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'ruby';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				} else {
					$dateGem['DayRecord']['gemCounter3']--;
				}
			}
		}
		ClassRegistry::init('DayRecord')->save($dateGem);
	}

	public static function checkProblemNumberAchievements() {
		if (!Auth::isLoggedIn()) {
			return;
		}

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 1;
		$solvedCount = Auth::getUser()['solved'];
		if ($solvedCount >= 1000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 2;
		if ($solvedCount >= 2000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 3;
		if ($solvedCount >= 3000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 4;
		if ($solvedCount >= 4000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 5;
		if ($solvedCount >= 5000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 6;
		if ($solvedCount >= 6000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 7;
		if ($solvedCount >= 7000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 8;
		if ($solvedCount >= 8000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 9;
		if ($solvedCount >= 9000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 10;
		if ($solvedCount >= 10000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		//uotd achievement
		$achievementId = 11;
		if (!isset($existingAs[$achievementId])) {
			$condition = ClassRegistry::init('AchievementCondition')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => 'uotd']]);
			if ($condition != null) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
		}

		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkDanSolveAchievements() {
		if (Auth::isLoggedIn()) {
			$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$buffer) {
				$buffer = [];
			}
			$ac = ClassRegistry::init('AchievementCondition')->find('all', [
				'order' => 'category ASC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'OR' => [
						['category' => 'danSolve1d'],
						['category' => 'danSolve2d'],
						['category' => 'danSolve3d'],
						['category' => 'danSolve4d'],
						['category' => 'danSolve5d'],
						['category' => 'emerald'],
						['category' => 'sapphire'],
						['category' => 'ruby'],
						['category' => 'sprint'],
						['category' => 'golden'],
						['category' => 'potion'],
					],
				],
			]);
			if (!$ac) {
				$ac = [];
			}
			$ac1 = [];
			$acCount = count($ac);
			for ($i = 0; $i < $acCount; $i++) {
				if ($ac[$i]['AchievementCondition']['category'] == 'danSolve1d') {
					$ac1['1d'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve2d') {
					$ac1['2d'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve3d') {
					$ac1['3d'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve4d') {
					$ac1['4d'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve5d') {
					$ac1['5d'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'emerald') {
					$ac1['emerald'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'sapphire') {
					$ac1['sapphire'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'ruby') {
					$ac1['ruby'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'sprint') {
					$ac1['sprint'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'golden') {
					$ac1['golden'] = $ac[$i]['AchievementCondition']['value'];
				} elseif ($ac[$i]['AchievementCondition']['category'] == 'potion') {
					$ac1['potion'] = $ac[$i]['AchievementCondition']['value'];
				}
			}

			$existingAs = [];
			$bufferCount = count($buffer);
			for ($i = 0; $i < $bufferCount; $i++) {
				$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
			}
			$as = [];
			$as['AchievementStatus']['user_id'] = Auth::getUserID();
			$updated = [];
			$achievementId = 101;
			if ($ac1['1d'] > 0 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 102;
			if ($ac1['2d'] > 0 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 103;
			if ($ac1['3d'] > 0 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 104;
			if ($ac1['4d'] > 0 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 105;
			if ($ac1['5d'] > 0 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 106;
			if ($ac1['1d'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 107;
			if ($ac1['2d'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 108;
			if ($ac1['3d'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 109;
			if ($ac1['4d'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 110;
			if ($ac1['5d'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 111;
			if (isset($ac1['emerald'])) {
				if ($ac1['emerald'] == 1 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			$achievementId = 112;
			if (isset($ac1['sapphire'])) {
				if ($ac1['sapphire'] == 1 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			$achievementId = 113;
			if (isset($ac1['ruby'])) {
				if ($ac1['ruby'] == 1 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			$achievementId = 114;
			if (!isset($existingAs[$achievementId]) && isset($existingAs[111]) && isset($existingAs[112]) && isset($existingAs[113])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 96;
			if (!isset($existingAs[$achievementId]) && $ac1['sprint'] >= 30) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 97;
			if (!isset($existingAs[$achievementId]) && $ac1['golden'] >= 10) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 98;
			if (!isset($existingAs[$achievementId]) && $ac1['potion'] >= 1) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$updatedCount = count($updated);
			for ($i = 0; $i < $updatedCount; $i++) {
				$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
				$updated[$i] = [];
				$updated[$i][0] = $a['Achievement']['name'];
				$updated[$i][1] = $a['Achievement']['description'];
				$updated[$i][2] = $a['Achievement']['image'];
				$updated[$i][3] = $a['Achievement']['color'];
				$updated[$i][4] = $a['Achievement']['xp'];
				$updated[$i][5] = $a['Achievement']['id'];
			}

			return $updated;
		}
	}

	protected function checkForLocked($t, $setsWithPremium) {
		$scCheck = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
		if ($scCheck && in_array($scCheck['SetConnection']['set_id'], $setsWithPremium) && !Auth::hasPremium()) {
			$t['Tsumego']['locked'] = true;
		} else {
			$t['Tsumego']['locked'] = false;
		}

		return $t;
	}
	public static function checkNoErrorAchievements() {
		if (Auth::isLoggedIn()) {

			$ac = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'err',
				],
			]);

			$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$buffer) {
				$buffer = [];
			}
			$existingAs = [];
			$bufferCount = count($buffer);
			for ($i = 0; $i < $bufferCount; $i++) {
				$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
			}
			$as = [];
			$as['AchievementStatus']['user_id'] = Auth::getUserID();
			$updated = [];

			$achievementId = 53;
			if ($ac['AchievementCondition']['value'] >= 10 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 54;
			if ($ac['AchievementCondition']['value'] >= 20 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 55;
			if ($ac['AchievementCondition']['value'] >= 30 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 56;
			if ($ac['AchievementCondition']['value'] >= 50 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 57;
			if ($ac['AchievementCondition']['value'] >= 100 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 58;
			if ($ac['AchievementCondition']['value'] >= 200 && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$updatedCount = count($updated);
			for ($i = 0; $i < $updatedCount; $i++) {
				$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
				$updated[$i] = [];
				$updated[$i][0] = $a['Achievement']['name'];
				$updated[$i][1] = $a['Achievement']['description'];
				$updated[$i][2] = $a['Achievement']['image'];
				$updated[$i][3] = $a['Achievement']['color'];
				$updated[$i][4] = $a['Achievement']['xp'];
				$updated[$i][5] = $a['Achievement']['id'];
			}

			return $updated;
		}
	}

	protected function checkTimeModeAchievements() {
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('TimeModeSession');

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$rBlitz = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_BLITZ, 'user_id' => Auth::getUserID()]]);
		if (!$rBlitz) {
			$rBlitz = [];
		}
		$rFast = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_FAST_SPEED, 'user_id' => Auth::getUserID()]]);
		if (!$rFast) {
			$rFast = [];
		}
		$rSlow = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_SLOW_SPEED, 'user_id' => Auth::getUserID()]]);
		if (!$rSlow) {
			$rSlow = [];
		}
		$r = $this->TimeModeSession->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$r) {
			$r = [];
		}

		$timeModeAchievements = [];
		for ($i = 70; $i <= 91; $i++) {
			$timeModeAchievements[$i] = false;
		}
		$rCount = count($r);
		for ($i = 0; $i < $rCount; $i++) {
			if ($r[$i]['TimeModeSession']['status'] == 's') {
				if ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[70] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[76] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[82] = true;
					}
				} elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[71] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[77] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[83] = true;
					}
				} elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[72] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[78] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[84] = true;
					}
				} elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[73] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[79] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[85] = true;
					}
				} elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[74] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[80] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[86] = true;
					}
				} elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d') {
					if ($r[$i]['TimeModeSession']['mode'] == 2) {
						$timeModeAchievements[75] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 1) {
						$timeModeAchievements[81] = true;
					} elseif ($r[$i]['TimeModeSession']['mode'] == 0) {
						$timeModeAchievements[87] = true;
					}
				}
			}
			if ($r[$i]['TimeModeSession']['points'] >= 850
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d')) {
				$timeModeAchievements[91] = true;
			}
			if ($r[$i]['TimeModeSession']['points'] >= 875
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k')) {
				$timeModeAchievements[90] = true;
			}
			if ($r[$i]['TimeModeSession']['points'] >= 900
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '7k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '8k')) {
				$timeModeAchievements[89] = true;
			}
			if ($r[$i]['TimeModeSession']['points'] >= 950
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '7k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '8k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '9k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '10k')) {
				$timeModeAchievements[88] = true;
			}
		}
		for ($i = 70; $i <= 91; $i++) {
			$achievementId = $i;
			if ($timeModeAchievements[$achievementId] == true && !isset($existingAs[$achievementId])) {
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkRatingAchievements() {
		if (!Auth::isLoggedIn()) {
			return;
		}

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 59;
		$currentElo = Auth::getUser()['rating'];
		if ($currentElo >= 1500 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 60;
		if ($currentElo >= 1600 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 61;
		if ($currentElo >= 1700 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 62;
		if ($currentElo >= 1800 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 63;
		if ($currentElo >= 1900 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 64;
		if ($currentElo >= 2000 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 65;
		if ($currentElo >= 2100 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 66;
		if ($currentElo >= 2200 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 67;
		if ($currentElo >= 2300 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 68;
		if ($currentElo >= 2400 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 69;
		if ($currentElo >= 2500 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkLevelAchievements() {
		if (!Auth::isLoggedIn()) {
			return;
		}
		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 36;
		$userLevel = Auth::getUser()['level'];
		if ($userLevel >= 10 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 37;
		if ($userLevel >= 20 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 38;
		if ($userLevel >= 30 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 39;
		if ($userLevel >= 40 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 40;
		if ($userLevel >= 50 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 41;
		if ($userLevel >= 60 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 42;
		if ($userLevel >= 70 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 43;
		if ($userLevel >= 80 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 44;
		if ($userLevel >= 90 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 45;
		if ($userLevel >= 100 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 100;
		if (Auth::hasPremium() && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function checkSetCompletedAchievements() {
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');

		$ac = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'set',
			],
		]);

		if (!$ac) {
			return [];
		}

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 47;
		if ($ac['AchievementCondition']['value'] >= 10 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 48;
		if ($ac['AchievementCondition']['value'] >= 20 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 49;
		if ($ac['AchievementCondition']['value'] >= 30 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 50;
		if ($ac['AchievementCondition']['value'] >= 40 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 51;
		if ($ac['AchievementCondition']['value'] >= 50 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 52;
		if ($ac['AchievementCondition']['value'] >= 60 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function setAchievementSpecial($s = null) {
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('SetConnection');

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$tsIds = [];
		$completed = '';
		if ($s == 'cc1') {
			$ts1 = TsumegoUtil::collectTsumegosFromSet(50);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(52);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(53);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(54);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			}
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts) {
				$uts = [];
			}
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				for ($k = 0; $k < $tsCount; $k++) {
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')) {
						$counter++;
					}
				}
			}
			if ($counter == count($ts)) {
				$completed = $s;
			}
		} elseif ($s == 'cc2') {
			$ts1 = TsumegoUtil::collectTsumegosFromSet(41);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(49);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(65);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(66);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			}
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts) {
				$uts = [];
			}
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				for ($k = 0; $k < $tsCount; $k++) {
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')) {
						$counter++;
					}
				}
			}
			if ($counter == count($ts)) {
				$completed = $s;
			}
		} elseif ($s == 'cc3') {
			$ts1 = TsumegoUtil::collectTsumegosFromSet(186);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(187);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(196);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(203);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			}
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts) {
				$uts = [];
			}
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				for ($k = 0; $k < $tsCount; $k++) {
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')) {
						$counter++;
					}
				}
			}
			if ($counter == count($ts)) {
				$completed = $s;
			}
		} elseif ($s == '1000w1') {
			$ts1 = TsumegoUtil::collectTsumegosFromSet(190);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(193);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(198);
			$ts = array_merge($ts1, $ts2, $ts3);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			}
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts) {
				$uts = [];
			}
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				for ($k = 0; $k < $tsCount; $k++) {
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')) {
						$counter++;
					}
				}
			}
			if ($counter == count($ts)) {
				$completed = $s;
			}
		} elseif ($s == '1000w2') {
			$ts = TsumegoUtil::collectTsumegosFromSet(216);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			}
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts) {
				$uts = [];
			}
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				for ($k = 0; $k < $tsCount; $k++) {
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')) {
						$counter++;
					}
				}
			}
			if ($counter == count($ts)) {
				$completed = $s;
			}
		}

		$achievementId = 92;
		if ($completed == 'cc1' && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 93;
		if ($completed == 'cc2' && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 94;
		if ($completed == 'cc3' && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 95;
		if ($completed == '1000w1' && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 115;
		if ($completed == '1000w2' && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function checkSetAchievements($sid = null) {
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');

		//$tNum = count($this->Tsumego->find('all', array('conditions' => array('set_id' => $sid))));
		$tNum = count(TsumegoUtil::collectTsumegosFromSet($sid));
		$s = $this->Set->findById($sid);
		$acA = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => '%',
			],
		]);
		if (!$acA) {
			return [];
		}
		$acS = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value ASC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => 's',
			],
		]);
		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer) {
			$buffer = [];
		}
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++) {
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		}
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 99;
		if ($sid == -1 && !isset($existingAs[$achievementId])) {
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		if ($tNum >= 100) {
			if ($s['Set']['difficulty'] < 1300) {
				$achievementId = 12;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 13;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 14;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 24;
				if ($acS['AchievementCondition']['value'] < 15 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 25;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 26;
				if ($acS['AchievementCondition']['value'] < 5 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			} elseif ($s['Set']['difficulty'] >= 1300 && $s['Set']['difficulty'] < 1500) {
				$achievementId = 15;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 16;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 17;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 27;
				if ($acS['AchievementCondition']['value'] < 18 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 28;
				if ($acS['AchievementCondition']['value'] < 13 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 29;
				if ($acS['AchievementCondition']['value'] < 8 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			} elseif ($s['Set']['difficulty'] >= 1500 && $s['Set']['difficulty'] < 1700) {
				$achievementId = 18;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 19;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 20;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 30;
				if ($acS['AchievementCondition']['value'] < 30 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 31;
				if ($acS['AchievementCondition']['value'] < 20 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 32;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			} else {
				$achievementId = 21;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 22;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 23;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 33;
				if ($acS['AchievementCondition']['value'] < 30 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 34;
				if ($acS['AchievementCondition']['value'] < 20 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 35;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId])) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			$achievementId = 46;
			if ($acA['AchievementCondition']['value'] >= 100) {
				$ac100 = ClassRegistry::init('AchievementCondition')->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => '%', 'value >=' => 100]]);
				if (!$ac100) {
					$ac100 = [];
				}
				$ac100counter = 0;
				$ac100Count = count($ac100);
				for ($j = 0; $j < $ac100Count; $j++) {
					if (count(TsumegoUtil::collectTsumegosFromSet($ac100[$j]['AchievementCondition']['set_id'])) >= 100) {
						$ac100counter++;
					}
				}
				$as100 = ClassRegistry::init('AchievementStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId]]);
				if ($as100 == null) {
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					$as['AchievementStatus']['value'] = 1;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				} elseif ($as100['AchievementStatus']['value'] != $ac100counter) {
					$as100['AchievementStatus']['value'] = $ac100counter;
					ClassRegistry::init('AchievementStatus')->save($as100);
					array_push($updated, $achievementId);
				}
			}
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++) {
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function getXPJump($lvl = null): int {
		if ($lvl >= 102) {
			return 0;
		}
		if ($lvl == 101) {
			return 1150;
		}
		if ($lvl == 100) {
			return 50000;
		}
		if ($lvl >= 70) {
			return 150;
		}
		if ($lvl >= 40) {
			return 100;
		}
		if ($lvl >= 20) {
			return 50;
		}
		if ($lvl >= 12) {
			return 25;
		}

		return 10;
	}

	public static function updateXP($userID, $achievementData): void {
		$xpBonus = 0;
		$aCount = count($achievementData);
		for ($i = 0; $i < $aCount; $i++) {
			$xpBonus += $achievementData[$i][4];
		}
		if ($xpBonus == 0) {
			return;
		}
		$user = ClassRegistry::init('User')->findById($userID);
		$user['User']['xp'] = $xpBonus;
		Level::checkLevelUp($user['User']);
		ClassRegistry::init('User')->save($user);
	}

	public static function getPartitionRange($amountRemaining, $collectionSize, $partition) {
		if ($collectionSize > 0) {
			$amountPartitions = ceil($amountRemaining / $collectionSize);
		} else {
			return ['0', $collectionSize - 1];
		}
		if ($collectionSize > 0 && $amountRemaining % $collectionSize == 0) {
			return [ $partition * $collectionSize,  $partition * $collectionSize + $collectionSize - 1];
		}
		$amountCounter = 0;
		$amountFrom = 0;
		$amountTo = $collectionSize - 1;
		while ($amountRemaining > $collectionSize) {
			if ($partition == $amountCounter) {
				break;
			}
			$amountRemaining -= $collectionSize;
			$amountCounter++;
			$amountFrom += $collectionSize;
			$amountTo += $collectionSize;
		}
		$amountTo = $amountFrom + $collectionSize - 1;
		if ($partition >= $amountPartitions - 1) {
			$amountTo = $amountFrom + $amountRemaining - 1;
		}
		$a = [];
		$a[0] = $amountFrom;
		$a[1] = $amountTo;

		return $a;
	}

	/**
	 * @param int $uid User ID
	 * @return void
	 */
	protected function handleSearchSettings($uid) {
		$this->loadModel('UserContribution');
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null) {
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['added_tag'] = 0;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			$this->UserContribution->create();
			$this->UserContribution->save($uc);
		}
		new TsumegoFilters();
	}

	protected function signIn(array $user): void {
		Auth::init($user);
		$vs = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $user['User']['id']], 'order' => 'created DESC']);
		if ($vs) {
			$this->Session->write('lastVisit', $vs['TsumegoStatus']['tsumego_id']);
		}
		$this->Session->write('texture', $user['User']['texture']);
		$this->Session->write('check1', $user['User']['id']);
	}

	public function beforeFilter(): void {
		$this->loadModel('User');
		$this->loadModel('Activate');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Set');
		$this->loadModel('TimeModeAttempt');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('UserBoard');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('AdminActivity');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('TagName');
		$this->loadModel('Favorite');

		Auth::init();
		$this->TimeMode->init();

		$highscoreLink = 'highscore';
		$lightDark = 'light';
		$resetCookies = false;
		$levelBar = 1;
		$lastProfileLeft = 1;
		$lastProfileRight = 2;
		$hasFavs = false;

		if (Auth::isLoggedIn()) {
			if (isset($_COOKIE['addTag']) && $_COOKIE['addTag'] != 0 && $this->Session->read('page') != 'set') {
				$newAddTag = explode('-', $_COOKIE['addTag']);
				$tagId = $newAddTag[0];
				$newTagName = $this->TagName->find('first', ['conditions' => ['name' => str_replace($tagId . '-', '', $_COOKIE['addTag'])]]);
				if ($newTagName) {
					$saveTag = [];
					$saveTag['Tag']['tag_name_id'] = $newTagName['TagName']['id'];
					$saveTag['Tag']['tsumego_id'] = $tagId;
					$saveTag['Tag']['user_id'] = Auth::getUserID();
					$saveTag['Tag']['approved'] = 0;
					$this->Tag->save($saveTag);
				}
				$this->set('removeCookie', 'addTag');
			}
			if (isset($_COOKIE['z_sess']) && $_COOKIE['z_sess'] != 0
			&& strlen($_COOKIE['z_sess']) > 5) {
				Auth::getUser()['_sessid'] = $_COOKIE['z_sess'];
				Auth::saveUser();
			}
			if (Auth::getUser()['lastHighscore'] == 1) {
				$highscoreLink = 'highscore';
			} elseif (Auth::getUser()['lastHighscore'] == 2) {
				$highscoreLink = 'rating';
			} elseif (Auth::getUser()['lastHighscore'] == 3) {
				$highscoreLink = 'leaderboard';
			} elseif (Auth::getUser()['lastHighscore'] == 4) {
				$highscoreLink = 'highscore3';
			}

			if (isset($_COOKIE['lastMode']) && $_COOKIE['lastMode'] != 0) {
				Auth::getUser()['lastMode'] = $_COOKIE['lastMode'];
				Auth::saveUser();
			}
			if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0') {
				Auth::getUser()['sound'] = $_COOKIE['sound'];
				Auth::saveUser();
				unset($_COOKIE['sound']);
			}
			$this->set('ac', true);
			$this->set('user', Auth::getUser());
		}

		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0') {
			$lightDark = $_COOKIE['lightDark'];
			if (Auth::isLoggedIn()) {
				// Convert string to integer for database storage
				$lightDarkInt = ($lightDark === 'light') ? 0 : 2;
				Auth::getUser()['lastLight'] = $lightDarkInt;
			}
		} elseif (Auth::isLoggedIn()) {
			if (Auth::getUser()['lastLight'] == 0
			|| Auth::getUser()['lastLight'] == 1) {
				$lightDark = 'light';
			} else {
				$lightDark = 'dark';
			}
		}

		if (Auth::isLoggedIn()) {
			$this->handleSearchSettings(Auth::getUserID());
			if (isset($_COOKIE['levelBar']) && $_COOKIE['levelBar'] != '0') {
				$levelBar = $_COOKIE['levelBar'];
				Auth::getUser()['levelBar'] = $levelBar;
			} elseif (Auth::getUser()['levelBar'] == 0
		  || Auth::getUser()['levelBar'] == 'level') {
				$levelBar = 1;
			} else {
				$levelBar = 2;
			}

			if (isset($_COOKIE['lastProfileLeft']) && $_COOKIE['lastProfileLeft'] != '0') {
				$lastProfileLeft = $_COOKIE['lastProfileLeft'];
				Auth::getUser()['lastProfileLeft'] = $lastProfileLeft;
			} else {
				$lastProfileLeft = Auth::getUser()['lastProfileLeft'];
				if ($lastProfileLeft == 0) {
					$lastProfileLeft = 1;
				}
			}
			if (isset($_COOKIE['lastProfileRight']) && $_COOKIE['lastProfileRight'] != '0') {
				$lastProfileRight = $_COOKIE['lastProfileRight'];
				Auth::getUser()['lastProfileRight'] = $lastProfileRight;
			} else {
				$lastProfileRight = Auth::getUser()['lastProfileRight'];
				if ($lastProfileRight == 0) {
					$lastProfileRight = 1;
				}
			}
		}
		$mode = 1;
		if (isset($_COOKIE['mode']) && $_COOKIE['mode'] != '0') {
			if ($_COOKIE['mode'] == 1) {
				$mode = 1;
			} else {
				$mode = 2;
			}
		}

		if (Auth::isLoggedIn() && Auth::getUser()['mode'] == 2) {
			$mode = 2;
		}

		if ($_COOKIE['sprint'] != 1) {
			$this->updateSprintCondition();
		}
		$correctSolveAttempt = false;

		if (Auth::isLoggedIn()) {
			if (isset($_COOKIE['revelation']) && $_COOKIE['revelation'] != 0) {
				Auth::getUser()['revelation'] -= 1;
			}

			if (!$this->request->is('ajax')) {
				$this->PlayResultProcessor->checkPreviousPlay($this->TimeMode);
			}

			if (isset($_COOKIE['noScore']) && isset($_COOKIE['noPreId'])) {
				if ($_COOKIE['noScore'] != '0' && $_COOKIE['noPreId'] != '0') {
					//$previosTsumegoX = $this->Tsumego->findById($_COOKIE['noPreId']);
					//$previosTsumegoXsc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $_COOKIE['noPreId'])));
					//$scoreArrX = explode('-', $this->decrypt($_COOKIE['noScore']));

					$utPreX = $this->TsumegoStatus->find('first', ['conditions' => ['tsumego_id' => $_COOKIE['noPreId'], 'user_id' => Auth::getUserID()]]);
					if ($utPreX == null) {
						$utPreX['TsumegoStatus'] = [];
						$utPreX['TsumegoStatus']['user_id'] = Auth::getUserID();
						$utPreX['TsumegoStatus']['tsumego_id'] = $_COOKIE['noPreId'];
					}
					if ($utPreX['TsumegoStatus']['status'] == 'W') {
						$utPreX['TsumegoStatus']['status'] = 'C';
					} else {
						$utPreX['TsumegoStatus']['status'] = 'S';
					}
					$utPreX['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					//$this->TsumegoStatus->save($utPreX);
					//$sessionUts = $this->Session->read('loggedInUser.uts');
					/*if (!$sessionUts) {
					$sessionUts = [];
					}
					$sessionUts[$utPreX['TsumegoStatus']['tsumego_id']] = $utPreX['TsumegoStatus']['status'];
					$this->Session->write('loggedInUser.uts', $sessionUts);*/
				}
			}
		}
		$boardNames = [];
		$enabledBoards = [];
		$boardPositions = [];

		$boardNames[1] = 'Pine';
		$boardNames[2] = 'Ash';
		$boardNames[3] = 'Maple';
		$boardNames[4] = 'Shin Kaya';
		$boardNames[5] = 'Birch';
		$boardNames[6] = 'Wenge';
		$boardNames[7] = 'Walnut';
		$boardNames[8] = 'Mahogany';
		$boardNames[9] = 'Blackwood';
		$boardNames[10] = 'Marble 1';
		$boardNames[11] = 'Marble 2';
		$boardNames[12] = 'Marble 3';
		$boardNames[13] = 'Tibet Spruce';
		$boardNames[14] = 'Marble 4';
		$boardNames[15] = 'Marble 5';
		$boardNames[16] = 'Quarry 1';
		$boardNames[17] = 'Flowers';
		$boardNames[18] = 'Nova';
		$boardNames[19] = 'Spring';
		$boardNames[20] = 'Moon';
		$boardNames[21] = 'Apex';
		$boardNames[22] = 'Gold 1';
		$boardNames[23] = 'Amber';
		$boardNames[24] = 'Marble 6';
		$boardNames[25] = 'Marble 7';
		$boardNames[26] = 'Marble 8';
		$boardNames[27] = 'Marble 9';
		$boardNames[28] = 'Marble 10';
		$boardNames[29] = 'Jade';
		$boardNames[30] = 'Quarry 2';
		$boardNames[31] = 'Black Bricks';
		$boardNames[32] = 'Wallpaper 1';
		$boardNames[33] = 'Wallpaper 2';
		$boardNames[34] = 'Gold & Gray';
		$boardNames[35] = 'Gold & Pink';
		$boardNames[36] = 'Veil';
		$boardNames[37] = 'Tiles';
		$boardNames[38] = 'Mars';
		$boardNames[39] = 'Pink Cloud';
		$boardNames[40] = 'Reptile';
		$boardNames[41] = 'Mezmerizing';
		$boardNames[42] = 'Magenta Sky';
		$boardNames[43] = 'Tsumego Hero';
		$boardNames[44] = 'Pretty';
		$boardNames[45] = 'Hunting';
		$boardNames[46] = 'Haunted';
		$boardNames[47] = 'Carnage';
		$boardNames[48] = 'Blind Spot';
		$boardNames[49] = 'Giants';
		$boardNames[50] = 'Gems';
		$boardNames[51] = 'Grandmaster';
		$boardPositions[1] = [1, 'texture1', 'black34.png', 'white34.png'];
		$boardPositions[2] = [2, 'texture2', 'black34.png', 'white34.png'];
		$boardPositions[3] = [3, 'texture3', 'black34.png', 'white34.png'];
		$boardPositions[4] = [4, 'texture4', 'black.png', 'white.png'];
		$boardPositions[5] = [5, 'texture5', 'black34.png', 'white34.png'];
		$boardPositions[6] = [6, 'texture6', 'black.png', 'white.png'];
		$boardPositions[7] = [7, 'texture7', 'black34.png', 'white34.png'];
		$boardPositions[8] = [8, 'texture8', 'black.png', 'white.png'];
		$boardPositions[9] = [9, 'texture9', 'black.png', 'white.png'];
		$boardPositions[10] = [10, 'texture10', 'black34.png', 'white34.png'];
		$boardPositions[11] = [11, 'texture11', 'black34.png', 'white34.png'];
		$boardPositions[12] = [12, 'texture12', 'black34.png', 'white34.png'];
		$boardPositions[13] = [13, 'texture13', 'black34.png', 'white34.png'];
		$boardPositions[14] = [14, 'texture14', 'black34.png', 'white34.png'];
		$boardPositions[15] = [15, 'texture15', 'black.png', 'white.png'];
		$boardPositions[16] = [16, 'texture16', 'black34.png', 'white34.png'];
		$boardPositions[17] = [17, 'texture17', 'black34.png', 'white34.png'];
		$boardPositions[18] = [18, 'texture18', 'black.png', 'white.png'];
		$boardPositions[19] = [19, 'texture19', 'black34.png', 'white34.png'];
		$boardPositions[20] = [20, 'texture20', 'black34.png', 'white34.png'];
		$boardPositions[21] = [33, 'texture33', 'black34.png', 'white34.png'];
		$boardPositions[22] = [21, 'texture21', 'black.png', 'whiteKo.png'];
		$boardPositions[23] = [22, 'texture22', 'black34.png', 'white34.png'];
		$boardPositions[24] = [34, 'texture34', 'black.png', 'white.png'];
		$boardPositions[25] = [35, 'texture35', 'black34.png', 'white34.png'];
		$boardPositions[26] = [36, 'texture36', 'black.png', 'white.png'];
		$boardPositions[27] = [37, 'texture37', 'black34.png', 'white34.png'];
		$boardPositions[28] = [38, 'texture38', 'black38.png', 'white34.png'];
		$boardPositions[29] = [39, 'texture39', 'black.png', 'white.png'];
		$boardPositions[30] = [40, 'texture40', 'black34.png', 'white34.png'];
		$boardPositions[31] = [41, 'texture41', 'black34.png', 'white34.png'];
		$boardPositions[32] = [42, 'texture42', 'black34.png', 'white42.png'];
		$boardPositions[33] = [43, 'texture43', 'black34.png', 'white42.png'];
		$boardPositions[34] = [44, 'texture44', 'black34.png', 'white34.png'];
		$boardPositions[35] = [45, 'texture45', 'black34.png', 'white42.png'];
		$boardPositions[36] = [47, 'texture47', 'black34.png', 'white34.png'];
		$boardPositions[37] = [48, 'texture48', 'black34.png', 'white34.png'];
		$boardPositions[38] = [49, 'texture49', 'black.png', 'white.png'];
		$boardPositions[39] = [50, 'texture50', 'black34.png', 'white34.png'];
		$boardPositions[40] = [51, 'texture51', 'black34.png', 'white34.png'];
		$boardPositions[41] = [52, 'texture52', 'black34.png', 'white34.png'];
		$boardPositions[42] = [53, 'texture53', 'black34.png', 'white34.png'];
		$boardPositions[43] = [54, 'texture54', 'black54.png', 'white54.png'];
		$boardPositions[44] = [23, 'texture23', 'black.png', 'whiteFlower.png'];
		$boardPositions[45] = [24, 'texture24', 'black24.png', 'white24.png'];
		$boardPositions[46] = [25, 'texture25', 'blackGhost.png', 'white.png'];
		$boardPositions[47] = [26, 'texture26', 'blackInvis.png', 'whiteCarnage.png'];
		$boardPositions[48] = [27, 'texture27', 'black27.png', 'white27.png'];
		$boardPositions[49] = [28, 'texture28', 'blackGiant.png', 'whiteKo.png'];
		$boardPositions[50] = [29, 'texture29', 'blackKo.png', 'whiteKo.png'];
		$boardPositions[51] = [30, 'texture55', 'blackGalaxy.png', 'whiteGalaxy.png'];

		$boardCount = 51;

		if ($this->Session->check('texture') || (isset($_COOKIE['texture']) && $_COOKIE['texture'] != '0')) {
			$splitCookie = [];
			if (isset($_COOKIE['texture']) && $_COOKIE['texture'] != '0') {
				$splitCookie = str_split($_COOKIE['texture']);
				$textureCookies = $_COOKIE['texture'];
				Auth::getUser()['texture'] = $this->Session->read('texture');
				$this->Session->write('texture', $_COOKIE['texture']);
				$this->set('textureCookies', $textureCookies);
			} else {
				if (Auth::isLoggedIn()) {
					$this->Session->write('texture', Auth::getUser()['texture']);
				}
				$textureCookies = $this->Session->read('texture');
				$splitTextureCookies = str_split($textureCookies);
				$splitTextureCookiesCount = count($splitTextureCookies);
				for ($i = 0; $i < $splitTextureCookiesCount; $i++) {
					if ($splitTextureCookies[$i] == 2) {
						$enabledBoards[$i + 1] = 'checked';
					} else {
						$enabledBoards[$i + 1] = '';
					}
				}
			}

			$splitCookieCount = count($splitCookie);
			for ($i = 0; $i < $splitCookieCount; $i++) {
				if ($splitCookie[$i] == 2) {
					$enabledBoards[$i + 1] = 'checked';
				} else {
					$enabledBoards[$i + 1] = '';
				}
			}
			if (Auth::isLoggedIn()) {
				Auth::saveUser();
			}
		}

		if (!$this->Session->check('texture')) {
			$this->Session->write('texture', '222222221111111111111111111111111111111111111111111');
			$enabledBoards[1] = 'checked';
			$enabledBoards[2] = 'checked';
			$enabledBoards[3] = 'checked';
			$enabledBoards[4] = 'checked';
			$enabledBoards[5] = 'checked';
			$enabledBoards[6] = 'checked';
			$enabledBoards[7] = 'checked';
			$enabledBoards[8] = 'checked';
			$enabledBoards[9] = '';
			$enabledBoards[10] = '';
			$enabledBoards[11] = '';
			$enabledBoards[12] = '';
			$enabledBoards[13] = '';
			$enabledBoards[14] = '';
			$enabledBoards[15] = '';
			$enabledBoards[16] = '';
			$enabledBoards[17] = '';
			$enabledBoards[18] = '';
			$enabledBoards[19] = '';
			$enabledBoards[20] = '';
			$enabledBoards[21] = '';
			$enabledBoards[22] = '';
			$enabledBoards[23] = '';
			$enabledBoards[24] = '';
			$enabledBoards[25] = '';
			$enabledBoards[26] = '';
			$enabledBoards[27] = '';
			$enabledBoards[28] = '';
			$enabledBoards[29] = '';
			$enabledBoards[30] = '';
			$enabledBoards[31] = '';
			$enabledBoards[32] = '';
			$enabledBoards[33] = '';
			$enabledBoards[34] = '';
			$enabledBoards[35] = '';
			$enabledBoards[36] = '';
			$enabledBoards[37] = '';
			$enabledBoards[38] = '';
			$enabledBoards[39] = '';
			$enabledBoards[40] = '';
			$enabledBoards[41] = '';
			$enabledBoards[42] = '';
			$enabledBoards[43] = '';
			$enabledBoards[44] = '';
			$enabledBoards[45] = '';
			$enabledBoards[46] = '';
			$enabledBoards[47] = '';
			$enabledBoards[48] = '';
			$enabledBoards[49] = '';
			$enabledBoards[50] = '';
			$enabledBoards[51] = '';
		}
		$achievementUpdate = [];
		if ($this->Session->check('initialLoading')) {
			$achievementUpdate1 = $this->checkLevelAchievements();
			$achievementUpdate2 = $this->checkProblemNumberAchievements();
			$achievementUpdate3 = $this->checkRatingAchievements();
			$achievementUpdate4 = $this->checkTimeModeAchievements();
			$achievementUpdate5 = $this->checkDanSolveAchievements();
			$achievementUpdate = array_merge(
				$achievementUpdate1 ?: [],
				$achievementUpdate2 ?: [],
				$achievementUpdate3 ?: [],
				$achievementUpdate4 ?: [],
				$achievementUpdate5 ?: []
			);
			$this->Session->delete('initialLoading');
		}

		if (count($achievementUpdate) > 0) {
			$this->updateXP(Auth::getUserID(), $achievementUpdate);
		}

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn()) {
			Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());
			$this->set('user', Auth::getUser());
		}
		$this->set('mode', $mode);
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('boardNames', $boardNames);
		$this->set('enabledBoards', $enabledBoards);
		$this->set('boardPositions', $boardPositions);
		$this->set('highscoreLink', $highscoreLink);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('lastProfileLeft', $lastProfileLeft);
		$this->set('lastProfileRight', $lastProfileRight);
		$this->set('resetCookies', $resetCookies);
		$this->set('hasFavs', $hasFavs);
	}

	public function afterFilter() {}
}
