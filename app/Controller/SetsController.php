<?php
class SetsController extends AppController{

  public $helpers = array('Html', 'Form');
	public $title = 'tsumego-hero.com';

	public function duplicates($id=null){
		$this->LoadModel('Tsumego');
		$this->LoadModel('Sgf');
		$this->LoadModel('Duplicate');
		$this->LoadModel('SetConnection');
		
		$_SESSION['page'] = 'sandbox';
		$_SESSION['title'] = 'Tsumego Hero - Duplicates';
		
		$tIds = array();
		$d2 = array();
		
		if(isset($this->params['url']['unmark'])){
			$unmark = $this->Duplicate->find('all', array('conditions' => array('dGroup' => $this->params['url']['unmark'])));
			for($i=0; $i<count($unmark); $i++)
				$this->Duplicate->delete($unmark[$i]['Duplicate']['id']);
		}
		
		$ts = $this->findTsumegoSet($id);
		$set = $this->Set->findById($ts[0]['Tsumego']['set_id']);
		for($i=0; $i<count($ts); $i++)
			array_push($tIds, $ts[$i]['Tsumego']['id']);
		$d0 = $this->Duplicate->find('all', array('conditions' => array('tsumego_id' => $tIds)));
		
		$d = array();
		for($i=0; $i<count($d0); $i++){
			$d01 = $this->Duplicate->find('all', array('conditions' => array(
				'dGroup' => $d0[$i]['Duplicate']['dGroup'],
				'NOT' => array('tsumego_id' => $d0[$i]['Duplicate']['tsumego_id'])
			)));
			array_push($d, $d0[$i]);
			for($j=0; $j<count($d01); $j++){
				array_push($d, $d01[$j]);
			}
		}
		$dNew = array();
		for($i=0; $i<count($d); $i++){
			$dNewMatch = false;
			for($j=0; $j<count($dNew); $j++){
				if($i!=$j){
					if($d[$i]['Duplicate']['id']==$dNew[$j]['Duplicate']['id'])
						$dNewMatch = true;
				}
			}
			if(!$dNewMatch)
				array_push($dNew, $d[$i]);
		}
		$d = $dNew;
		$similarArr = array();
		$similarArrInfo = array();
		$similarArrBoardSize = array();
		
		$counter2 = 0;
		$counter = -1;
		
		$currentGroup = -1;
		for($i=0; $i<count($d); $i++){
			if($currentGroup!=$d[$i]['Duplicate']['dGroup']){
				$counter++;
				$d2[$counter] = array();
			}
			$td = $this->Tsumego->findById($d[$i]['Duplicate']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $td['Tsumego']['id'])));$td['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$setx = $this->Set->findById($td['Tsumego']['set_id']);
			$td['Tsumego']['title'] = $setx['Set']['title'].' - '.$td['Tsumego']['num'];
			$td['Tsumego']['dGroup'] = $d[$i]['Duplicate']['dGroup'];
			
			array_push($d2[$counter], $td);
			$currentGroup = $d[$i]['Duplicate']['dGroup'];
			
			$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $td['Tsumego']['id'])));
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
	
	public function duplicatesearch(){
		$this->LoadModel('Tsumego');
		$this->LoadModel('Duplicate');
		$_SESSION['page'] = 'sandbox';
		$_SESSION['title'] = 'Duplicate Search Results';
		$s = $this->Set->find('all', array('order' => 'created DESC', 'conditions' => array(
			'OR' => array(
				array('public' => 1),
				array('public' => 0)
			)
		)));
		for($i=0; $i<count($s); $i++){
			//$ts = $this->Tsumego->find('all', array('conditions' => array('set_id' => $s[$i]['Set']['id'])));
			$ts = $this->findTsumegoSet($s[$i]['Set']['id']);		
			$tsIds = array();
			for($j=0; $j<count($ts); $j++){
				array_push($tsIds, $ts[$j]['Tsumego']['id']);
			}
			$d = $this->Duplicate->find('all', array('conditions' => array('tsumego_id' => $tsIds)));
			$s[$i]['Set']['dNum'] = count($d);
		}
		
		$ds1 = file_get_contents('ds1.txt');
		$all = $this->Tsumego->find('all', array('order' => 'id ASC'));
		
		$this->set('progress', $progress);
		$this->set('s', $s);
	}

	public function beta(){
		$this->LoadModel('User');
		$this->LoadModel('Tsumego');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Comment');
		$this->LoadModel('Favorite');
		$this->LoadModel('Comment');
		$this->LoadModel('SetConnection');

		$_SESSION['page'] = 'sandbox';
		$_SESSION['title'] = 'Tsumego Hero - Collections';
		$setsNew = array();

		if(isset($this->params['url']['restore'])){
			$restore = $this->Set->findById($this->params['url']['restore']);
			if($restore['Set']['public']==-1){
				$restore['Set']['public']=0;
				$this->Set->save($restore);
			}
		}

		$sets = $this->Set->find('all', array(
			'order' => array('Set.order'),
			'conditions' => array('public' => 0)
		));
		$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);

		if(isset($_SESSION['loggedInUser'])){
			$uts = $this->TsumegoStatus->find('all', array('conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
			$utsMap = array();
			for($l=0; $l<count($uts); $l++){
				$utsMap[$uts[$l]['TsumegoStatus']['tsumego_id']] = $uts[$l]['TsumegoStatus']['status'];
			}
		}
		$overallCounter = 0;

		for($i=0; $i<count($sets); $i++){
			$ts = $this->findTsumegoSet($sets[$i]['Set']['id']);
			$sets[$i]['Set']['anz'] = count($ts);
			$counter = 0;
			$elo = 0;
			for($k=0; $k<count($ts); $k++){
				
				$elo += $ts[$k]['Tsumego']['elo_rating_mode'];
				if(isset($_SESSION['loggedInUser']['User']['id'])){
					if(isset($utsMap[$ts[$k]['Tsumego']['id']])){
						if($utsMap[$ts[$k]['Tsumego']['id']] == 'S' || $utsMap[$ts[$k]['Tsumego']['id']] == 'W' || $utsMap[$ts[$k]['Tsumego']['id']] == 'C'){
							$counter++;
						}
					}
				}
			}
			$elo = $elo / count($ts);
			$date = new DateTime($sets[$i]['Set']['created']);
			$month = date("F", strtotime($sets[$i]['Set']['created']));
			$setday = $date->format('d. ');
			$setyear = $date->format('Y');
			if($setday[0]==0) $setday = substr($setday, -3);
			$sets[$i]['Set']['created'] = $date->format('Ymd');
			$sets[$i]['Set']['createdDisplay'] = $setday.$month.' '.$setyear;
			$percent = 0;
			if(count($ts)>0) $percent = $counter/count($ts)*100;
			$overallCounter += count($ts);
			$sets[$i]['Set']['solvedNum'] = $counter;
			$sets[$i]['Set']['solved'] = round($percent, 1);
			$sets[$i]['Set']['solvedColor'] = $this->getSolvedColor($sets[$i]['Set']['solved']);
			$sets[$i]['Set']['topicColor'] = $sets[$i]['Set']['color'];
			$sets[$i]['Set']['difficultyColor'] = $this->getDifficultyColor($sets[$i]['Set']['difficulty']);
			$sets[$i]['Set']['sizeColor'] = $this->getSizeColor($sets[$i]['Set']['anz']);
			$sets[$i]['Set']['dateColor'] = $this->getDateColor($sets[$i]['Set']['created']);

			$sn = array();
			$sn['id'] = $sets[$i]['Set']['id'];
			$sn['name'] = $sets[$i]['Set']['title'];
			$sn['amount'] = count($ts);
			$sn['color'] = $sets[$i]['Set']['color'];
			$sn['difficulty'] = $this->getTsumegoRank($elo);
			$sn['solved'] = round($percent, 1);
			array_push($setsNew, $sn);
		}

		$accessList = $this->User->find('all', array('conditions' => array('completed' => 1)));
		$access = array();
		for($i=0; $i<count($accessList); $i++){
			array_push($access, $accessList[$i]['User']['name']);
		}

		$adminsList = $this->User->find('all', array('order' => 'id ASC', 'conditions' => array('isAdmin >' => 0)));
		$admins = array();
		for($i=0; $i<count($adminsList); $i++){
			array_push($admins, $adminsList[$i]['User']['name']);
		}

		$this->set('admins', $admins);
		$this->set('access', $access);
		$this->set('sets', $sets);
		$this->set('setsNew', $setsNew);
		$this->set('overallCounter', $overallCounter);
  }

	public function create($tid=null){
		$this->LoadModel('Tsumego');
		$this->LoadModel('SetConnection');
		$redirect = false;
		$t = array();
		if(isset($this->data['Set'])){
			$s = $this->Set->find('all', array('order' => 'id DESC'));
			$ss = array();
			for($i=0;$i<count($s);$i++){
				if($s[$i]['Set']['id']<6472) array_push($ss, $s[$i]);
			}

			$seed = str_split('abcdefghijklmnopqrstuvwxyz0123456789');
			shuffle($seed);
			$rand = '';
			foreach (array_rand($seed, 6) as $k) $rand .= $seed[$k];
			$hashName = '6473k339312/_'.$rand.'_'.$this->data['Set']['title'];
			$hashName2 = '_'.$rand.'_'.$this->data['Set']['title'];

			$set = array();
			$set['Set']['id'] = $ss[0]['Set']['id']+1;
			$set['Set']['title'] = $this->data['Set']['title'];
			$set['Set']['public'] = 0;
			$set['Set']['image'] = 'b1.png';
			$set['Set']['folder'] = $hashName2;
			$set['Set']['difficulty'] = 4;
			$set['Set']['author'] = 'various creators';
			$set['Set']['order'] = 999;

			$this->Set->create();
			$this->Set->save($set);

			$tMax = $this->Tsumego->find('first', array('order' => 'id DESC'));

			$t = array();
			$t['Tsumego']['id'] = $tMax['Tsumego']['id']+1;
			$t['Tsumego']['set_id'] = 206;
			$t['Tsumego']['num'] = 1;
			$t['Tsumego']['difficulty'] =  4;
			$t['Tsumego']['variance'] =  100;
			$t['Tsumego']['description'] =  'b to kill';
			$t['Tsumego']['author'] =  $_SESSION['loggedInUser']['User']['name'];
			$this->Tsumego->create();
			$this->Tsumego->save($t);
			
			$sc = array();
			$sc['SetConnection']['set_id'] = $ss[0]['Set']['id']+1;
			$sc['SetConnection']['tsumego_id'] = $tMax['Tsumego']['id']+1;
			$sc['SetConnection']['num'] = 1;
			$this->SetConnection->create();
			$this->SetConnection->save($sc);

			mkdir($hashName, 0777);
			copy('6473k339312/__new/1.sgf', $hashName.'/1.sgf');

			$redirect = true;
		}
		$this->set('t', $t);
		$this->set('redirect', $redirect);
	}

	public function remove($id){
		$this->LoadModel('Tsumego');
		$redirect = false;

		if(isset($this->data['Set'])){
			if(strpos(';'.$this->data['Set']['hash'], '6473k339312-')==1){
				$set = str_replace('6473k339312-', '', $this->data['Set']['hash']);

				$s = $this->Set->findById($set);
				if($s['Set']['public']==0 || $s['Set']['public']==-1) $this->Set->delete($set);
				$ts = $this->findTsumegoSet($set);	
				if(count($ts)<50){
					for($i=0;$i<count($ts);$i++){
						$this->Tsumego->delete($ts[$i]['Tsumego']['id']);
					}
				}
				$redirect = true;
			}
		}
		$this->set('t', $t);
		$this->set('redirect', $redirect);
	}

	public function add($tid){
		$this->LoadModel('Tsumego');

		if(isset($this->data['Tsumego'])){
			$t = array();
			$t['Tsumego']['num'] = $this->data['Tsumego']['num'];
			$t['Tsumego']['difficulty'] =  $this->data['Tsumego']['difficulty'];
			$t['Tsumego']['variance'] =  $this->data['Tsumego']['variance'];
			$t['Tsumego']['description'] =  $this->data['Tsumego']['description'];
			$this->Tsumego->save($t);
		}
		$ts = $this->findTsumegoSet($tid);
		$this->set('t', $ts[0]);
	}

	private function newDate($date=null){
		$this->LoadModel('Tsumego');
		$ts = $this->findTsumegoSet(94);	
		for($i=0; $i<count($ts); $i++){
			if($ts[$i]['Tsumego']['num']>200 && $ts[$i]['Tsumego']['num']<=300){
				$ts[$i]['Tsumego']['created'] = $date;
				$this->Tsumego->save($ts[$i]);
			}
		}
	}

	public function index(){
		$this->LoadModel('User');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Favorite');
		$this->LoadModel('AchievementCondition');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('SetConnection');
		$this->LoadModel('UserContribution');
		$_SESSION['page'] = 'set';
		$_SESSION['title'] = 'Tsumego Hero - Collections';

		$setTiles = array();
		$difficultyTiles = array();
		$tagTiles = array();
		$utsMap = array();
		$sets = array();
		$ranksArray = array();
		$tagList = array();
		$setsWithPremium = array();
		$overallCounter = 0;
		$searchCounter = 0;
		$searchPatameters = $this->processSearchParameters($_SESSION['loggedInUser']['User']['id']);
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];

		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			$hasPremium = false;
		else
			$hasPremium = true;
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++)
			array_push($setsWithPremium, $swp[$i]['Set']['id']);

		//setTiles
		$setsRaw = $this->Set->find('all', array(
			'order' => array('Set.order'),
			'conditions' => array('public' => 1)
		));
		for($i=0; $i<count($setsRaw); $i++)
			if($hasPremium==true || $setsRaw[$i]['Set']['premium']!=1)
				array_push($setTiles, $setsRaw[$i]['Set']['title']);
		
		//difficultyTiles
		$dt = $this->getExistingRanksArray();
		for($i=0; $i<count($dt); $i++)
			array_push($difficultyTiles, $dt[$i]['rank']);

		//tagTiles
		$tags = $this->Tag->find('all', array('conditions' => array('approved' => 1)));
		$tagNames = $this->TagName->find('all', array('conditions' => array(
			'approved' => 1,
			'NOT' => array('name' => 'Tsumego')
		)));
		$json = json_decode(file_get_contents('json/popular_tags.json'));
		$tn = $this->TagName->find('all');
		$tnKeys = array();
		$tnKeysAmount = array();
		$json2 = array();
		$json2amount = array();
		$hybrid = array();
		$group100 = array();
		$group10 = array();
		$group1 = array();
		for($i=0;$i<count($tn);$i++){
			$tnKeys[$tn[$i]['TagName']['id']] = $tn[$i]['TagName']['name'];
			$tnKeysAmount[$tn[$i]['TagName']['id']] = $tn[$i]['TagName']['name'];
		}
		for($i=0; $i<count($json); $i++)
			if($tnKeys[$json[$i]->id] != 'Tsumego'){
				array_push($json2, $tnKeys[$json[$i]->id]);
				array_push($json2amount, $json[$i]->num);
			}
		for($i=0; $i<count($json2); $i++)
			//if($json2amount[$i]>=100)
			if(true)
				array_push($group100, $json2[$i]);
			else if($json2amount[$i]>=10)
				array_push($group10, $json2[$i]);
			else
				array_push($group1, $json2[$i]);
			
		array_multisort($group100);
		array_multisort($group10);
		array_multisort($group1);
		for($i=0; $i<count($group100); $i++)
			array_push($hybrid, $group100[$i]);
		/*	
		for($i=0; $i<count($group10); $i++)
			array_push($hybrid, $group10[$i]);
		for($i=0; $i<count($group1); $i++)
			array_push($hybrid, $group1[$i]);
		*/
		$json2 = $hybrid;
		for($i=0; $i<count($json2); $i++){
			$json3 = $this->TagName->findByName($json2[$i]);
			array_push($tagTiles, $json3['TagName']['name']);
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$utsMap = $_SESSION['loggedInUser']['uts'];
		}else{
			$noLoginUts = array();
			for($i=0; $i<count($_SESSION['noLogin']); $i++)
				$noLoginUts[$_SESSION['noLogin'][$i]] = $_SESSION['noLoginStatus'][$i];
			$utsMap = $noLoginUts;
		}
		$search3ids = array();
		for($i=0; $i<count($search3); $i++)
			array_push($search3ids, $this->TagName->findByName($search3[$i])['TagName']['id']);

		//sets
		if($query == "topics"){
			$setConditions = array('public' => 1);
			if(count($search1) > 0){
				$search1ids = array();
				for($i=0; $i<count($search1); $i++){
					$search1id = $this->Set->find('first', array('conditions' => array(
						'title' => $search1[$i],
						'public' => 1
					)));
					$search1ids[$i] = $search1id['Set']['id'];
				}
				$setConditions['id'] = $search1ids;
			}
			$rankConditions = array();
			if(count($search2) > 0){
				$fromTo = array();
				for($i=0; $i<count($search2); $i++){
					$ft = array();
					$ft['elo_rating_mode >='] =  $this->getTsumegoElo($search2[$i]);
					$ft['elo_rating_mode <'] =  $ft['elo_rating_mode >=']+100;
					if($search2[$i]=='15k')
						$ft['elo_rating_mode >='] = 50;
					array_push($fromTo, $ft);
				}
				$rankConditions['OR'] = $fromTo;
			}
			$setsRaw = $this->Set->find('all', array('order' => 'order ASC', 'conditions' => $setConditions));

			$achievementUpdate = 0;
			for($i=0; $i<count($setsRaw); $i++){
				if(count($rankConditions) > 0){
					$ts = $this->Tsumego->find('all', array('order' => 'num ASC', 'conditions' => array(
						'set_id' => $setsRaw[$i]['Set']['id'],
						$rankConditions
					)));
				}else{
					$ts = $this->findTsumegoSet($setsRaw[$i]['Set']['id']);
				}
				$currentIds = array();
				for($j=0; $j<count($ts); $j++)
					array_push($currentIds, $ts[$j]['Tsumego']['id']);
				$setAmount = count($ts);
				if(count($search3) > 0){
					$idsTemp = array();
					$tsTagsFiltered = $this->Tag->find('all', array('conditions' => array(
						'tsumego_id' => $currentIds,
						'tag_name_id' => $search3ids,
					)));
					for($j=0; $j<count($tsTagsFiltered); $j++)
						array_push($idsTemp, $tsTagsFiltered[$j]['Tag']['tsumego_id']);
					$currentIds = array_unique($idsTemp);
					$setAmount = count($currentIds);
				}
				if(!in_array($setsRaw[$i]['Set']['id'], $setsWithPremium) || $hasPremium)
					$searchCounter += $setAmount;
				
				$s = array();
				$s['id'] = $setsRaw[$i]['Set']['id'];
				$s['name'] = $setsRaw[$i]['Set']['title'];
				$s['amount'] = $setAmount;
				$s['color'] = $setsRaw[$i]['Set']['color'];
				$s['premium'] = $setsRaw[$i]['Set']['premium'];
				$s['currentIds'] = $currentIds;
				if(count($currentIds)>0)
					array_push($sets, $s);
			}
			
			$sets = $this->partitionCollections($sets, $collectionSize, $utsMap);
			if($collectionSize>=200){
				for($i=0; $i<count($sets); $i++){
					if($sets[$i]['solved']>=100)
						$overallCounter++;
				}
			}
		}else{
			for($i=0; $i<count($setsRaw); $i++){
				$s = array();
				$s['id'] = $setsRaw[$i]['Set']['id'];
				$s['name'] = $setsRaw[$i]['Set']['title'];
				array_push($sets, $s);
			}
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($overallCounter>=10){
				$aCondition = $this->AchievementCondition->find('first', array('order' => 'value DESC', 'conditions' => array(
					'user_id' => $_SESSION['loggedInUser']['User']['id'], 'category' => 'set'
				)));
				if($aCondition==null) $aCondition = array();
				$aCondition['AchievementCondition']['category'] = 'set';
				$aCondition['AchievementCondition']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$aCondition['AchievementCondition']['value'] = $overallCounter;
				$this->AchievementCondition->save($aCondition);
			}
			$this->User->save($u);
			$achievementUpdate = $this->checkSetCompletedAchievements();
			if(count($achievementUpdate)>0)
				$this->updateXP($_SESSION['loggedInUser']['User']['id'], $achievementUpdate);
		}
		//difficulty
		if($query == "difficulty"){
			$ranksArray = $this->getExistingRanksArray();
			$newRanksArray = array();
			$setConditions = array();
			if(count($search1) > 0){
				$search1ids = array();
				for($i=0; $i<count($search1); $i++){
					$search1id = $this->Set->find('first', array('conditions' => array('title' => $search1[$i])));
					$search1ids[$i] = $search1id['Set']['id'];
				}
				$setConditions['set_id'] = $search1ids;
			}
			if(count($search2) > 0){
				$ranksArray2 = array();
				$ranksCounter = 0;
				for($i=0; $i<count($search2); $i++){
					for($j=0; $j<count($ranksArray); $j++){
						if($search2[$i]==$ranksArray[$j]['rank']){
							$ranksArray2[$ranksCounter]['rank'] = $ranksArray[$j]['rank'];
							$ranksArray2[$ranksCounter]['color'] = $ranksArray[$j]['color'];
							$ranksCounter++;
						}
					}
				}
				$ranksArray = $ranksArray2;
			}
			for($i=0; $i<count($ranksArray); $i++){
				$ftFrom['elo_rating_mode >='] =  $this->getTsumegoElo($ranksArray[$i]['rank']);
				$ftTo['elo_rating_mode <'] =  $ftFrom['elo_rating_mode >=']+100;
				if($ranksArray[$i]['rank']=='15k')
					$ftFrom['elo_rating_mode >='] = 50;
				$notPremiumArray = array();
				if(!$hasPremium)
					$notPremiumArray['NOT'] = array('set_id' => $setsWithPremium);
				$ts1 = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
					'public' => 1,
					$notPremiumArray,
					$ftFrom,
					$ftTo,
					$setConditions
				)));
				$setAmount = count($ts1);
				$currentIds = array();
				for($j=0; $j<count($ts1); $j++)
					array_push($currentIds, $ts1[$j]['Tsumego']['id']);
						
				if(count($search3) > 0){
					$idsTemp = array();
					$tsTagsFiltered = $this->Tag->find('all', array('conditions' => array(
						'tsumego_id' => $currentIds,
						'tag_name_id' => $search3ids,
					)));
					for($j=0; $j<count($tsTagsFiltered); $j++)
						array_push($idsTemp, $tsTagsFiltered[$j]['Tag']['tsumego_id']);
					
					$currentIds = array_unique($idsTemp);
					$setAmount = count($currentIds);
				}
				$searchCounter += $setAmount;

				$rTemp = array();
				$rTemp['id'] = $ranksArray[$i]['rank'];
				$rTemp['name'] = $ranksArray[$i]['rank'];
				$rTemp['amount'] = $setAmount;
				$rTemp['currentIds'] = $currentIds;
				$rTemp['color'] = $ranksArray[$i]['color'];
				if(count($currentIds)>0)
					array_push($newRanksArray, $rTemp);
			}
			$ranksArray = $this->partitionCollections($newRanksArray, $collectionSize, $utsMap);
		}else{
			$ranksArray = $this->getExistingRanksArray();
			for($i=0; $i<count($ranksArray); $i++){
				$ranksArray[$i]['id'] = $ranksArray[$i]['rank'];
				$ranksArray[$i]['name'] = $ranksArray[$i]['rank'];
			}
		}
		//tags
		if($query == "tags"){
			$setConditions = array();
			$search3Set = false;
			if(count($search1) > 0){
				$search1idxx = array();
				for($i=0; $i<count($search1); $i++){
					$search1id = $this->Set->find('first', array('conditions' => array('title' => $search1[$i])));
					$search1idx = $this->findTsumegoSet($search1id['Set']['id']);
					for($j=0; $j<count($search1idx); $j++)
						array_push($search1idxx, $search1idx[$j]['Tsumego']['id']);
				}
				$setConditions['tsumego_id'] = $search1idxx;
			}
			if(count($search3) > 0){
				$search3Set = true;
			}
			for($i=0; $i<count($json2); $i++){
				if(in_array($json2[$i], $search3) || !$search3Set){
					$json3 = $this->TagName->findByName($json2[$i]);
					$tagsx = $this->Tag->find('all', array('order' => 'id ASC', 'conditions' => array(
						'tag_name_id' => $json3['TagName']['id'], 
						'approved' => 1,
						$setConditions
					)));
					$currentIds = array();
					for($j=0; $j<count($tagsx); $j++)
						array_push($currentIds, $tagsx[$j]['Tag']['tsumego_id']);

					if(!$hasPremium){
						$currentIdsNew = array();
						$pTest = $this->Tsumego->find('all', array('conditions' => array('id' => $currentIds)));
						for($j=0; $j<count($pTest); $j++)
							if(!in_array($pTest[$j]['Tsumego']['set_id'], $setsWithPremium))
								array_push($currentIdsNew, $pTest[$j]['Tsumego']['id']);
						$currentIds = $currentIdsNew;
					}
					$amountTags = count($currentIds);

					if(count($search2) > 0){
						$rankConditions = array();	
						$fromTo = array();
						$idsTemp = array();
						for($j=0; $j<count($search2); $j++){
							$ft = array();
							$ft['elo_rating_mode >='] =  $this->getTsumegoElo($search2[$j]);
							$ft['elo_rating_mode <'] =  $ft['elo_rating_mode >=']+100;
							if($search2[$j]=='15k')
								$ft['elo_rating_mode >='] = 50;
							array_push($fromTo, $ft);
						}
						$rankConditions['OR'] = $fromTo;

						$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
							'id' => $currentIds,
							'public' => 1,
							$rankConditions
						)));
						for($j=0; $j<count($ts); $j++)
							array_push($idsTemp, $ts[$j]['Tsumego']['id']);
						$currentIds = array_unique($idsTemp);
						$amountTags = count($currentIds);
					}
					$searchCounter += $amountTags;

					$tl = array();
					$tl['id'] = $json3['TagName']['id'];
					$tl['name'] = $json3['TagName']['name'];
					$tl['amount'] = $amountTags;
					$tl['currentIds'] = $currentIds;
					$tl['color'] = $json3['TagName']['color'];
					if($amountTags>0)
						array_push($tagList, $tl);
				}
			}
			for($i=0; $i<count($tagList); $i++)
				$tagList[$i]['color'] = $this->getTagColor($tagList[$i]['color']);
			$tagList = $this->partitionCollections($tagList, $collectionSize, $utsMap);
		}else{
			for($i=0; $i<count($json2); $i++){
				$json3 = $this->TagName->findByName($json2[$i]);
				$tl = array();
				$tl['id'] = $json3['TagName']['id'];
				$tl['name'] = $json3['TagName']['name'];
				array_push($tagList, $tl);
			}
		}
		if($query=='topics' && count($search1)==0)
			$queryRefresh = false;
		else if($query=='difficulty' && count($search2)==0)
			$queryRefresh = false;
		else if($query=='tags' && count($search3)==0)
			$queryRefresh = false;
		else
			$queryRefresh = true;
		
		$this->set('sets', $sets);
		$this->set('ranksArray', $ranksArray);
		$this->set('tagList', $tagList);
		$this->set('setTiles', $setTiles);
		$this->set('difficultyTiles', $difficultyTiles);
		$this->set('tagTiles', $tagTiles);
		$this->set('query', $query);
		$this->set('collectionSize', $collectionSize);
		$this->set('search1', $search1);
		$this->set('search2', $search2);
		$this->set('search3', $search3);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('searchCounter', $searchCounter);
		$this->set('hasPremium', $hasPremium);
		$this->set('queryRefresh', $queryRefresh);
	}

	private function partitionCollections($list, $size, $utsMap){
		$newList = array();
		for($i=0; $i<count($list); $i++){
			$amountTags = $list[$i]['amount'];
			$amountCounter = 0;
			$amountFrom = 0;
			$amountTo = $size-1;
			while($amountTags > $size){
				$newList = $this->partitionCollection($newList, $list[$i], $size, $utsMap, $amountFrom, $amountTo+1, $amountCounter, true);
				$amountTags -= $size;
				$amountCounter++;
				$amountFrom += $size;
				$amountTo += $size;
			}
			$amountTo = $amountFrom + $amountTags;
			$newList = $this->partitionCollection($newList, $list[$i], $amountTags, $utsMap, $amountFrom, $amountTo, $amountCounter, false);
		}
		return $newList;
	}

	private function partitionCollection($newList, $list, $size, $utsMap, $from, $to, $amountCounter, $inLoop){
		$tl = array();
		$tl['id'] = $list['id'];
		$colorValue = 1;
		if(!$inLoop && $amountCounter==0){
			$tl['partition'] = -1;
		}else{
			$tl['partition'] = $amountCounter;
			$step = 1.5;
			$colorValue = 1 - ($amountCounter * 0.1 * $step );
		}
		$tl['name'] = $list['name'];
		$tl['amount'] = $size;
		$tl['color'] = str_replace('[o]',$colorValue,$list['color']);
		if(isset($list['premium']))
			$tl['premium'] = $list['premium'];
		else
			$tl['premium'] = 0;
		$currentIds = array();
		for($i=$from; $i<$to; $i++)
			array_push($currentIds, $list['currentIds'][$i]);
		$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $utsMap);
		$tl['difficulty'] = $difficultyAndSolved['difficulty'];
		$tl['solved'] = $difficultyAndSolved['solved'];
		array_push($newList, $tl);
		
		return $newList;
	}

	private function getDifficultyAndSolved($currentTagIds, $utsMap){
		$tagTsumegoDifficulty = $this->Tsumego->find('all', array('conditions' => array('id' => $currentTagIds)));
		$tagDifficultyResult = 0;
		$statusCounter = 0;
		for($j=0; $j<count($tagTsumegoDifficulty); $j++){
			$tagDifficultyResult += $tagTsumegoDifficulty[$j]['Tsumego']['elo_rating_mode'];
			if(isset($utsMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']])){
				if($utsMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']]=='S' || $utsMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']]=='W' || $utsMap[$tagTsumegoDifficulty[$j]['Tsumego']['id']]=='C')
					$statusCounter++;
			}
		}
		$tagDifficultyResult = $tagDifficultyResult / count($tagTsumegoDifficulty);
		$tagDifficultyResult = $this->getTsumegoRank($tagDifficultyResult);
		$return = array();
		$return['difficulty'] = $tagDifficultyResult;
		$return['solved'] = round($statusCounter / count($currentTagIds) * 100, 2);
		return $return;
	}
	
	public function ui($id=null){
		$s = $this->Set->findById($id);
		$redirect = false;

		if(isset($_FILES['adminUpload'])){
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$randstring = 'set_';
			for($i=0; $i<6; $i++){
				$randstring .= $characters[rand(0,strlen($characters))];
			}
			$filename = $randstring.'_'.$_FILES['adminUpload']['name'];

			$errors= array();
			$file_name = $_FILES['adminUpload']['name'];
			$file_size =$_FILES['adminUpload']['size'];
			$file_tmp =$_FILES['adminUpload']['tmp_name'];
			$file_type=$_FILES['adminUpload']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['adminUpload']['name'])));
			$extensions= array("png", "jpg");

			if(in_array($file_ext,$extensions)=== false) $errors[]="png/jpg allowed.";
			if($file_size > 2097152) $errors[]='The file is too large.';

			if(empty($errors)==true){
				$uploadfile = $_SERVER['DOCUMENT_ROOT'].'/app/webroot/img/'.$filename;
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

  public function view($id=null){
		$this->LoadModel('Tsumego');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Favorite');
		$this->LoadModel('AdminActivity');
		$this->LoadModel('Joseki');
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('ProgressDeletion');
		$this->LoadModel('Achievement');
		$this->LoadModel('AchievementStatus');
		$this->LoadModel('AchievementCondition');
		$this->LoadModel('Sgf');
		$this->LoadModel('SetConnection');
		$this->LoadModel('TagName');
		$this->LoadModel('Tag');
		$this->LoadModel('User');
		$this->LoadModel('UserContribution');

		if($id != '1')
			$_SESSION['page'] = 'set';
		else
			$_SESSION['page'] = 'favs';
		$josekiOrder = 0;
		$tsIds = array();
		$refreshView = false;
		$avgTime = 0;
		$accuracy = 0;
		$formChange = false;
		$achievementUpdate = array();
		$viewType = 'topics';
		$partition = -1;
		$hasPartition = false;
		$utsMap = array();
		$setsWithPremium = array();
		$setDifficulty = 1200;
		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			$hasPremium = false;
		else
			$hasPremium = true;
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++)
			array_push($setsWithPremium, $swp[$i]['Set']['id']);

		if(isset($this->params['url']['partition'])){
			$partition = $this->params['url']['partition'];
			$hasPartition = true;
		}
		if($partition == -1)
			$partition = 0;

		$searchPatameters = $this->processSearchParameters($_SESSION['loggedInUser']['User']['id']);
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];
		$search3ids = array();

		for($i=0; $i<count($search3); $i++)
			array_push($search3ids, $this->TagName->findByName($search3[$i])['TagName']['id']);
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$utsMap = $_SESSION['loggedInUser']['uts'];
			if($_SESSION['loggedInUser']['User']['isAdmin']>0){
				$aad = $this->AdminActivity->find('first', array('order' => 'id DESC'));
				if($aad['AdminActivity']['file'] == '/delete'){
					$scDelete = $this->SetConnection->find('first', array('order' => 'created DESC','conditions' => array('tsumego_id' => $aad['AdminActivity']['tsumego_id'])));
					$this->SetConnection->delete($scDelete['SetConnection']['id']);
					$this->Tsumego->delete($aad['AdminActivity']['tsumego_id']);
					$aad['AdminActivity']['file'] = 'description';
					$this->AdminActivity->save($aad);
				}
			}
		}
		if(isset($this->params['url']['add'])){
			$overallCount = $this->Tsumego->find('first', array('order' => 'id DESC'));
			$scTcount = $this->SetConnection->find('first', array('conditions' => array('set_id' => $id, 'num' => 1)));
			$setCount = $this->Tsumego->findById($scTcount['SetConnection']['tsumego_id']);
			$setCount['Tsumego']['id'] = $overallCount['Tsumego']['id'] + 1;
			$setCount['Tsumego']['set_id'] = $scTcount['SetConnection']['set_id'];
			$setCount['Tsumego']['num'] += 1;
			$setCount['Tsumego']['variance'] = 100;
			if($_SESSION['loggedInUser']['User']['id'] == 72) $setCount['Tsumego']['author'] = 'Joschka Zimdars';
			elseif($_SESSION['loggedInUser']['User']['id'] == 1206) $setCount['Tsumego']['author'] = 'Innokentiy Zabirov';
			elseif($_SESSION['loggedInUser']['User']['id'] == 3745) $setCount['Tsumego']['author'] = 'Dennis Olevanov';
			else $setCount['Tsumego']['author'] = $_SESSION['loggedInUser']['User']['name'];
			$this->Tsumego->create();
			$this->Tsumego->save($setCount);
			$adminActivity = array();
			$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$adminActivity['AdminActivity']['tsumego_id'] = 0;
			$adminActivity['AdminActivity']['file'] = 'description';
			$adminActivity['AdminActivity']['answer'] = 'Added problem for '.$set['Set']['title'];
			$this->AdminActivity->save($adminActivity);
		}

		if(isset($this->params['url']['show'])){
			if($this->params['url']['show']=='order') $josekiOrder = 1;
			if($this->params['url']['show']=='num') $josekiOrder = 0;
		}
		if($id!='1'){
			if(is_numeric($id)){
				$viewType = 'topics';
			}else{
				$foundLetter = false;
				$idSplit = str_split($id);
				for($i=0; $i<count($idSplit)-1; $i++){
					if(!is_numeric($idSplit[$i]))
						$foundLetter = true;
				}
				if($foundLetter==false && ($idSplit[count($idSplit)-1]=='k' || $idSplit[count($idSplit)-1]=='d'))
					$viewType = 'difficulty';
				else
					$viewType = 'tags';
			}
			$query = $viewType;
			$_COOKIE['query'] = $query;
			$searchPatameters = $this->processSearchParameters($_SESSION['loggedInUser']['User']['id']);

			$_SESSION['lastSet'] = $id;
			if($viewType == 'difficulty'){
				$set = array();
				$setConditions = array();
				if(count($search1) > 0){
					$search1ids = array();
					for($i=0; $i<count($search1); $i++){
						$search1id = $this->Set->find('first', array('conditions' => array('title' => $search1[$i])));
						$search1ids[$i] = $search1id['Set']['id'];
					}
					$setConditions['set_id'] = $search1ids;
				}
				$set['Set']['id'] = $id;
				if(!$hasPartition)
					$set['Set']['title'] = $id;
				else
					$set['Set']['title'] = $id.' #'.($partition+1);
				$set['Set']['image'] = $id.'Rank.png';
				$set['Set']['multiplier'] = 1;
				$set['Set']['public'] = 1;
				$elo = $this->getTsumegoElo($id);
				$ftFrom['elo_rating_mode >='] =  $elo;
				$ftTo['elo_rating_mode <'] =  $elo+100;
				if($id=='15k'){
					$ftFrom['elo_rating_mode >='] = 50;
					$set['Set']['description'] = $id.' are problems that have a rating from 600 to '.($ftTo['elo_rating_mode <']-1).'. This collection also contains problems with weaker rating.';
				}else{
					$set['Set']['description'] = $id.' are problems that have a rating from '.$ftFrom['elo_rating_mode >='].' to '.($ftTo['elo_rating_mode <']-1).'.';
				}
					
				$set['Set']['difficulty'] = $elo;
				$notPremiumArray = array();
				if(!$hasPremium)
					$notPremiumArray['NOT'] = array('set_id' => $setsWithPremium);
				$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
					'public' => 1,
					$notPremiumArray,
					$ftFrom,
					$ftTo,
					$setConditions
				)));

				$ts1 = array();
				$i2 = 1;
				for($i=0; $i<count($ts); $i++){
					$tagValid = false;
					if(count($search3) > 0){
						$tagForTsumego = $this->Tag->find('first', array('conditions' => array(
							'tsumego_id' => $ts[$i]['Tsumego']['id'],
							'tag_name_id' => $search3ids
						)));
						if($tagForTsumego!=null)
							$tagValid = true;
					}else
						$tagValid = true;
					if($tagValid){
						$ts[$i]['Tsumego']['num'] = $i2;
						array_push($ts1, $ts[$i]);
						$i2++;
					}	
				}
				$ts = $ts1;
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$currentIds = array();
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					$ts[$i]['Tsumego']['status'] = $utsMap[$ts[$i]['Tsumego']['id']];
					array_push($currentIds, $ts[$i]['Tsumego']['id']);
					array_push($ts1, $ts[$i]);
				}
				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $utsMap);
				$ts = $ts1;
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($ts);
			}else if($viewType == 'tags'){
				$set['Set']['id'] = $id;
				if(!$hasPartition)
					$set['Set']['title'] = $id;
				else
					$set['Set']['title'] = $id.' #'.($partition+1);
				$set['Set']['image'] = '';
				$set['Set']['multiplier'] = 1;
				$set['Set']['public'] = 1;
				$setConditions = array();
				$rankConditions = array();
				$tagIds = array();
				$tagName = $this->TagName->findByName($id);
				$set['Set']['description'] = $tagName['TagName']['description'];
				if(count($search1) > 0){
					$search1idxx = array();
					for($i=0; $i<count($search1); $i++){
						$search1id = $this->Set->find('first', array('conditions' => array('title' => $search1[$i])));
						$search1idx = $this->findTsumegoSet($search1id['Set']['id']);
						for($j=0; $j<count($search1idx); $j++)
							array_push($search1idxx, $search1idx[$j]['Tsumego']['id']);
					}
					$setConditions['tsumego_id'] = $search1idxx;
				}
				$tagsx = $this->Tag->find('all', array('order' => 'id ASC', 'conditions' => array(
					'tag_name_id' => $tagName['TagName']['id'],
					'approved' => 1,
					$setConditions
				)));
				for($i=0; $i<count($tagsx); $i++)
					array_push($tagIds, $tagsx[$i]['Tag']['tsumego_id']);

				if(!$hasPremium){
					$currentIdsNew = array();
					$pTest = $this->Tsumego->find('all', array('conditions' => array('id' => $tagIds)));
					for($j=0; $j<count($pTest); $j++)
						if(!in_array($pTest[$j]['Tsumego']['set_id'], $setsWithPremium))
							array_push($currentIdsNew, $pTest[$j]['Tsumego']['id']);
					$tagIds = $currentIdsNew;
				}

				if(count($search2) > 0){
					$fromTo = array();
					$idsTemp = array();
					for($j=0; $j<count($search2); $j++){
						$ft = array();
						$ft['elo_rating_mode >='] =  $this->getTsumegoElo($search2[$j]);
						$ft['elo_rating_mode <'] =  $ft['elo_rating_mode >=']+100;
						if($search2[$j]=='15k')
							$ft['elo_rating_mode >='] = 50;
						array_push($fromTo, $ft);
					}
					$rankConditions['OR'] = $fromTo;
				}
				$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
					'id' => $tagIds,
					'public' => 1,
					$rankConditions
				)));
				for($i=0; $i<count($ts); $i++){
					$ts[$i]['Tsumego']['num'] = $i+1;
					if(in_array($ts[$i]['Tsumego']['set_id'], $setsWithPremium) && !$hasPremium)
						$ts[$i]['Tsumego']['locked'] = true;
					else
						$ts[$i]['Tsumego']['locked'] = false;
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$currentIds = array();
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					$ts[$i]['Tsumego']['status'] = $utsMap[$ts[$i]['Tsumego']['id']];
					array_push($currentIds, $ts[$i]['Tsumego']['id']);
					array_push($ts1, $ts[$i]);
				}
				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $utsMap);
				$ts = $ts1;
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($ts);
			//set
			}else if($viewType == 'topics'){
				$set = $this->Set->find('first', array('conditions' => array('id' => $id)));
				$ts = array();
				$scTs = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array('set_id' => $set['Set']['id'])));
				$rankConditions = array();
				if(count($search2) > 0){
					$fromTo = array();
					for($i=0; $i<count($search2); $i++){
						$ft = array();
						$ft['elo_rating_mode >='] =  $this->getTsumegoElo($search2[$i]);
						$ft['elo_rating_mode <'] =  $ft['elo_rating_mode >=']+100;
						if($search2[$i]=='15k')
							$ft['elo_rating_mode >='] = 50;
						array_push($fromTo, $ft);
					}
					$rankConditions['OR'] = $fromTo;
				}
				for($i=0; $i<count($scTs); $i++){
					$scT = $this->Tsumego->find('first', array('conditions' => array(
						'id' => $scTs[$i]['SetConnection']['tsumego_id'],
						$rankConditions
					)));
					$tagValid = false;
					if(count($search3) > 0){
						$tagForTsumego = $this->Tag->find('first', array('conditions' => array(
							'tsumego_id' => $scTs[$i]['SetConnection']['tsumego_id'],
							'tag_name_id' => $search3ids,
						)));
						if($tagForTsumego!=null)
							$tagValid = true;
					}else
						$tagValid = true;
					if($scT!=null && $tagValid){
						$scT['Tsumego']['set_id'] = $scTs[$i]['SetConnection']['set_id'];
						$scT['Tsumego']['num'] = $scTs[$i]['SetConnection']['num'];
						$scT['Tsumego']['duplicateLink'] = '';
						$scTs2 = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array('tsumego_id' => $scT['Tsumego']['id'])));
						for($j=0;$j<count($scTs2);$j++)
							if(count($scTs2)>1 && $scTs2[$j]['SetConnection']['set_id']==$set['Set']['id'])
								$scT['Tsumego']['duplicateLink'] = '?sid='.$scT['Tsumego']['set_id'];
						array_push($ts, $scT);
					}
				}
				if($set['Set']['public']!=1)
					$collectionSize = 2000;
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$currentIds = array();
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					if(isset($utsMap[$ts[$i]['Tsumego']['id']]))
						$ts[$i]['Tsumego']['status'] = $utsMap[$ts[$i]['Tsumego']['id']];
					else
						$ts[$i]['Tsumego']['status'] = 'N';
					array_push($currentIds, $ts[$i]['Tsumego']['id']);
					array_push($ts1, $ts[$i]);
				}
				$difficultyAndSolved = $this->getDifficultyAndSolved($currentIds, $utsMap);
				$ts = $ts1;
				
				$set['Set']['difficultyRank'] = $difficultyAndSolved['difficulty'];
				$set['Set']['solved'] = $difficultyAndSolved['solved'];
				$set['Set']['anz'] = count($ts);
				if($hasPartition)
					$set['Set']['title'] = $set['Set']['title'].' #'.($partition+1);
				$tsBuffer = array();
				$tsBufferLowest=10000;
				$tsBufferHighest=0;
				for($i=0; $i<count($ts); $i++){
					$tsBuffer[$ts[$i]['Tsumego']['num']] = $ts[$i];
					if($ts[$i]['Tsumego']['num']<$tsBufferLowest)
						$tsBufferLowest = $ts[$i]['Tsumego']['num'];
					if($ts[$i]['Tsumego']['num']>$tsBufferHighest)
						$tsBufferHighest = $ts[$i]['Tsumego']['num'];
				}
				$ts = array();
				for($i=$tsBufferLowest; $i<=$tsBufferHighest; $i++)
					if(isset($tsBuffer[$i]))
						array_push($ts, $tsBuffer[$i]);
				$allVcActive = true;
				$allVcInactive = true;
				$allArActive = true;
				$allArInactive = true;
				$allPassActive = true;
				$allPassInactive = true;
				for($i=0; $i<count($ts); $i++){
					if($ts[$i]['Tsumego']['virtual_children']==0)
						$allVcActive = false;
					if($ts[$i]['Tsumego']['virtual_children']==1)
						$allVcInactive = false;
					if($ts[$i]['Tsumego']['alternative_response']==0)
						$allArActive = false;
					if($ts[$i]['Tsumego']['alternative_response']==1)
						$allArInactive = false;
					if($ts[$i]['Tsumego']['pass']==0)
						$allPassActive = false;
					if($ts[$i]['Tsumego']['pass']==1)
						$allPassInactive = false;
				}
				for($i=0; $i<count($ts); $i++)
					array_push($tsIds, $ts[$i]['Tsumego']['id']);
				if($set['Set']['public']==0)
					$_SESSION['page'] = 'sandbox';
				$this->set('isFav', false);
				if(isset($this->params['url']['sort'])){
					if($this->params['url']['sort']==1){
						$tsId = array();
						$tsNum = array();
						$tsOrder = array();
						for($i=0; $i<count($ts); $i++){
							array_push($tsId, $ts[$i]['Tsumego']['id']);
							array_push($tsNum, $ts[$i]['Tsumego']['num']);
							array_push($tsOrder, $ts[$i]['Tsumego']['order']);
						}
						array_multisort($tsOrder, $tsId, $tsNum);
						$nr = 1;
						for($i=0; $i<count($tsId); $i++){
							$tsu = $this->Tsumego->findById($tsId[$i]);
							if($tsu['Tsumego']['num']!=$nr) rename('6473k339312/joseki/'.$tsu['Tsumego']['num'].'.sgf','6473k339312/joseki/'.$tsu['Tsumego']['num'].'x.sgf');
							$nr++;
						}
						$nr = 1;
						for($i=0; $i<count($tsId); $i++){
							$tsu = $this->Tsumego->findById($tsId[$i]);
							if($tsu['Tsumego']['num']!=$nr){
								rename('6473k339312/joseki/'.$tsu['Tsumego']['num'].'x.sgf','6473k339312/joseki/'.$nr.'.sgf');
								$tsu['Tsumego']['num'] = $nr;
								$this->Tsumego->save($tsu);
							}
							$nr++;
						}
					}
				}
				if(isset($this->params['url']['rename'])){
					if($this->params['url']['rename']==1){
						$tsId = array();
						$tsNum = array();
						$tsOrder = array();
						for($i=0; $i<count($ts); $i++){
							array_push($tsId, $ts[$i]['Tsumego']['id']);
							array_push($tsNum, $ts[$i]['Tsumego']['num']);
							array_push($tsOrder, $ts[$i]['Tsumego']['order']);
						}
						array_multisort($tsOrder, $tsId, $tsNum);
						$nr = 1;
						for($i=0; $i<count($tsId); $i++){
							$j = $this->Joseki->find('first', array('conditions' =>  array('tsumego_id' => $tsId[$i])));
							$j['Joseki']['order'] = $nr;
							$this->Joseki->save($j);
							$nr++;
						}
					}
				}
				if(isset($this->data['Set']['title'])){
					if($set['Set']['title']!=$this->data['Set']['title']) $formChange = true;
					if($set['Set']['title2']!=$this->data['Set']['title2']) $formChange = true;
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['title'] = $this->data['Set']['title'];
					$changeSet['Set']['title2'] = $this->data['Set']['title2'];
					$this->set('data', $changeSet['Set']['title']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set '.$set['Set']['title'];
				}
				if(isset($this->data['Set']['description'])){
					if($set['Set']['description']!=$this->data['Set']['description']) $formChange = true;
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['description'] = $this->data['Set']['description'];
					$this->set('data', $changeSet['Set']['description']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set '.$set['Set']['title'];
				}
				if(isset($this->data['Set']['setDifficulty'])){
					if($this->data['Set']['setDifficulty']!=1200 && $this->data['Set']['setDifficulty']>=900 && $this->data['Set']['setDifficulty']<=2900){
						$setDifficultyTsumegoSet = $this->findTsumegoSet($set['Set']['id']);
						$setDifficulty = $this->data['Set']['setDifficulty'];
						for($i=0;$i<count($setDifficultyTsumegoSet);$i++){
							$setDifficultyTsumegoSet[$i]['Tsumego']['elo_rating_mode'] = $this->data['Set']['setDifficulty'];
							$this->Tsumego->save($setDifficultyTsumegoSet[$i]);
						}
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Edited rating data for set '.$set['Set']['title'];
					}
				}
				if(isset($this->data['Set']['color'])){
					if($set['Set']['color']!=$this->data['Set']['color']) $formChange = true;
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['color'] = $this->data['Set']['color'];
					$this->set('data', $changeSet['Set']['color']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set '.$set['Set']['title'];
					$this->AdminActivity->save($adminActivity);
				}
				if(isset($this->data['Set']['order'])){
					if($set['Set']['order']!=$this->data['Set']['order']) $formChange = true;
					$this->Set->create();
					$changeSet = $set;
					$changeSet['Set']['order'] = $this->data['Set']['order'];
					$this->set('data', $changeSet['Set']['order']);
					$this->Set->save($changeSet, true);
					$set = $this->Set->findById($id);
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Edited meta data for set '.$set['Set']['title'];
					$this->AdminActivity->save($adminActivity);
				}
				if(isset($this->data['Settings'])){
					if($this->data['Settings']['r38'] == 'on'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['virtual_children'] = 1;
							$this->Tsumego->save($ts[$i]);
						}
						$allVcActive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned on merge recurring positions for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
						
					}
					if($this->data['Settings']['r38'] == 'off'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['virtual_children'] = 0;
							$this->Tsumego->save($ts[$i]);
						}
						$allVcInactive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned off merge recurring positions for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if($this->data['Settings']['r39'] == 'on'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['alternative_response'] = 1;
							$this->Tsumego->save($ts[$i]);
						}
						$allArActive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned on alternative response mode for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if($this->data['Settings']['r39'] == 'off'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['alternative_response'] = 0;
							$this->Tsumego->save($ts[$i]);
						}
						$allArInactive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Turned off alternative response mode for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if($this->data['Settings']['r43'] == 'yes'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['pass'] = 1;
							$this->Tsumego->save($ts[$i]);
						}
						$allPassActive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Enabled passing for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					if($this->data['Settings']['r43'] == 'no'){
						for($i=0; $i<count($ts); $i++){
							$ts[$i]['Tsumego']['pass'] = 0;
							$this->Tsumego->save($ts[$i]);
						}
						$allPassInactive = true;
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $ts[0]['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Disabled passing for set '.$set['Set']['title'];
						$this->AdminActivity->save($adminActivity);
					}
					$this->set('formRedirect', true);
				}
				if(isset($this->data['Tsumego'])){
					if(!$formChange){
						$scFormChange = $this->SetConnection->find('first', array('order' => 'num DESC', 'conditions' => array('set_id' => $id)));
						$tFormChange = $this->Tsumego->findById($scFormChange['SetConnection']['tsumego_id']);
						if($scFormChange==null){
							$scFormChange = array();
							$scFormChange['SetConnection']['set_id'] = $id;
							$scFormChange['SetConnection']['num'] = $this->data['Tsumego']['num'];
							$tFormChange = array();
							$tFormChange['Tsumego']['elo_rating_mode'] = 1000;
						}
						$tf = array();
						$tf['Tsumego']['num'] = $this->data['Tsumego']['num'];
						$tf['Tsumego']['difficulty'] = 40;
						$tf['Tsumego']['set_id'] =  $id;
						$tf['Tsumego']['variance'] =  $this->data['Tsumego']['variance'];
						$tf['Tsumego']['description'] =  $this->data['Tsumego']['description'];
						$tf['Tsumego']['hint'] =  $this->data['Tsumego']['hint'];
						$tf['Tsumego']['author'] =  $this->data['Tsumego']['author'];
						$tf['Tsumego']['elo_rating_mode'] = $tFormChange['Tsumego']['elo_rating_mode'];
						if(is_numeric($this->data['Tsumego']['num'])){
							if($this->data['Tsumego']['num']>=0){
								$this->Tsumego->create();
								$this->Tsumego->save($tf);
								$tfSetHighestId = $this->Tsumego->find('first', array('order' => 'id DESC'));
								$tfSetConnection = array();
								$tfSetConnection['SetConnection']['set_id'] = $id;
								$tfSetConnection['SetConnection']['tsumego_id'] = $tfSetHighestId['Tsumego']['id'];
								$tfSetConnection['SetConnection']['num'] = $this->data['Tsumego']['num'];
								$this->SetConnection->create();
								$this->SetConnection->save($tfSetConnection);
							}
						}
						$tsIds = array();
						$ts = $this->findTsumegoSet($id);
						for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
							if(isset($utsMap[$ts[$i]['Tsumego']['id']]))
								$ts[$i]['Tsumego']['status'] = $utsMap[$ts[$i]['Tsumego']['id']];
							else
								$ts[$i]['Tsumego']['status'] = 'N';;
						}
						for($i=0; $i<count($ts); $i++) 
							array_push($tsIds, $ts[$i]['Tsumego']['id']);
					}
				}
			}
			//end set
		//favs
		}else{
			$allUts = $this->TsumegoStatus->find('all', array('conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
			$idMap = array();
			$statusMap = array();
			for($i=0; $i<count($allUts); $i++){
				array_push($idMap, $allUts[$i]['TsumegoStatus']['tsumego_id']);
				array_push($statusMap, $allUts[$i]['TsumegoStatus']['status']);
			}
			$fav = $this->Favorite->find('all', array('order' => 'created',	'direction' => 'DESC', 'conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
			if(count($fav)>0)
				$achievementUpdate = $this->checkSetAchievements(-1);
			$ts = array();
			$difficultyCount = 0;
			$solvedCount = 0;
			$sizeCount = 0;
			for($i=0; $i<count($fav); $i++){
				$tx = $this->Tsumego->find('first', array('conditions' =>  array('id' => $fav[$i]['Favorite']['tsumego_id'])));
				$difficultyCount += $tx['Tsumego']['difficulty'];
				$utx = $this->findUt($fav[$i]['Favorite']['tsumego_id'], $allUts, $idMap);
				if($utx['TsumegoStatus']['status'] == 'S' || $utx['TsumegoStatus']['status'] == 'W' || $utx['TsumegoStatus']['status'] == 'C') $solvedCount++;
				$sizeCount++;
				array_push($ts, $tx);
			}
			for($i=0; $i<count($allUts); $i++){
				for($j=0; $j<count($ts); $j++){
					if($allUts[$i]['TsumegoStatus']['tsumego_id'] == $ts[$j]['Tsumego']['id']){
						$ts[$j]['Tsumego']['status'] = $allUts[$i]['TsumegoStatus']['status'];
					}
				}
			}
			$difficultyCount /= $sizeCount;
			if($difficultyCount<=2) $difficultyCount = 1;
			else if($difficultyCount>2 && $difficultyCount<=3) $difficultyCount = 2;
			else if($difficultyCount>3 && $difficultyCount<=4) $difficultyCount = 3;
			else if($difficultyCount>4 && $difficultyCount<=6) $difficultyCount = 4;
			else if($difficultyCount>6) $difficultyCount = 5;
			$percent = $solvedCount/$sizeCount*100;
			$set = array();
			$set['Set']['id'] = 1;
			$set['Set']['title'] = 'Favorites';
			$set['Set']['title2'] = null;
			$set['Set']['author'] = $_SESSION['loggedInUser']['User']['name'];
			$set['Set']['description'] = '';
			$set['Set']['folder'] = '';
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
		$_SESSION['title'] = $set['Set']['title'].' on Tsumego Hero';
		$set['Set']['anz'] = count($ts);

		if(isset($_SESSION['loggedInUser']['User']['id']) && $viewType == 'topics'){
			$ur = array();
			if($viewType == 'topics'){
				$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'conditions' => array(
					'user_id' => $_SESSION['loggedInUser']['User']['id'],
					'tsumego_id' => $tsIds
				)));
			}
			for($i=0; $i<count($ts); $i++){
				$urTemp = array();
				$urSum = '';
				$ts[$i]['Tsumego']['seconds'] = 0;
				for($j=0; $j<count($ur); $j++){
					if($ts[$i]['Tsumego']['id'] == $ur[$j]['TsumegoAttempt']['tsumego_id']){
						array_push($urTemp, $ur[$j]);
						if($ur[$j]['TsumegoAttempt']['solved']==1){
							$ts[$i]['Tsumego']['seconds'] = $ur[$j]['TsumegoAttempt']['seconds'];
						}
						if($ur[$j]['TsumegoAttempt']['solved']==0){
							$mis = $ur[$j]['TsumegoAttempt']['misplays'];
							if($mis==0) $mis=1;
							while($mis>0){
								$urSum.='F';
								$mis--;
							}
						}else{
							$urSum.=$ur[$j]['TsumegoAttempt']['solved'];
						}
					}
				}
				$ts[$i]['Tsumego']['performance'] = $urSum;
			}
		}
		if(!isset($_SESSION['loggedInUser']['User']['id'])){
			$counter = 0;
			if(isset($_SESSION['noLogin'])){
				$noLogin = $_SESSION['noLogin'];
				$noLoginStatus = $_SESSION['noLoginStatus'];
				for($i=0; $i<count($noLogin); $i++){
					for($f=0; $f<count($ts); $f++){
						if($ts[$f]['Tsumego']['id']==$noLogin[$i]){
							$ts[$f]['Tsumego']['status'] = $noLoginStatus[$i];
							if($noLoginStatus[$i]=='S' || $noLoginStatus[$i]=='W' || $noLoginStatus[$i]=='C'){
								$counter++;
							}
						}
					}
				}
			}
			$percent = $counter/count($ts)*100;
			$set['Set']['solved'] = round($percent, 1);
		}
		$scTt = $this->SetConnection->find('first', array('conditions' => array('set_id' => $set['Set']['id'], 'num' => 1)));
		if($scTt!=null)
			$t = $this->Tsumego->findById($scTt['SetConnection']['tsumego_id']);
		else
			$t = null;
		if($t==null) $t = $ts[0];
		$set['Set']['t'] = $t['Tsumego']['id'];

		$tfs = array();
		if($viewType == 'topics')
			$tfs = $this->findTsumegoSet($id);
		$scoring = true;
		if(isset($_SESSION['loggedInUser']['User']['id']) && $viewType == 'topics'){
			if(isset($this->data['Comment']['reset'])){
				if($this->data['Comment']['reset']=='reset'){
					$uts = $this->TsumegoStatus->find('all', array('conditions' => array(
						'user_id' => $_SESSION['loggedInUser']['User']['id'],
						'tsumego_id' => $currentIds
					)));
					$ur = $this->TsumegoAttempt->find('all', array('conditions' => array(
						'user_id' => $_SESSION['loggedInUser']['User']['id'],
						'tsumego_id' => $currentIds
					)));
					for($i=0; $i<count($ur); $i++){
						$this->TsumegoAttempt->delete($ur[$i]['TsumegoAttempt']['id']);
					}
					for($i=0; $i<count($uts); $i++){
						$this->TsumegoStatus->delete($uts[$i]['TsumegoStatus']['id']);
						unset($_SESSION['loggedInUser']['uts'][$uts[$i]['TsumegoStatus']['tsumego_id']]);
					}
					$pr = array();
					$pr['ProgressDeletion']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$pr['ProgressDeletion']['set_id'] = $id;
					$this->ProgressDeletion->create();
					$this->ProgressDeletion->save($pr);
					$refreshView = true;
				}
			}
			$pd = $this->ProgressDeletion->find('all', array('conditions' => array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'],
				'set_id' => $id
			)));
			$pdCounter = 0;
			for($i=0; $i<count($pd); $i++){
				$date = date_create($pd[$i]['ProgressDeletion']['created']);
				$pd[$i]['ProgressDeletion']['d'] = $date->format('Y').'-'.$date->format('m');
				if(date('Y-m')==$pd[$i]['ProgressDeletion']['d']) $pdCounter++;
			}
			$urSecCounter = 0;
			$urSecAvg = 0;
			$pSsum = 0;
			$pFsum = 0;
			for($i=0; $i<count($ts); $i++){
				$tss = 0;
				if($ts[$i]['Tsumego']['seconds']=='' || $ts[$i]['Tsumego']['seconds']==0){
					if($ts[$i]['Tsumego']['status']=='S' || $ts[$i]['Tsumego']['status']=='C' || $ts[$i]['Tsumego']['status']=='W')
						$tss = 60;
					else
						$tss = 0;
				}else{
					$tss = $ts[$i]['Tsumego']['seconds'];
				}
				$urSecAvg += $tss;
				$urSecCounter++;
				
				$tss2 = 'F';
				if($ts[$i]['Tsumego']['performance']==''){
					if($ts[$i]['Tsumego']['status']=='S' || $ts[$i]['Tsumego']['status']=='C' || $ts[$i]['Tsumego']['status']=='W')
						$tss2 = 'F';
					else
						$tss2 = '';
				}else{
					$tss2 = $ts[$i]['Tsumego']['performance'];
				}
				$pS = substr_count($tss2, '1');
				$pF = substr_count($tss2, 'F');
				$pSsum += $pS;
				$pFsum += $pF;
			}
			if($urSecCounter==0)
				$avgTime = 60;
			else
				$avgTime = round($urSecAvg/$urSecCounter, 2);
			if($pSsum+$pFsum == 0)
				$accuracy = 0;
			else
				$accuracy = round($pSsum/($pSsum+$pFsum)*100, 2);
			$avgTime2 = $avgTime; 
			$achievementUpdate2 = array();
			if($set['Set']['solved']>=100){
				if($set['Set']['id']!=210){
					$this->updateAchievementConditions($set['Set']['id'], $avgTime2, $accuracy);
					$achievementUpdate1 = $this->checkSetAchievements($set['Set']['id']);
				}
				if($id==50||$id==52||$id==53||$id==54){
					$achievementUpdate2 = $this->setAchievementSpecial('cc1');
				}else if($id==41||$id==49||$id==65||$id==66){
					$achievementUpdate2 = $this->setAchievementSpecial('cc2');
				}else if($id==186||$id==187||$id==196||$id==203){
					$achievementUpdate2 = $this->setAchievementSpecial('cc3');
				}else if($id==190||$id==193||$id==198){
					$achievementUpdate2 = $this->setAchievementSpecial('1000w1');
				}else if($id==216){
					$achievementUpdate2 = $this->setAchievementSpecial('1000w2');
				}
				$achievementUpdate = array_merge($achievementUpdate1, $achievementUpdate2);
			}
			if(count($achievementUpdate)>0) $this->updateXP($_SESSION['loggedInUser']['User']['id'], $achievementUpdate);
			
			$acS = $this->AchievementCondition->find('first', array('order' => 'value ASC', 'conditions' => array(
				'set_id' => $id, 'user_id' => $_SESSION['loggedInUser']['User']['id'], 'category' => 's'
			)));
			$acA = $this->AchievementCondition->find('first', array('order' => 'value DESC', 'conditions' => array(
				'set_id' => $id, 'user_id' => $_SESSION['loggedInUser']['User']['id'], 'category' => '%'
			)));
		}else{
			$scoring = false;
		}
		$tooltipSgfs = array();
		$tooltipInfo = array();
		$tooltipBoardSize = array();
		for($i=0; $i<count($ts); $i++){
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		
		$allTags = $this->TagName->find('all');
		$allTagsSorted = array();
		$allTagsKeys = array();
		for($i=0;$i<count($allTags);$i++){
			array_push($allTagsSorted, $allTags[$i]['TagName']['name']);
			$allTagsKeys[$allTags[$i]['TagName']['name']] = $allTags[$i];
		}
		sort($allTagsSorted);
		$s2Tags = array();
		for($i=0;$i<count($allTagsSorted);$i++)
			array_push($s2Tags, $allTagsKeys[$allTagsSorted[$i]]);

		$allTags = $s2Tags;

		if($_SESSION['loggedInUser']['User']['isAdmin']>0){
			if(isset($_COOKIE['addTag']) && $_COOKIE['addTag'] != 0){
				if($this->params['url']['hash'] == '32bb90e8976aab5298d5da10fe66f21d'){
					$newAddTag = explode("-", $_COOKIE['addTag']);
					$tagSetId = $newAddTag[0];
					$newTagName = $this->TagName->find('first', array('conditions' => array('name' => str_replace($tagSetId.'-', '', $_COOKIE['addTag']))));
					$tagSc = $this->findTsumegoSet($tagSetId);
					for($i=0; $i<count($tagSc); $i++){
						$tagAlreadyThere = $this->Tag->find('first', array('conditions' => array(
							'tsumego_id' => $tagSc[$i]['Tsumego']['id'], 'tag_name_id' => $newTagName['TagName']['id']
						)));
						if($tagAlreadyThere == null){
							$saveTag = array();
							$saveTag['Tag']['tag_name_id'] = $newTagName['TagName']['id'];
							$saveTag['Tag']['tsumego_id'] = $tagSc[$i]['Tsumego']['id'];
							$saveTag['Tag']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
							$saveTag['Tag']['approved'] = 1;
							$this->Tag->create();
							$this->Tag->save($saveTag);
						}
					}
					$this->set('removeCookie', 'addTag');
				}
			}
		}
		
		if($viewType == 'topics'){
			$this->set('tfs', $tfs[count($tfs)-1]);
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

		$this->set('search1', $search1);
		$this->set('search2', $search2);
		$this->set('search3', $search3);
		$this->set('query', $query);
		$this->set('viewType', $viewType);
		$this->set('allTags', $allTags);
		$this->set('ts', $ts);
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
	
	public function download_archive(){
		$s = $this->Set->find('all', array('order' => 'id ASC', 'conditions' => array(
			'OR' => array(
				array('public' => 1),
				array('public' => 0)
			)
		)));
		
		$text = file_get_contents('download_archive.txt');
		
		
		$text2 = $text+1;
		file_put_contents('download_archive.txt', $text2);
		
		$this->set('text', $text);
		$this->set('s', $s);
	}
	
	public function download_archive2($id=null){
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$this->loadModel('Sgf');
		
		$title='';
		$t=array();
		
		if($_SESSION['loggedInUser']['User']['id']==72){
			$s = $this->Set->findById($id);
			$title = $s['Set']['title'].' '.$s['Set']['title2'];
			
			$title = str_replace(':', '', $title);
			
			if($s['Set']['public']!=1)
				$title .= ' (sandbox)';
			
			mkdir('download_archive/'.$title);
			
			$ts = array();
			$scTs = $this->SetConnection->find('all', array('conditions' => array('set_id' => $id)));
			
			for($i=0; $i<count($scTs); $i++){
				$scT = $this->Tsumego->findById($scTs[$i]['SetConnection']['tsumego_id']);
				$scT['Tsumego']['set_id'] = $scTs[$i]['SetConnection']['set_id'];
				$scT['Tsumego']['num'] = $scTs[$i]['SetConnection']['num'];
				$scT['Tsumego']['duplicateLink'] = '';
				$scTs2 = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $scT['Tsumego']['id'])));
				for($j=0;$j<count($scTs2);$j++)
					if(count($scTs2)>1 && $scTs2[$j]['SetConnection']['set_id']==$set['Set']['id'])
						$scT['Tsumego']['duplicateLink'] = '?sid='.$scT['Tsumego']['set_id'];
				
				array_push($ts, $scT);
			}
			
			$tsBuffer = array();
			$tsBufferLowest=10000;
			$tsBufferHighest=0;
			for($i=0; $i<count($ts); $i++){
				$tsBuffer[$ts[$i]['Tsumego']['num']] = $ts[$i];
				if($ts[$i]['Tsumego']['num']<$tsBufferLowest)
					$tsBufferLowest = $ts[$i]['Tsumego']['num'];
				if($ts[$i]['Tsumego']['num']>$tsBufferHighest)
					$tsBufferHighest = $ts[$i]['Tsumego']['num'];
			}
			
			
			$t = array();
			for($i=$tsBufferLowest; $i<=$tsBufferHighest; $i++)
				if(isset($tsBuffer[$i]))
					array_push($t, $tsBuffer[$i]);
			
			for($i=0; $i<count($t); $i++){
				$t[$i]['Tsumego']['title'] = $s['Set']['title'].' '.$t[$i]['Tsumego']['num'];
				$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $t[$i]['Tsumego']['id'])));
				$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
				//$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
				$t[$i]['Tsumego']['sgf'] = $sgf['Sgf']['sgf'];
				
				file_put_contents('download_archive/'.$title.'/'.$t[$i]['Tsumego']['title'].'.sgf', $t[$i]['Tsumego']['sgf']);
			}
		}
		$text = file_get_contents('download_archive.txt');
		
		$sAll = $this->Set->find('all', array('order' => 'id ASC', 'conditions' => array(
			'OR' => array(
				array('public' => 1),
				array('public' => 0)
			)
		)));

		$this->set('s', $sAll);
		$this->set('text', $text);
		$this->set('title', $title);
		$this->set('t', $t);
	}
	
	public function updateAchievementConditions($sid, $avgTime, $accuracy){
		$uid = $_SESSION['loggedInUser']['User']['id'];
		$acS = $this->AchievementCondition->find('first', array('order' => 'value ASC', 'conditions' => array('set_id' => $sid, 'user_id' => $uid, 'category' => 's')));
		$acA = $this->AchievementCondition->find('first', array('order' => 'value DESC', 'conditions' => array('set_id' => $sid, 'user_id' => $uid, 'category' => '%')));
		
		if($acS==null){
			$aCond = array();
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $avgTime;
			$aCond['AchievementCondition']['category'] = 's';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		}else{
			if($avgTime < $acS['AchievementCondition']['value']){
				$acS['AchievementCondition']['value'] = $avgTime;
				$this->AchievementCondition->save($acS);
			}
		}
		if($acA==null){
			$aCond = array();
			$aCond['AchievementCondition']['user_id'] = $uid;
			$aCond['AchievementCondition']['set_id'] = $sid;
			$aCond['AchievementCondition']['value'] = $accuracy;
			$aCond['AchievementCondition']['category'] = '%';
			$this->AchievementCondition->create();
			$this->AchievementCondition->save($aCond);
		}else{
			if($accuracy > $acA['AchievementCondition']['value']){
				$acA['AchievementCondition']['value'] = $accuracy;
				$this->AchievementCondition->save($acA);
			}
		}
	}
	
	public function beta2(){
		$this->LoadModel('User');
		$this->LoadModel('Tsumego');
		$this->LoadModel('TsumegoStatus');

		$_SESSION['page'] = 'sandbox';
		$_SESSION['title'] = 'Deleted Collections';

		if(isset($this->params['url']['remove'])){
			$remove = $this->Set->findById($this->params['url']['remove']);
			if($remove['Set']['public']==0){
				$remove['Set']['public']=-1;
				$this->Set->save($remove);
			}
		}

		$setsX = $this->Set->find('all', array(
			'order' => array('Set.order'),
			'conditions' => array('public' => -1)
		));

		$secretPoints = 0;
		$removeMap = array();

		$sets = array();
		for($i=0; $i<count($setsX); $i++){
			if(!isset($removeMap[$setsX[$i]['Set']['id']])) array_push($sets, $setsX[$i]);
		}
		if(isset($_SESSION['loggedInUser'])){
			$uts = $this->TsumegoStatus->find('all', array('conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
			$utsMap = array();
			for($l=0; $l<count($uts); $l++){
				$utsMap[$uts[$l]['TsumegoStatus']['tsumego_id']] = $uts[$l]['TsumegoStatus']['status'];
			}
		}

		for($i=0; $i<count($sets); $i++){
			$ts = $this->findTsumegoSet($sets[$i]['Set']['id']);	
			$sets[$i]['Set']['anz'] = count($ts);
			$counter = 0;

			if(isset($_SESSION['loggedInUser'])){
				for($k=0; $k<count($ts); $k++){
					if(isset($utsMap[$ts[$k]['Tsumego']['id']])){
						if($utsMap[$ts[$k]['Tsumego']['id']] == 'S' || $utsMap[$ts[$k]['Tsumego']['id']] == 'W' || $utsMap[$ts[$k]['Tsumego']['id']] == 'C'){
							$counter++;
							$globalSolvedCounter++;
						}
					}
				}
			}else{
				if(isset($_SESSION['noLogin'])){
					$noLogin = $_SESSION['noLogin'];
					$noLoginStatus = $_SESSION['noLoginStatus'];
					for($g=0; $g<count($noLogin); $g++){
						for($f=0; $f<count($ts); $f++){
							if($ts[$f]['Tsumego']['id']==$noLogin[$g]){
								$ts[$f]['Tsumego']['status'] = $noLoginStatus[$g];
								if($noLoginStatus[$g]=='S' || $noLoginStatus[$g]=='W' || $noLoginStatus[$g]=='C'){
									$counter++;
								}
							}
						}
					}
				}
			}

			$date = new DateTime($sets[$i]['Set']['created']);
			$month = date("F", strtotime($sets[$i]['Set']['created']));
			$setday = $date->format('d. ');
			$setyear = $date->format('Y');
			if($setday[0]==0) $setday = substr($setday, -3);
			$sets[$i]['Set']['created'] = $date->format('Ymd');
			$sets[$i]['Set']['createdDisplay'] = $setday.$month.' '.$setyear;

			if(count($ts)>0)
				$percent = $counter/count($ts)*100;
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

	private function findUt($id=null, $allUts=null, $map=null){
		$currentUt = array_search($id, $map);
		$ut = $allUts[$currentUt];
		if($currentUt==0) if($id!=$map[0]) $ut = null;
		return $ut;
	}

	private function getDifficultyColor($difficulty = null){
		if($difficulty==1) return '#33cc33';
		if($difficulty==2) return '#709533';
		if($difficulty==3) return '#2e3370';
		if($difficulty==4) return '#ac5d33';
		if($difficulty==5) return '#e02e33';
		return 'white';
	}

	private function getSizeColor($size = null){
		$colors = array();
		array_push($colors, '#cc6600');
		array_push($colors, '#ac4e26');
		array_push($colors, '#963e3e');
		array_push($colors, '#802e58');
		array_push($colors, '#60167d');
		if($size<30) return $colors[0];
		else if($size<60) return $colors[1];
		else if($size<110) return $colors[2];
		else if($size<202) return $colors[3];
		else return $colors[4];
	}

	private function getDateColor($date = null){
		$current = '20180705';
		$dist = $current - $date;

		if($dist<7) return '#0033cc';
		if($dist<100) return '#0f33ad';
		if($dist<150) return '#1f338f';
		if($dist<200) return '#2e3370';
		if($dist<300) return '#3d3352';
		if($dist<400) return '#4c3333';
		if($dist<500) return '#57331f';
		return '#663300';
	}

	private function getSolvedColor($percent = null){
		$colors = array();

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
		for($i=0; $i<count($colors); $i++){
			if($percent<=$steps) return $colors[$i];
			$steps += 2.5;
		}
		return '#333333';
	}

	private function getExistingRanksArray(){
		$this->loadModel('Tsumego');
		$ranksArray = array();
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
		$nine = $this->Tsumego->find('first', array('conditions' => array(
			'elo_rating_mode >=' => $this->getTsumegoElo('9d'),
		)));
		if($nine!=null){
			$ranksArray[23]['rank'] = '9d';
			$ranksArray[23]['color'] = 'rgba(244, 88, 88, [o])';
		}
		return $ranksArray;
	}
	private function getTagColor($pos){
		$c = array();
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

?>
