<?php
class TsumegosController extends AppController{
  public $helpers = array('Html', 'Form');

	public function play($id=null){
		$_SESSION['page'] = 'play';
		$this->loadModel('User');
		$this->LoadModel('Set');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Comment');
		$this->LoadModel('UserBoard');
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('Favorite');
		$this->LoadModel('AdminActivity');
		$this->LoadModel('Activate');
		$this->LoadModel('Joseki');
		$this->LoadModel('Reputation');
		$this->LoadModel('Rank');
		$this->LoadModel('RankSetting');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('ProgressDeletion');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('TsumegoVariant');
		$this->loadModel('Signature');
		$this->loadModel('Tag');
		$this->loadModel('TagName');
		$this->LoadModel('UserContribution');
		
		$onlineMode = true;
		$noUser;
		$noLogin;
		$noLoginStatus;
		$rejuvenation = false;
		$doublexp = null;
		$exploit = null;
		$dailyMaximum = false;
		$suspiciousBehavior = false;
		$refinementUT = null;
		$half = '';
		$inFavorite = false;
		$lastInFav = 0;
		$isSandbox = false;
		$goldenTsumego = false;
		$refresh = null;
		$mode = 1;
		$noTr = false;
		$range = array();
		$difficulty = 4;
		$oldmin = -600;
		$oldmax = 500;
		$newmin = 0.1;
		$newmax = 2;
		$potion = 0;
		$potionPercent = 0;
		$potionPercent2 = 0;
		$potionSuccess = false;
		$potionActive = false;
		$reviewCheat = false;
		$commentCoordinates = array();
		$josekiLevel = 1;
		$rankTs = array();
		$ranks = array();
		$firstRanks = 0;
		$currentRank = null;
		$currentRankNum = 0;
		$r10 = 0;
		$stopParameter = 0;
		$stopParameter2 = 0;
		$mode3ScoreArray = array();
		$trs = array();
		$potionAlert = false;
		$ui = 2;
		$eloScore = 0;
		$eloScore2 = 0;
		$requestProblem = '';
		$achievementUpdate = array();
		$pdCounter = 0;
		$duplicates = array();
		$preSc = array();
		$tRank = '15k';
		$requestSolution = false;
		$currentRank2 = null;
		$nothingInRange = false;
		$avActive = true;
		$avActive2 = true;
		$utsMap = array();
		$allUts = array();
		$setsWithPremium = array();
		$queryTitle = '';
		$queryTitleSets = '';
		$amountOfOtherCollection = 200;
		$partition = -1;

		if(isset($this->params['url']['sid']))
			if(strpos($this->params['url']['sid'], '?')>0)
				$id = 15352;
		
		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			$hasPremium = false;
		else
			$hasPremium = true;
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++)
			array_push($setsWithPremium, $swp[$i]['Set']['id']);

		$hasDuplicateGroup = count($this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $id))))>1;
		if($hasDuplicateGroup){
			$duplicates = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $id)));
			for($i=0;$i<count($duplicates);$i++){
				$duplicateSet = $this->Set->findById($duplicates[$i]['SetConnection']['set_id']);
				$duplicates[$i]['SetConnection']['title'] = $duplicateSet['Set']['title'].' '.$duplicates[$i]['SetConnection']['num'];
			}
		}
		$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id)));
		if($scT==null){
			$id = 15352;
			$hasDuplicateGroup = count($this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $id))))>1;
		}
		$tv = $this->TsumegoVariant->find('first', array('conditions' => array('tsumego_id' => $id)));
		
		if(isset($this->params['url']['requestSolution'])){
			$requestSolutionUser = $this->User->findById($this->params['url']['requestSolution']);
			if($requestSolutionUser['User']['isAdmin']>=1){
				$requestSolution = true;
				$adminActivity = array();
				$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$adminActivity['AdminActivity']['tsumego_id'] = $id;
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'requested solution';
				$this->AdminActivity->create();
				$this->AdminActivity->save($adminActivity);
			}
		}
		
		if(isset($this->params['url']['potionAlert']))
			$potionAlert = true;
		if(isset($_COOKIE['ui']) && $_COOKIE['ui'] != '0'){
			$ui = $_COOKIE['ui'];
			unset($_COOKIE['ui']);
		}
		if(isset($this->params['url']['modelink'])){
			if($this->params['url']['modelink']==1) $tlength=15;
			elseif($this->params['url']['modelink']==2) $tlength=16;
			elseif($this->params['url']['modelink']==3) $tlength=17;
			if(isset($this->params['url']['modelink'])) $_COOKIE['mode'] = 3;
			
			$tcharacters = '0123456789abcdefghijklmnopqrstuvwxyz';
			$tcharactersLength = strlen($tcharacters);
			$trandomString = '';
			for($i=0;$i<$tlength;$i++){
				$trandomString .= $tcharacters[rand(0, $tcharactersLength - 1)];
			}
			$_SESSION['loggedInUser']['User']['activeRank'] = $trandomString;
			$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$u['User']['activeRank'] =  $trandomString;
			$this->User->save($u);
		}
		$searchPatameters = $this->processSearchParameters($_SESSION['loggedInUser']['User']['id']);
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];

		if(isset($this->params['url']['search'])){
			if($this->params['url']['search']=='topics'){
				$query = $this->params['url']['search'];
				$_COOKIE['query'] = $this->params['url']['search'];
				$this->processSearchParameters($_SESSION['loggedInUser']['User']['id']);
			}
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$_SESSION['loggedInUser']['User']['mode'] = 1;
			$u =  $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			if(isset($_COOKIE['mode']) && $_COOKIE['mode'] != '0'){
				if(strlen($_SESSION['loggedInUser']['User']['activeRank'])>=15){
					if($_COOKIE['mode']!=3){//switch 3=>2, 3=>1
						$ranks = $this->Rank->find('all', array('conditions' => array('session' => $_SESSION['loggedInUser']['User']['activeRank'])));
						if(count($ranks)!=10){
							for($i=0;$i<count($ranks);$i++){
								$this->Rank->delete($ranks[$i]['Rank']['id']);
							}
						}
						$_SESSION['loggedInUser']['User']['activeRank'] = 0;
						$u['User']['activeRank'] = 0;
						$this->User->save($u);
					}
				}else{
					if($_COOKIE['mode']==3){
						$_COOKIE['mode'] = 1;
					}
				}
				$_SESSION['loggedInUser']['User']['mode'] = $_COOKIE['mode'];
				
				$mode = $_COOKIE['mode'];
			}
			unset($_COOKIE['mode']);
		}else{
			$nextMode = $this->Tsumego->findById(15352);
			$mode = 1;
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if(strlen($_SESSION['loggedInUser']['User']['activeRank'])>=15){
				if(strlen($_SESSION['loggedInUser']['User']['activeRank'])==15){
					$stopParameter = 10;
					$stopParameter2 = 0;
				}elseif(strlen($_SESSION['loggedInUser']['User']['activeRank'])==16){
					$stopParameter = 10;
					$stopParameter2 = 1;
				}elseif(strlen($_SESSION['loggedInUser']['User']['activeRank'])==17){
					$stopParameter = 10;
					$stopParameter2 = 2;
				}
				$mode = 3;
				$_SESSION['loggedInUser']['User']['mode'] = 3;
				$ranks = $this->Rank->find('all', array('conditions' =>  array('session' => $_SESSION['loggedInUser']['User']['activeRank'])));
				if(count($ranks)==0){
					$r = $this->params['url']['rank'];
					if($r=='5d'){ $r1=2500; $r2=10000; }
					elseif($r=='4d'){ $r1=2400; $r2=2500; }
					elseif($r=='3d'){ $r1=2300; $r2=2400; }
					elseif($r=='2d'){ $r1=2200; $r2=2300; }
					elseif($r=='1d'){ $r1=2100; $r2=2200; }
					elseif($r=='1k'){ $r1=2000; $r2=2100; }
					elseif($r=='2k'){ $r1=1900; $r2=2000; }
					elseif($r=='3k'){ $r1=1800; $r2=1900; }
					elseif($r=='4k'){ $r1=1700; $r2=1800; }
					elseif($r=='5k'){ $r1=1600; $r2=1700; }
					elseif($r=='6k'){ $r1=1500; $r2=1600; }
					elseif($r=='7k'){ $r1=1400; $r2=1500; }
					elseif($r=='8k'){ $r1=1300; $r2=1400; }
					elseif($r=='9k'){ $r1=1200; $r2=1300; }
					elseif($r=='10k'){ $r1=1100; $r2=1200; }
					elseif($r=='11k'){ $r1=1000; $r2=1100; }
					elseif($r=='12k'){ $r1=900; $r2=1000; }
					elseif($r=='13k'){ $r1=800; $r2=900; }
					elseif($r=='14k'){ $r1=700; $r2=800; }
					elseif($r=='15k'){ $r1=0; $r2=700; }
					else{ $r1=0; $r2=700; }
					
					$rs = $this->RankSetting->find('all', array('conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
					$allowedRs = array();
					$rankTs = array();
					for($i=0; $i<count($rs); $i++){
						$timeSc = $this->findTsumegoSet($rs[$i]['RankSetting']['set_id']);
						for($g=0; $g<count($timeSc); $g++)
							if($timeSc[$g]['Tsumego']['elo_rating_mode']>=$r1 && $timeSc[$g]['Tsumego']['elo_rating_mode']<$r2)
								if(!in_array($timeSc[$g]['Tsumego']['set_id'], $setsWithPremium) || $hasPremium)
									array_push($rankTs, $timeSc[$g]);	
					}
					shuffle($rankTs);
					for($i=0; $i<$stopParameter; $i++){
						$rm = array();
						$rm['Rank']['session'] = $_SESSION['loggedInUser']['User']['activeRank'];
						$rm['Rank']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$rm['Rank']['tsumego_id'] = $rankTs[$i]['Tsumego']['id'];
						if($rm['Rank']['tsumego_id']==null) $rm['Rank']['tsumego_id'] = 5127;
						$rm['Rank']['rank'] = $r;
						$rm['Rank']['num'] = $i+1;
						$rm['Rank']['currentNum'] = 1;
						$this->Rank->create();
						$this->Rank->save($rm);
					}
					$currentRankNum = 1;
					$firstRanks = 1;
				}else{
					for($i=0; $i<count($ranks); $i++){
						$ranks[$i]['Rank']['currentNum']++;
						$this->Rank->save($ranks[$i]);
					}
					$currentNum = $ranks[0]['Rank']['currentNum'];
					$tsid = null;
					$tsid2 = null;
					for($i=0; $i<count($ranks); $i++){
						if($ranks[$i]['Rank']['num'] == $currentNum){
							$tsid = $ranks[$i]['Rank']['tsumego_id'];
							if($currentNum<10)
								$tsid2 = $ranks[$i+1]['Rank']['tsumego_id'];
							else
								$tsid2 = $ranks[$i]['Rank']['tsumego_id'];
						}
					}
					$currentRank = $this->Tsumego->findById($tsid);
					$currentRank2 = $this->Tsumego->findById($tsid2);
					$firstRanks = 2;
					if($currentNum==$stopParameter+1)
						$r10=1;
					$currentRankNum = $currentNum;
				}
			}
		}
		if(isset($this->params['url']['refresh']))
			$refresh = $this->params['url']['refresh'];
		
		if(!is_numeric($id)) $id = 15352;
		if(!empty($rankTs)){
			$id = $rankTs[0]['Tsumego']['id'];
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id)));
			$mode = 3;
			$currentRank2 = $rankTs[1]['Tsumego']['id'];
		}elseif($firstRanks==2){
			$id = $currentRank['Tsumego']['id'];
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id)));
			$mode = 3;
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['mode']==0)
				$_SESSION['loggedInUser']['User']['mode'] = 1;
			if(isset($this->params['url']['mode'])){
				$_SESSION['loggedInUser']['User']['mode'] = $this->params['url']['mode'];	
				$mode = $this->params['url']['mode'];
			}
			$difficulty = $u['User']['t_glicko'];
			if(isset($_COOKIE['difficulty']) && $_COOKIE['difficulty'] != '0'){
				$difficulty = $_COOKIE['difficulty'];
				$u['User']['t_glicko'] = $_COOKIE['difficulty'];
				unset($_COOKIE['difficulty']);
			}
			if($mode==2){
				if($difficulty==1) $adjustDifficulty = -450;
				elseif($difficulty==2) $adjustDifficulty = -300;
				elseif($difficulty==3) $adjustDifficulty = -150;
				elseif($difficulty==4) $adjustDifficulty = 0;
				elseif($difficulty==5) $adjustDifficulty = 150;
				elseif($difficulty==6) $adjustDifficulty = 300;
				elseif($difficulty==7) $adjustDifficulty = 450;
				else $adjustDifficulty = 0;
				
				$eloRange = $u['User']['elo_rating_mode'] + $adjustDifficulty;
				$eloRangeMin = $eloRange-240;
				$eloRangeMax = $eloRange+240;
				
				$range = $this->Tsumego->find('all', array('conditions' => array(
					'elo_rating_mode >=' => $eloRangeMin,
					'elo_rating_mode <=' => $eloRangeMax,
				)));
				shuffle($range);
				$ratingFound = false;
				$nothingInRange = false;
				$ratingFoundCounter = 0;
				while(!$ratingFound){
					if($ratingFoundCounter<count($range)){
						$rafSc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $range[$ratingFoundCounter]['Tsumego']['id'])));
						$raS = $this->Set->findById($rafSc['SetConnection']['set_id']);
						if($raS['Set']['public']==1){
							if($raS['Set']['id']!=210 && $raS['Set']['id']!=191 && $raS['Set']['id']!=181 && $raS['Set']['id']!=207 && $raS['Set']['id']!=172 && 
							$raS['Set']['id']!=202 && $raS['Set']['id']!=237 && $raS['Set']['id']!=81578 && $raS['Set']['id']!=74761 && $raS['Set']['id']!=71790 && $raS['Set']['id']!=33007 && 
							$raS['Set']['id']!=31813 && $raS['Set']['id']!=29156 && $raS['Set']['id']!=11969 && $raS['Set']['id']!=6473){
								if(!in_array($raS['Set']['id'], $setsWithPremium) || $hasPremium)
									$ratingFound = true;
							}
						}
						if($ratingFound==false)
							$ratingFoundCounter++;
					}else{
						$nothingInRange = 'No problem found.';
						break;
					}
				}
				$nextMode = $range[$ratingFoundCounter];
				
				$ratingCookieScore = false;
				$ratingCookieMisplay = false;
				$ratingCookiePreId = false;
				if(isset($_COOKIE['score']) && $_COOKIE['score']!='0')
					$ratingCookieScore = true;
				if(isset($_COOKIE['misplay']) && $_COOKIE['misplay']!='0')
					$ratingCookieMisplay = true;
				if(isset($_COOKIE['preId']) && $_COOKIE['preId']!='0')
					$ratingCookiePreId = true;
				if(isset($_COOKIE['ratingModePreId']) && $_COOKIE['ratingModePreId']!='0'){
					if(!$ratingCookiePreId && !$ratingCookieScore && !$ratingCookieMisplay){
						$nextMode = $this->Tsumego->findById($_COOKIE['ratingModePreId']);
						unset($_COOKIE['ratingModePreId']);
					}
				}
				$id = $nextMode['Tsumego']['id'];
				$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id)));
			}
		}
		
		$t = $this->Tsumego->findById($id);//the tsumego
		$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

		if($t['Tsumego']['elo_rating_mode'] < 1000) $t = $this->checkEloAdjust($t);

		$activityValue = $this->getActivityValue($_SESSION['loggedInUser']['User']['id'], $t['Tsumego']['id']);
		$eloDifference = abs($_SESSION['loggedInUser']['User']['elo_rating_mode'] - $t['Tsumego']['elo_rating_mode']);
		
		if($_SESSION['loggedInUser']['User']['elo_rating_mode'] > $t['Tsumego']['elo_rating_mode']){
			$eloBigger = 'u';
			if($eloDifference > 1000)
				$avActive2 = false;
		}else
			$eloBigger = 't';
		$newUserEloW = $this->getNewElo($eloDifference, $eloBigger, $activityValue[0], $t['Tsumego']['id'], 'w');
		$newUserEloL = $this->getNewElo($eloDifference, $eloBigger, $activityValue[0], $t['Tsumego']['id'], 'l');
		
		if($activityValue[1]==0 && $avActive2){
			$eloScore = $newUserEloW['user'];
			$eloScore2 = $newUserEloL['user'];
			$avActive = true;
		}else{
			$eloScore = 0;
			$eloScore2 = 0;
			$avActive = false;
		}
		if(isset($this->params['url']['sid'])){
			$sc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id, 'set_id' => $this->params['url']['sid'])));
			if($sc!=null){
				$t['Tsumego']['set_id'] = $this->params['url']['sid'];
				$t['Tsumego']['num'] = $sc['SetConnection']['num'];
				if(!$hasDuplicateGroup)
					$t['Tsumego']['duplicateLink'] = '';
				else
					$t['Tsumego']['duplicateLink'] = '?sid='.$t['Tsumego']['set_id'];
			}
		}
		$tRank = $this->getTsumegoRank($t['Tsumego']['elo_rating_mode']);
		
		if($t['Tsumego']['duplicate']>9){//duplicate and not main
			$tDuplicate = $this->Tsumego->findById($t['Tsumego']['duplicate']);
			$t['Tsumego']['difficulty'] = $tDuplicate['Tsumego']['difficulty'];
			$t['Tsumego']['description'] = $tDuplicate['Tsumego']['description'];
			$t['Tsumego']['hint'] = $tDuplicate['Tsumego']['hint'];
			$t['Tsumego']['author'] = $tDuplicate['Tsumego']['author'];
			$t['Tsumego']['solved'] = $tDuplicate['Tsumego']['solved'];
			$t['Tsumego']['failed'] = $tDuplicate['Tsumego']['failed'];
			$t['Tsumego']['userWin'] = $tDuplicate['Tsumego']['userWin'];
			$t['Tsumego']['userLoss'] = $tDuplicate['Tsumego']['userLoss'];
			$t['Tsumego']['alternative_response'] = $tDuplicate['Tsumego']['alternative_response'];
			$t['Tsumego']['virtual_children'] = $tDuplicate['Tsumego']['virtual_children'];
		}
		
		$fSet = $this->Set->find('first', array('conditions' => array('id' => $t['Tsumego']['set_id'])));
		if($t==null) $t = $this->Tsumego->findById($_SESSION['lastVisit']);
		
		if($mode==1 || $mode==3)
			$nextMode = $t;
		if(isset($this->params['url']['rcheat'])) if($this->params['url']['rcheat']==1) $reviewCheat = true;
		$this->Session->write('lastVisit', $id);
		if(!empty($this->data)){
			if(isset($this->data['Comment']['status']) && !isset($this->data['Study2'])){
				$adminActivity = array();
				$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
				$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
				$adminActivity['AdminActivity']['answer'] = $this->data['Comment']['status'];
				$this->AdminActivity->save($adminActivity);
				$this->Comment->save($this->data, true);
			}elseif(isset($this->data['Comment']['modifyDescription'])){
				$adminActivity = array();
				$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
				$adminActivity['AdminActivity']['file'] = 'description';
				$adminActivity['AdminActivity']['answer'] = 'Description: '.$this->data['Comment']['modifyDescription'].' '.$this->data['Comment']['modifyHint'];
				$t['Tsumego']['description'] = $this->data['Comment']['modifyDescription'];
				$t['Tsumego']['hint'] = $this->data['Comment']['modifyHint'];
				$t['Tsumego']['author'] = $this->data['Comment']['modifyAuthor'];
				if($this->data['Comment']['modifyElo']<2900)
					$t['Tsumego']['elo_rating_mode'] = $this->data['Comment']['modifyElo'];
				if($t['Tsumego']['elo_rating_mode']>100)
					$this->Tsumego->save($t, true);
				
				if($this->data['Comment']['deleteProblem']=='delete'){
					$adminActivity['AdminActivity']['answer'] = 'Problem deleted. ('.$t['Tsumego']['set_id'].'-'.$t['Tsumego']['id'].')';
					$adminActivity['AdminActivity']['file'] = '/delete';
				}
				if($this->data['Comment']['deleteTag']!=null){
					$tagsToDelete = $this->Tag->find('all', array('conditions' => array('tsumego_id' => $id)));
					for($i=0; $i<count($tagsToDelete); $i++){
						$tagNameForDelete = $this->TagName->findById($tagsToDelete[$i]['Tag']['tag_name_id']);
						if($tagNameForDelete['TagName']['name'] == $this->data['Comment']['deleteTag'])
							$this->Tag->delete($tagsToDelete[$i]['Tag']['id']);
					}
				}
				$this->AdminActivity->save($adminActivity);
			}elseif(isset($this->data['Study'])){
				$tv['TsumegoVariant']['answer1'] = $this->data['Study']['study1'];
				$tv['TsumegoVariant']['answer2'] = $this->data['Study']['study2'];
				$tv['TsumegoVariant']['answer3'] = $this->data['Study']['study3'];
				$tv['TsumegoVariant']['answer4'] = $this->data['Study']['study4'];
				$tv['TsumegoVariant']['explanation'] = $this->data['Study']['explanation'];
				$tv['TsumegoVariant']['numAnswer'] = $this->data['Study']['studyCorrect'];
				$this->TsumegoVariant->save($tv);
			}elseif(isset($this->data['Study2'])){
				$tv['TsumegoVariant']['winner'] = $this->data['Study2']['winner'];
				$tv['TsumegoVariant']['answer1'] = $this->data['Study2']['answer1'];
				$tv['TsumegoVariant']['answer2'] = $this->data['Study2']['answer2'];
				$tv['TsumegoVariant']['answer3'] = $this->data['Study2']['answer3'];
				$this->TsumegoVariant->save($tv);
			}elseif(isset($this->data['Settings'])){
				if($this->data['Settings']['r38']=='on' && $t['Tsumego']['virtual_children']!=1){
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Turned on merge recurring positions';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if($this->data['Settings']['r38']=='off' && $t['Tsumego']['virtual_children']!=0){
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Turned off merge recurring positions';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if($this->data['Settings']['r39']=='on' && $t['Tsumego']['alternative_response']!=1){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Turned on alternative response mode';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
				}
				if($this->data['Settings']['r39']=='off' && $t['Tsumego']['alternative_response']!=0){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Turned off alternative response mode';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
				}
				if($this->data['Settings']['r43']=='no' && $t['Tsumego']['pass']!=0){
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Disabled passing';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if($this->data['Settings']['r43']=='yes' && $t['Tsumego']['pass']!=1){
					$adminActivity = array();
					$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Enabled passing';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if($this->data['Settings']['r41']=='yes' && $tv==null){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to multiple choice';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$tv1 = array();
					$tv1['TsumegoVariant']['tsumego_id'] = $id;
					$tv1['TsumegoVariant']['type'] = 'multiple_choice';
					$tv1['TsumegoVariant']['answer1'] = 'Black is dead';
					$tv1['TsumegoVariant']['answer2'] = 'White is dead';
					$tv1['TsumegoVariant']['answer3'] = 'Ko';
					$tv1['TsumegoVariant']['answer4'] = 'Seki';
					$tv1['TsumegoVariant']['numAnswer'] = '1';
					$this->TsumegoVariant->create();
					$this->TsumegoVariant->save($tv1);
				}
				if($this->data['Settings']['r41']=='no' && $tv!=null){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Deleted multiple choice problem type';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$this->TsumegoVariant->delete($tv['TsumegoVariant']['id']);
					$tv = null;
				}
				if($this->data['Settings']['r42']=='yes' && $tv==null){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to score estimating';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$tv1 = array();
					$tv1['TsumegoVariant']['tsumego_id'] = $id;
					$tv1['TsumegoVariant']['type'] = 'score_estimating';
					$tv1['TsumegoVariant']['numAnswer'] = '0';
					$this->TsumegoVariant->create();
					$this->TsumegoVariant->save($tv1);
				}
				if($this->data['Settings']['r42']=='no' && $tv!=null){
					$adminActivity2 = array();
					$adminActivity2['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Deleted score estimating problem type';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$this->TsumegoVariant->delete($tv['TsumegoVariant']['id']);
					$tv = null;
				}
				if($this->data['Settings']['r38'] == 'on') $t['Tsumego']['virtual_children'] = 1;
				else $t['Tsumego']['virtual_children'] = 0;
				if($this->data['Settings']['r39'] == 'on') $t['Tsumego']['alternative_response'] = 1;
				else $t['Tsumego']['alternative_response'] = 0;
				if($this->data['Settings']['r43'] == 'yes') $t['Tsumego']['pass'] = 1;
				else $t['Tsumego']['pass'] = 0;
				if($this->data['Settings']['r40'] == 'on') $t['Tsumego']['duplicate'] = -1;
				else $t['Tsumego']['duplicate'] = 0;
				if($t['Tsumego']['elo_rating_mode']>100)
					$this->Tsumego->save($t, true);
			}else{
				if($this->data['Comment']['user_id']!=33){
					$this->Comment->create();
					if($this->checkCommentValid($_SESSION['loggedInUser']['User']['id'])){
						$this->Comment->save($this->data, true);
					}
				}
			}
			$this->set('formRedirect', true);
		}
		if(isset($_SESSION['loggedInUser'])){if($_SESSION['loggedInUser']['User']['isAdmin']>0){
			$aad = $this->AdminActivity->find('first', array('order' => 'id DESC'));
			if($aad['AdminActivity']['file'] == '/delete'){
				$this->set('deleteProblem2', true);
			}
		}}
		if(isset($this->params['url']['favorite']))
			$inFavorite = true;
		if(isset($this->params['url']['deleteComment'])){
			$deleteComment = $this->Comment->findById($this->params['url']['deleteComment']);
			if(isset($this->params['url']['changeComment'])){
				if($this->params['url']['changeComment']==1) $deleteComment['Comment']['status'] = 97;
				elseif($this->params['url']['changeComment']==2) $deleteComment['Comment']['status'] = 98;
				elseif($this->params['url']['changeComment']==3) $deleteComment['Comment']['status'] = 96;
				elseif($this->params['url']['changeComment']==4) $deleteComment['Comment']['status'] = 0;
			}else $deleteComment['Comment']['status'] = 99;
			$adminActivity = array();
			$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $deleteComment['Comment']['status'];
			$this->AdminActivity->save($adminActivity);
			$this->Comment->save($deleteComment);
		}
		if(isset($_FILES['game'])){
			$errors= array();
			$file_name = $_FILES['game']['name'];
			$file_size =$_FILES['game']['size'];
			$file_tmp =$_FILES['game']['tmp_name'];
			$file_type=$_FILES['game']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['game']['name'])));
			$extensions= array("sgf");
			if(in_array($file_ext,$extensions)=== false){
				$errors[]="Only SGF files are allowed.";
			}
			if($file_size > 2097152){
				$errors[]='The file is too large.';
			}
			$cox = count($this->Comment->find('all', array('conditions' => (array('tsumego_id' => $id)))));
			if(empty($fSet['Set']['title2'])) $title2 = '';
			else $title2 = '-';
			$file_name = $fSet['Set']['title'].$title2.$fSet['Set']['title2'].'-'.$t['Tsumego']['num'].'-'.$cox.'.sgf';
			$sgfComment = array();
			$this->Comment->create();
			$sgfComment['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$sgfComment['tsumego_id'] = $t['Tsumego']['id'];
			$file_name = str_replace('#', 'num', $file_name); 
			$sgfComment['message'] = '<a href="/files/ul1/'.$file_name.'">SGF</a>';
			$sgfComment['created'] = date('Y-m-d H:i:s');
			$this->Comment->save($sgfComment);
			if(empty($errors)==true){
				$uploadfile = $_SERVER['DOCUMENT_ROOT'].'/app/webroot/files/ul1/'.$file_name;
				move_uploaded_file($file_tmp, $uploadfile);
			}
		}
		if(isset($_FILES['adminUpload'])){
			$errors= array();
			$file_name = $_FILES['adminUpload']['name'];
			$file_size =$_FILES['adminUpload']['size'];
			$file_tmp =$_FILES['adminUpload']['tmp_name'];
			$file_type=$_FILES['adminUpload']['type'];
			$file_ext=strtolower(end(explode('.',$_FILES['adminUpload']['name'])));
			$extensions= array("sgf");
			if(in_array($file_ext,$extensions)=== false) $errors[]="Only SGF files are allowed.";
			if($file_size > 2097152) $errors[]='The file is too large.';
			$fSet = $this->Set->find('first', array('conditions' => array('id' => $t['Tsumego']['set_id'])));
			$this->AdminActivity->create();
			$adminActivity = array();
			$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $file_name;
			$this->AdminActivity->save($adminActivity);
			$t['Tsumego']['variance'] = 0;
			if($t['Tsumego']['elo_rating_mode']>100)
				$this->Tsumego->save($t, true);

			if(empty($errors)==true){
				if($t['Tsumego']['duplicate']<=9)
					$lastV = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $id)));
				else
					$lastV = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $t['Tsumego']['duplicate'])));
				$sgf = array();
				$sgf['Sgf']['sgf'] = file_get_contents($_FILES['adminUpload']['tmp_name']);
				$sgf['Sgf']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				
				if($t['Tsumego']['duplicate']<=9)
					$sgf['Sgf']['tsumego_id'] = $id;
				else
					$sgf['Sgf']['tsumego_id'] = $t['Tsumego']['duplicate'];

				$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, $_SESSION['loggedInUser']['User']['id']);
				$this->handleContribution($_SESSION['loggedInUser']['User']['id'], 'made_proposal');
				$this->Sgf->save($sgf);
			}
		}
		$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty']*$fSet['Set']['multiplier']);
		
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			unset($_SESSION['noUser']);
			unset($_SESSION['noLogin']);
			unset($_SESSION['noLoginStatus']);
			$pd = $this->ProgressDeletion->find('all', array('conditions' => array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'],
				'set_id' => $t['Tsumego']['set_id']
			)));
			for($i=0; $i<count($pd); $i++){
				$date = date_create($pd[$i]['ProgressDeletion']['created']);
				$pd[$i]['ProgressDeletion']['d'] = $date->format('Y').'-'.$date->format('m');
				if(date('Y-m')==$pd[$i]['ProgressDeletion']['d']) $pdCounter++;
			}
			if(isset($_COOKIE['sandbox']) && $_COOKIE['sandbox']!='0'){
				$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
				$ux['User']['reuse1'] = $_COOKIE['sandbox'];
				$_SESSION['loggedInUser']['User']['reuse1'] = $_COOKIE['sandbox'];
				$this->User->save($ux);
			}
		}else{
			if(isset($_SESSION['noLogin'])){
				$noLogin = $_SESSION['noLogin'];
				$noLoginStatus = $_SESSION['noLoginStatus'];
				if(!in_array($t['Tsumego']['id'], $noLogin)){
					array_push($noLogin, $t['Tsumego']['id']);
					array_push($noLoginStatus, 'V');
				}
				$_SESSION['noLogin'] = $noLogin;
				$_SESSION['noLoginStatus'] = $noLoginStatus;
			}else{
				$_SESSION['noLogin'] = array($id);
				$_SESSION['noLoginStatus'] = array('V');
			}
			$u['User'] = array();
			$u['User']['id'] = 33;
			$u['User']['name'] = 'noUser';
			$u['User']['level'] = 1;
			$u['User']['mode'] = 0;
			$u['User']['elo_rating_mode'] = 100;
			$u['User']['xp'] = 0;
			$u['User']['nextlvl'] = 1000;
			$u['User']['health'] = 10;
			
			if(isset($_SESSION['noUser'])){
				$noUser = $_SESSION['noUser'];
				$u['User']['damage'] = $_SESSION['noUser']['damage'];
			}else{
				$noUser = array('id' => 33, 'level' => 1, 'xp' => 0, 'nextlvl' => 50, 'health' => 10, 'damage' => 0);
				$u['User']['damage'] = 0;
				$_SESSION['noUser'] = $noUser;
			}
		}
		
		if(isset($_COOKIE['skip']) && $_COOKIE['skip'] != '0'){
			$u['User']['readingTrial']--;	
			unset($_COOKIE['skip']);
		}
		$sandboxSets = $this->Set->find('all', array('conditions' => array('public' => 0)));
		for($i=0; $i<count($sandboxSets); $i++){
			if($t['Tsumego']['set_id'] == $sandboxSets[$i]['Set']['id']) $isSandbox = true;
		}
		if($t['Tsumego']['set_id'] == 161) $isSandbox = false;
		
		$co = $this->Comment->find('all', array('conditions' => (array('tsumego_id' => $id))));
		$counter1 = 1;
		for($i=0; $i<count($co); $i++){
			if(strpos($co[$i]['Comment']['message'], '<a href="/files/ul1/') === false)
				$co[$i]['Comment']['message'] = htmlspecialchars($co[$i]['Comment']['message']);
			$cou = $this->User->findById($co[$i]['Comment']['user_id']);
			if($cou==null)
				$cou['User']['name'] = '[deleted user]';
			$co[$i]['Comment']['user'] = $this->checkPicture($cou);
			$cad = $this->User->findById($co[$i]['Comment']['admin_id']);
			if($cad!=null){
				if($cad['User']['id'] == 73) $cad['User']['name'] = 'Admin';
				$co[$i]['Comment']['admin'] = $cad['User']['name'];
			}
			$date = new DateTime($co[$i]['Comment']['created']);
			$month = date("F", strtotime($co[$i]['Comment']['created']));
			$tday = $date->format('d. ');
			$tyear = $date->format('Y');
			$tClock = $date->format('H:i');
			if($tday[0]==0) $tday = substr($tday, -3);
			$co[$i]['Comment']['created'] = $tday.$month.' '.$tyear.'<br>'.$tClock;
			$array = $this->commentCoordinates($co[$i]['Comment']['message'], $counter1, true);
			$co[$i]['Comment']['message'] = $array[0];
			array_push($commentCoordinates, $array[1]);
			$counter1++;
			$array = $this->commentCoordinates($co[$i]['Comment']['status'], $counter1, true);
			$co[$i]['Comment']['status'] = $array[0];
			array_push($commentCoordinates, $array[1]);
			$counter1++;
		}
		if($mode==1){
			if(isset($_SESSION['loggedInUser']) && !isset($_SESSION['noLogin'])){
				$utsMap = $_SESSION['loggedInUser']['uts'];
				$allUts = array();//the tsumego statuses
				$correctCounter = 0;
				$utsMapx = array_count_values($utsMap);
				$correctCounter = $utsMapx['C'] + $utsMapx['S'] + $utsMapx['W'];
				$_SESSION['loggedInUser']['User']['solved'] = $correctCounter;
				$u['User']['solved'] = $correctCounter;
				$ut = $this->findUt($id, $utsMap);//status of current
			}else{
				$allUts = null;
				$ut = null;
			}
		}elseif($mode==2){
			$allUts1 = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $t['Tsumego']['id'])));
			$_SESSION['loggedInUser']['User']['mode'] = 2;
			$allUts = array();
			$allUts2 = array();
			$allUts2['TsumegoStatus']['id'] = 59;
			$allUts2['TsumegoStatus']['user_id'] = 72;
			$allUts2['TsumegoStatus']['tsumego_id'] = 572;
			$allUts2['TsumegoStatus']['status'] = 'V';
			$allUts2['TsumegoStatus']['created'] = '2018-02-07 16:35:10';
			array_push($allUts, $allUts1);
			array_push($allUts, $allUts2);
			$ut = $allUts[0];
			
			$old_u = array();
			$old_u['old_r'] = $u['User']['elo_rating_mode'];
			$old_u['old_rd'] = $u['User']['rd'];
			$old_t = array();
			$old_t['old_r'] = $t['Tsumego']['elo_rating_mode'];
			$old_t['old_rd'] = $t['Tsumego']['rd'];
			
			$ratingDeviationArray = $this->compute_rating($old_u, $old_t, 0);
			
			$diff = $t['Tsumego']['elo_rating_mode'] - $u['User']['elo_rating_mode'];
			if($diff<=-600) $diff = -595;
			$newV = (($diff-$oldmin)/($oldmax-$oldmin))*($newmax-$newmin);
			
		}else if($mode==3){
			$allUts1 = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $t['Tsumego']['id'])));
			$allUts = array();
			$allUts2 = array();
			$allUts2['TsumegoStatus']['id'] = 59;
			$allUts2['TsumegoStatus']['user_id'] = 72;
			$allUts2['TsumegoStatus']['tsumego_id'] = 572;
			$allUts2['TsumegoStatus']['status'] = 'V';
			$allUts2['TsumegoStatus']['created'] = '2018-02-07 16:35:10';
			array_push($allUts, $allUts1);
			array_push($allUts, $allUts2);
			$ut = $allUts[0];
		}
		
		if(isset($ut['TsumegoStatus']['status']))
			if($ut['TsumegoStatus']['status']=='G') $goldenTsumego = true;
		
		if(isset($_COOKIE['preId']) && $_COOKIE['preId']!=0){
			$preTsumego = $this->Tsumego->findById($_COOKIE['preId']);
			$preSc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $preTsumego['Tsumego']['id'])));
			$preTsumego['Tsumego']['set_id'] = $preSc['SetConnection']['set_id'];
			$utPre = $this->findUt($_COOKIE['preId'], $utsMap);
		}else $utPre = $this->findUt(15352, $utsMap);
		
		if($mode==1 || $mode==3){
			if(isset($_COOKIE['preId']) && $_COOKIE['preId']==$t['Tsumego']['id']){
				if($_COOKIE['score']!=0){
					$_COOKIE['score'] = $this->decrypt($_COOKIE['score']);
					$scoreArr = explode('-', $_COOKIE['score']);
					$isNum = $preTsumego['Tsumego']['num']==$scoreArr[0];
					$isSet = $preTsumego['Tsumego']['set_id']==$scoreArr[2];
					if($isNum && $isSet)
						$ut['TsumegoStatus']['status'] = 'S';
				}				
				if($_COOKIE['misplay']!=0){
					if($u['User']['damage']+$_COOKIE['misplay'] > $u['User']['health']){
						if(!$hasPremium){
							if($utPre['TsumegoStatus']['status']!='W') $ut['TsumegoStatus']['status'] = 'F';
							else $ut['TsumegoStatus']['status'] = 'X';
						}
					}
				}
			}
		}elseif($mode==2){
			if(isset($_COOKIE['preId']) && $_COOKIE['preId']==$t['Tsumego']['id']){
			}
		}
		if($mode==3){
			$mode3Score1 = $this->encrypt($t['Tsumego']['num'].'-solved-'.$t['Tsumego']['set_id']);
			$mode3Score2 = $this->encrypt($t['Tsumego']['num'].'-failed-'.$t['Tsumego']['set_id']);
			$mode3Score3 = $this->encrypt($t['Tsumego']['num'].'-timeout-'.$t['Tsumego']['set_id']);
			$mode3Score4 = $this->encrypt($t['Tsumego']['num'].'-skipped-'.$t['Tsumego']['set_id']);
			array_push($mode3ScoreArray, $mode3Score1);
			array_push($mode3ScoreArray, $mode3Score2);
			array_push($mode3ScoreArray, $mode3Score3);
			array_push($mode3ScoreArray, $mode3Score4);
		}
		
		$favorite = $this->Favorite->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $id)));
		if(isset($_COOKIE['favorite']) && $_COOKIE['favorite'] != '0'){
			if(isset($_SESSION['loggedInUser']['User']['id'])){
				if($_COOKIE['favorite']>0){
					$fav = $this->Favorite->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $_COOKIE['favorite'])));
					if($fav==null){
						$fav = array();
						$fav['Favorite']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$fav['Favorite']['tsumego_id'] = $_COOKIE['favorite'];
						$fav['Favorite']['created'] = date('Y-m-d H:i:s');
						$this->Favorite->create();
						$this->Favorite->save($fav);
					}
				}else{
					$favId = $_COOKIE['favorite'] * -1;
					$favDel = $this->Favorite->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $favId)));
					$this->Favorite->delete($favDel['Favorite']['id']);
				}
				unset($_COOKIE['favorite']);
			}
		}
		
		if(isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0'){
			$_SESSION['loggedInUser']['User']['sound'] = $_COOKIE['sound'];
			$u['User']['sound'] = $_COOKIE['sound'];
			unset($_COOKIE['sound']);
		}
		
		if(isset($_COOKIE['rank']) && $_COOKIE['rank']!='0'){
			$drCookie = $this->decrypt($_COOKIE['rank']);
			$drCookie2 = explode('-', $drCookie);
			$_COOKIE['rank'] = $drCookie2[1];
		}
		
		if(isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['potion']>=15)
			$this->setPotionCondition();
		
		if(isset($_COOKIE['rejuvenationx']) && $_COOKIE['rejuvenationx']!=0){
			if($u['User']['usedRejuvenation']==0 && $_COOKIE['rejuvenationx']==1){
				$u['User']['damage'] = 0;
				$u['User']['intuition'] = 1;
				$u['User']['damage'] = 0;
				$rejuvenation = true;
			}else if($_COOKIE['rejuvenationx']==2){
				$u['User']['damage'] = 0;
			}
			$_COOKIE['misplay'] = 0;
			unset($_COOKIE['rejuvenationx']);
		}
		//Incorrect
		if(isset($_COOKIE['misplay']) && $_COOKIE['misplay']!=0){
			if($mode==1 && $u['User']['id']!=33){
				if(isset($_SESSION['loggedInUser']['User']['id'])){
					if(isset($_COOKIE['preId']) && $_COOKIE['preId']!=0){
						$this->TsumegoAttempt->create();
						$ur1 = array();
						$ur1['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$ur1['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
						$ur1['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
						$ur1['TsumegoAttempt']['gain'] = 0;
						$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
						$ur1['TsumegoAttempt']['solved'] = '0';
						$ur1['TsumegoAttempt']['misplays'] = $_COOKIE['misplay'];
						$this->TsumegoAttempt->save($ur1);
					}
				}
			}
			if($mode==1 || $mode==3){
				if($mode==1 && $_COOKIE['transition']!=2) $u['User']['damage'] += $_COOKIE['misplay'];
				if(isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0'){
					$ranks = $this->Rank->find('all', array('conditions' =>  array('session' => $_SESSION['loggedInUser']['User']['activeRank'])));
					$currentNum = $ranks[0]['Rank']['currentNum'];
					$_COOKIE['preId'] = $ranks[0]['Rank']['tsumego_id'];
					for($i=0; $i<count($ranks); $i++){
						if($ranks[$i]['Rank']['num'] == $currentNum-1){
							$_COOKIE['preId'] = $ranks[$i]['Rank']['tsumego_id'];
							$ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
							$ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds']/10/$_COOKIE['preId'];
							$this->Rank->save($ranks[$i]);
						}
					}
					
					$eloDifference = abs($_SESSION['loggedInUser']['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
					if($_SESSION['loggedInUser']['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode'])
						$eloBigger = 'u';
					else
						$eloBigger = 't';
					$activityValueTime = 1;
					if(isset($_COOKIE['av']))
						$activityValueTime = $_COOKIE['av'];
					$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'l');
					
					$preTsumego['Tsumego']['elo_rating_mode'] += $activityValueTime['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if($preTsumego['Tsumego']['elo_rating_mode']>100)
						$this->Tsumego->save($preTsumego);
							
					$this->TsumegoAttempt->create();
					$ur1 = array();
					$ur1['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$ur1['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
					$ur1['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
					$ur1['TsumegoAttempt']['gain'] = 0;
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds']/10/$_COOKIE['preId'];
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = 1;
					$ur1['TsumegoAttempt']['mode'] = 3;
					$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
					$this->TsumegoAttempt->save($ur1);
				}
				if($_COOKIE['type']=='g')
					$this->updateGoldenCondition(false);
			}elseif($mode==2){
				$userEloBefore = $u['User']['elo_rating_mode'];
				$tsumegoEloBefore = $preTsumego['Tsumego']['elo_rating_mode'];
				$newV = 1;
				if(isset($_COOKIE['preId'])){
					$eloDifference = abs($_SESSION['loggedInUser']['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
					if($_SESSION['loggedInUser']['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode'])
						$eloBigger = 'u';
					else
						$eloBigger = 't';
					$activityValueRating = 1;
					if(isset($_COOKIE['av']))
						$activityValueRating = $_COOKIE['av'];
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'l');
					$preTsumego['Tsumego']['elo_rating_mode'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if($_COOKIE['type']=='g')
						$this->updateGoldenCondition(false);
					
					if($preTsumego['Tsumego']['elo_rating_mode']>100)
						$this->Tsumego->save($preTsumego);
					
					$this->TsumegoAttempt->create();
					$ur1 = array();
					$ur1['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$ur1['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
					$ur1['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
					$ur1['TsumegoAttempt']['gain'] = $u['User']['elo_rating_mode'];
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = 1;
					$ur1['TsumegoAttempt']['mode'] = 2;
					$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
					$this->TsumegoAttempt->save($ur1);
				}
			}
			$aCondition = $this->AchievementCondition->find('first', array('order' => 'value DESC', 'conditions' => array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'], 'category' => 'err'
			)));
			if($aCondition==null) $aCondition = array();
			$aCondition['AchievementCondition']['category'] = 'err';
			$aCondition['AchievementCondition']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$aCondition['AchievementCondition']['value'] = 0;
			$this->AchievementCondition->save($aCondition);
			if($u['User']['damage']>$u['User']['health']){
				if($utPre==null){
					$utPre['TsumegoStatus'] = array();
					$utPre['TsumegoStatus']['user_id'] = $u['User']['id'];
					$utPre['TsumegoStatus']['tsumego_id'] = $_COOKIE['preId'];
				}
				if(!$hasPremium){
					if($utPre['TsumegoStatus']['status']=='W')
						$utPre['TsumegoStatus']['status'] = 'X';//W => X
					else if($utPre['TsumegoStatus']['status']=='V')
						$utPre['TsumegoStatus']['status'] = 'F';// V => F
					else if($utPre['TsumegoStatus']['status']=='G')
						$utPre['TsumegoStatus']['status'] = 'F';// G => F
					else if($utPre['TsumegoStatus']['status']=='S')
						$utPre['TsumegoStatus']['status'] = 'S';//S => S
				}
				$utPre['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
				if(isset($_SESSION['loggedInUser']['User']['id'])){
					if(!isset($utPre['TsumegoStatus']['status']))
						$utPre['TsumegoStatus']['status'] = 'V';
					if($mode!=3){
						if($onlineMode) $this->TsumegoStatus->save($utPre);
						$_SESSION['loggedInUser']['uts'][$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
						$utsMap[$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
					}
					if($_SESSION['loggedInUser']['User']['premium']>0 || $_SESSION['loggedInUser']['User']['level']>=50){
						if($u['User']['potion']!=-69){
							$_SESSION['loggedInUser']['User']['potion']++;
							$u['User']['potion']++;
							$potion = $_SESSION['loggedInUser']['User']['potion'];
							$potionPercent = 0;
							$potionPercent2 = 0;
							$potionSuccess = false;
							if($potion>=5){
								$potionPercent = $potion*1.3;
								$potionPercent2 = rand(0,100);
								$potionSuccess = $potionPercent>$potionPercent2;
							}
							if($potionSuccess)
								$u['User']['potion'] = -69;
						}
					}
				}
				if($mode==1) $u['User']['damage'] = $u['User']['health'];
				if(!isset($_SESSION['loggedInUser'])){
					for($i=0; $i<count($noLogin); $i++){
						if($noLogin[$i]==$_COOKIE['preId']){
							$noLoginStatus[$i] = 'F';
							$_SESSION['noLoginStatus'] = $noLoginStatus;
						}				
					}
				}
			}
			$noUser['damage'] = $u['User']['damage'];
			unset($_COOKIE['misplay']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
			unset($_COOKIE['transition']);
		}
		$correctSolveAttempt = false;
		
		//Correct!
		if(isset($_COOKIE['score']) && $_COOKIE['score'] != '0'){
			$_COOKIE['score'] = $this->decrypt($_COOKIE['score']);
			$scoreArr = explode('-', $_COOKIE['score']);
			$isNum = $preTsumego['Tsumego']['num']==$scoreArr[0];
			$isSet = $preTsumego['Tsumego']['set_id']==$scoreArr[2];
			$_COOKIE['score'] = $scoreArr[1];
			
			$solvedTsumegoRank = $this->getTsumegoRank($preTsumego['Tsumego']['elo_rating_mode']);
			
			if($isNum && $isSet || $mode==2){
				if($mode==1 || $mode==3){
					if(isset($_SESSION['loggedInUser']) && !isset($_SESSION['noLogin'])){
						//$exploit = $this->UserBoard->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'b1' => $_COOKIE['preId'])));
						$ub = array();
						$ub['UserBoard']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$ub['UserBoard']['b1'] = $_COOKIE['preId'];
						$this->UserBoard->create();
						$this->UserBoard->save($ub);
						
						if($_COOKIE['score']<3000) ;
						else $_COOKIE['score'] = 0;
						
						if($_COOKIE['score']>=3000){
							$suspiciousBehavior = true;
							//$_SESSION['loggedInUser']['User']['reuse5'] = 1;
							//$u['User']['reuse5'] = 1;
						}
						if($u['User']['reuse3']>12000){
							$_SESSION['loggedInUser']['User']['reuse4'] = 1;
							$u['User']['reuse4'] = 1;
						}
					}
					if($mode==3){
						$exploit=null;
						$suspiciousBehavior=false;
					}
					if($exploit==null && $suspiciousBehavior==false){
						if($mode==1){
							$xpOld = $u['User']['xp'] + (intval($_COOKIE['score']));
							$u['User']['reuse2']++;
							$u['User']['reuse3'] += intval($_COOKIE['score']);
							if($xpOld >= $u['User']['nextlvl']){
								$xpOnNewLvl = -1 * ($u['User']['nextlvl'] - $xpOld);
								$u['User']['xp'] = $xpOnNewLvl;
								$u['User']['level'] += 1;
								$u['User']['nextlvl'] += $this->getXPJump($u['User']['level']);
								$u['User']['health'] = $this->getHealth($u['User']['level']);
								$noUser['xp'] = $u['User']['xp']; 
								$noUser['level'] = $u['User']['level'];
								$noUser['nextlvl'] = $u['User']['nextlvl'];
								$noUser['health'] = $u['User']['health'];
								$_SESSION['loggedInUser']['User']['level'] = $u['User']['level'];
							}else{
								$u['User']['xp'] = $xpOld;
								$u['User']['ip'] = $_SERVER['REMOTE_ADDR'];
								$noUser['xp'] = $u['User']['xp'];
							}
						}
						if($mode==1 && $u['User']['id']!=33){
							if(isset($_SESSION['loggedInUser']['User']['id'])){
								if(isset($_COOKIE['preId']) && $_COOKIE['preId']!=0){
									$this->TsumegoAttempt->create();
									$ur = array();
									$ur['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
									$ur['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
									$ur['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
									$ur['TsumegoAttempt']['gain'] = $_COOKIE['score'];
									$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
									$ur['TsumegoAttempt']['solved'] = '1';
									$ur['TsumegoAttempt']['mode'] = 1;
									$this->TsumegoAttempt->save($ur);
									$correctSolveAttempt = true;
									$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
									$this->updateGems($solvedTsumegoRank);
									if($_COOKIE['sprint']==1)
										$this->updateSprintCondition(true);
									else
										$this->updateSprintCondition(false);
									if($_COOKIE['type']=='g')
										$this->updateGoldenCondition(true);
								}
							}
						}
						if(isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0'){
							$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
							$this->updateGems($solvedTsumegoRank);
							$ranks = $this->Rank->find('all', array('conditions' => array('session' => $_SESSION['loggedInUser']['User']['activeRank'])));
							$currentNum = $ranks[0]['Rank']['currentNum'];
							for($i=0; $i<count($ranks); $i++){
								if($ranks[$i]['Rank']['num'] == $currentNum-1){
									$_COOKIE['preId'] = $ranks[$i]['Rank']['tsumego_id'];
									if($_COOKIE['rank']!='solved' && $_COOKIE['rank']!='failed' && $_COOKIE['rank']!='skipped' && $_COOKIE['rank']!='timeout')
										$_COOKIE['rank']='failed';
									$ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
									$ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds']/10/$_COOKIE['preId'];
									$this->Rank->save($ranks[$i]);
								}
							}
							
							$ratingModeUt['TsumegoStatus']['status'] = $_COOKIE['preTsumegoBuffer'];
					
							if($ratingModeUt['TsumegoStatus']['status']=='W'){
								$ratingModeXp = $preTsumego['Tsumego']['difficulty']/2;
							}else if($ratingModeUt['TsumegoStatus']['status']=='S' || $ratingModeUt['TsumegoStatus']['status']=='C'){
								$ratingModeXp = 0;
							}else{
								$ratingModeXp = $preTsumego['Tsumego']['difficulty'];
							}
							$xpOld = $u['User']['xp'] + $ratingModeXp;
							if($xpOld >= $u['User']['nextlvl']){
								$xpOnNewLvl = -1 * ($u['User']['nextlvl'] - $xpOld);
								$u['User']['xp'] = $xpOnNewLvl;
								$u['User']['level'] += 1;
								$u['User']['nextlvl'] += $this->getXPJump($u['User']['level']);
								$u['User']['health'] = $this->getHealth($u['User']['level']);
								$_SESSION['loggedInUser']['User']['level'] = $u['User']['level'];
							}else
								$u['User']['xp'] = $xpOld;
							
							$eloDifference = abs($_SESSION['loggedInUser']['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
							if($_SESSION['loggedInUser']['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode'])
								$eloBigger = 'u';
							else
								$eloBigger = 't';
							$activityValueTime = 1;
							if(isset($_COOKIE['av']))
								$activityValueTime = $_COOKIE['av'];
							$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'w');
							$preTsumego['Tsumego']['elo_rating_mode'] += $activityValueTime['tsumego'];
							$preTsumego['Tsumego']['activity_value']++;
							if($preTsumego['Tsumego']['elo_rating_mode']>100)
								$this->Tsumego->save($preTsumego);
							
							$this->TsumegoAttempt->create();
							$ur1 = array();
							$ur1['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
							$ur1['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
							$ur1['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
							$ur1['TsumegoAttempt']['gain'] = 1;
							$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds']/10/$_COOKIE['preId'];
							$ur1['TsumegoAttempt']['solved'] = '1';
							$ur1['TsumegoAttempt']['misplays'] = 0;
							$ur1['TsumegoAttempt']['mode'] = 3;
							$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
							$this->TsumegoAttempt->save($ur1);
						}
					}
					if(isset($_SESSION['noLogin'])){
						for($i=0; $i<count($noLogin); $i++){
							if($noLogin[$i]==$_COOKIE['preId']){
								$noLoginStatus[$i] = 'S';
								$_SESSION['noLoginStatus'] = $noLoginStatus;
							}				
						}
					}
					if($utPre==null){
						$utPre['TsumegoStatus'] = array();
						$utPre['TsumegoStatus']['user_id'] = $u['User']['id'];
						$utPre['TsumegoStatus']['tsumego_id'] = $_COOKIE['preId'];
						$utPre['TsumegoStatus']['status'] = 'V';
					}
					if($utPre['TsumegoStatus']['status'] == 'W') $utPre['TsumegoStatus']['status'] = 'C';
					else $utPre['TsumegoStatus']['status'] = 'S';
				
					$utPre['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					if(isset($_SESSION['loggedInUser']) && !isset($_SESSION['noLogin'])){
						if(!isset($utPre['TsumegoStatus']['status'])) $utPre['TsumegoStatus']['status'] = 'V';
						if($mode==1){
							if($onlineMode) $this->TsumegoStatus->save($utPre);
							$_SESSION['loggedInUser']['uts'][$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
							$utsMap[$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
						}
					}
				}elseif($mode==2 && $_COOKIE['transition']!=1){
					$userEloBefore = $u['User']['elo_rating_mode'];
					$tsumegoEloBefore = $preTsumego['Tsumego']['elo_rating_mode'];
					$diff = $preTsumego['Tsumego']['elo_rating_mode'] - $u['User']['elo_rating_mode'];
					
					$ratingModeUt['TsumegoStatus']['status'] = $_COOKIE['preTsumegoBuffer'];
					
					if($ratingModeUt['TsumegoStatus']['status']=='W'){
						$ratingModeXp = $preTsumego['Tsumego']['difficulty']/2;
					}else if($ratingModeUt['TsumegoStatus']['status']=='S' || $ratingModeUt['TsumegoStatus']['status']=='C'){
						$ratingModeXp = 0;
					}else{
						$ratingModeXp = $preTsumego['Tsumego']['difficulty'];
					}
					$xpOld = $u['User']['xp'] + $ratingModeXp;
					if($xpOld >= $u['User']['nextlvl']){
						$xpOnNewLvl = -1 * ($u['User']['nextlvl'] - $xpOld);
						$u['User']['xp'] = $xpOnNewLvl;
						$u['User']['level'] += 1;
						$u['User']['nextlvl'] += $this->getXPJump($u['User']['level']);
						$u['User']['health'] = $this->getHealth($u['User']['level']);
						$_SESSION['loggedInUser']['User']['level'] = $u['User']['level'];
					}else{
						$u['User']['xp'] = $xpOld;
						$_SESSION['loggedInUser']['User']['xp'] = $xpOld;
					}
					if(intval($_COOKIE['score']>100))
						$_COOKIE['score'] = 100;
					
					if($_COOKIE['type']=='g')
						$this->updateGoldenCondition(true);
					$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
					$this->updateGems($solvedTsumegoRank);
					$u['User']['solved2']++; 
					
					$eloDifference = abs($_SESSION['loggedInUser']['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
					if($_SESSION['loggedInUser']['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode'])
						$eloBigger = 'u';
					else
						$eloBigger = 't';
					$activityValueRating = 1;
					if(isset($_COOKIE['av']))
						$activityValueRating = $_COOKIE['av'];
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'w');
					$preTsumego['Tsumego']['elo_rating_mode'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if($preTsumego['Tsumego']['elo_rating_mode']>100)
						$this->Tsumego->save($preTsumego);
					
					$this->TsumegoAttempt->create();
					$ur = array();
					$ur['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$ur['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
					$ur['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
					$ur['TsumegoAttempt']['gain'] = $u['User']['elo_rating_mode'];
					$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur['TsumegoAttempt']['solved'] = '1';
					$ur['TsumegoAttempt']['mode'] = 2;
					$ur['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
					$this->TsumegoAttempt->save($ur);
				}
				$aCondition = $this->AchievementCondition->find('first', array('order' => 'value DESC', 'conditions' => array(
					'user_id' => $_SESSION['loggedInUser']['User']['id'], 'category' => 'err'
				)));
				if($aCondition==null) $aCondition = array();
				$aCondition['AchievementCondition']['category'] = 'err';
				$aCondition['AchievementCondition']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$aCondition['AchievementCondition']['value']++;
				$this->AchievementCondition->save($aCondition);
			}else{
				$u['User']['penalty'] += 1;
			}
			unset($_COOKIE['preTsumegoBuffer']);
			unset($_COOKIE['score']);
			unset($_COOKIE['transition']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
		}
		
		if(isset($_COOKIE['correctNoPoints']) && $_COOKIE['correctNoPoints'] != '0'){
			if($u['User']['id']!=33 && !$correctSolveAttempt){
				if(isset($_COOKIE['preId']) && $_COOKIE['preId']!=0){
					$this->TsumegoAttempt->create();
					$ur = array();
					$ur['TsumegoAttempt']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$ur['TsumegoAttempt']['elo'] = $_SESSION['loggedInUser']['User']['elo_rating_mode'];
					$ur['TsumegoAttempt']['tsumego_id'] = $_COOKIE['preId'];
					$ur['TsumegoAttempt']['gain'] = 0;
					$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur['TsumegoAttempt']['solved'] = '1';
					$ur['TsumegoAttempt']['mode'] = 1;
					$this->TsumegoAttempt->save($ur);
				}
			}
		}
		unset($_COOKIE['preId']);
		if(isset($_COOKIE['doublexp']) && $_COOKIE['doublexp'] != '0'){
			if($u['User']['usedSprint']==0){
				$doublexp = $_COOKIE['doublexp'];
			}else{
				unset($_COOKIE['doublexp']);
			}
		}
		if(isset($_COOKIE['sprint']) && $_COOKIE['sprint'] != '0'){
			$u['User']['sprint'] = 0;
			if($_COOKIE['sprint']==1) $this->set('sprintActivated', true);
			if($_COOKIE['sprint']==2) $u['User']['usedSprint'] = 1;
			unset($_COOKIE['sprint']);
		}
		if(isset($_COOKIE['intuition']) && $_COOKIE['intuition'] != '0'){
			if($_COOKIE['intuition']=='1') $u['User']['intuition'] = 0;
			if($_COOKIE['intuition']=='2') $u['User']['intuition'] = 1;
			unset($_COOKIE['intuition']);
		}
		if(isset($_COOKIE['rejuvenation']) && $_COOKIE['rejuvenation'] != '0'){
			$u['User']['rejuvenation'] = 0;
			$u['User']['usedRejuvenation'] = 1;
			unset($_COOKIE['rejuvenation']);
		}
		if(isset($_COOKIE['extendedSprint']) && $_COOKIE['extendedSprint'] != '0'){
			$u['User']['penalty'] += 1;
			unset($_COOKIE['extendedSprint']);
		}
		if(isset($_COOKIE['refinement']) && $_COOKIE['refinement'] != '0'){
			if($_COOKIE['refinement']>0){
				if($u['User']['usedRefinement']==0){
					$refinementUT = $this->findUt($id, $utsMap);
					if($refinementUT==null){
						$this->TsumegoStatus->create();
						$refinementUT['TsumegoStatus']['user_id'] = $u['User']['id'];
						$refinementUT['TsumegoStatus']['tsumego_id'] = $id;
					}
					$refinementUT['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					$refinementUT['TsumegoStatus']['status'] = 'G';
					$testUt = $this->TsumegoStatus->find('first', array('conditions' => array(
						'tsumego_id' => $refinementUT['TsumegoStatus']['tsumego_id'],
						'user_id' => $refinementUT['TsumegoStatus']['user_id']
					)));
					if($testUt!=null)
						$refinementUT['TsumegoStatus']['id'] = $testUt['TsumegoStatus']['id'];
					$this->TsumegoStatus->save($refinementUT);
					$_SESSION['loggedInUser']['uts'][$refinementUT['TsumegoStatus']['tsumego_id']] = $refinementUT['TsumegoStatus']['status'];
					$utsMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $refinementUT['TsumegoStatus']['status'];
						
					if(empty($ut)) $ut = $refinementUT;
					else $ut['TsumegoStatus']['status'] = 'G';
					$goldenTsumego = true;
					$u['User']['usedRefinement'] = 1;
				}
			}else{
				$resetRefinement = $this->findUt($id, $utsMap);
				if($resetRefinement!=null){
					$resetRefinement['TsumegoStatus']['status'] = 'V';
					$testUt = $this->TsumegoStatus->find('first', array('conditions' => array(
						'tsumego_id' => $resetRefinement['TsumegoStatus']['tsumego_id'],
						'user_id' => $resetRefinement['TsumegoStatus']['user_id']
					)));
					$resetRefinement['TsumegoStatus']['id'] = $testUt['TsumegoStatus']['id'];
					$this->TsumegoStatus->save($resetRefinement);
					$_SESSION['loggedInUser']['uts'][$resetRefinement['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
					$utsMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
				}
				if(empty($ut)) $ut = $resetRefinement;
				else $ut['TsumegoStatus']['status'] = 'V';
				$goldenTsumego = false;
			}
			$u['User']['refinement'] = 0;
			unset($_COOKIE['refinement']);
		}
		
		if($rejuvenation){
			$utr = $this->TsumegoStatus->find('all', array('conditions' => array('status' => 'F', 'user_id' => $u['User']['id'])));
			for($i=0; $i<count($utr); $i++){
				$utr[$i]['TsumegoStatus']['status'] = 'V';
				$this->TsumegoStatus->create();
				$this->TsumegoStatus->save($utr[$i]);
				$_SESSION['loggedInUser']['uts'][$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
				$utsMap[$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
			}
			$utrx = $this->TsumegoStatus->find('all', array('conditions' => array('status' => 'X', 'user_id' => $u['User']['id'])));
			for($j=0; $j<count($utrx); $j++){
				$utrx[$j]['TsumegoStatus']['status'] = 'W';
				$this->TsumegoStatus->create();
				$this->TsumegoStatus->save($utrx[$j]);
				$_SESSION['loggedInUser']['uts'][$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
				$utsMap[$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
			}
		}
		
		if(isset($_COOKIE['reputation']) && $_COOKIE['reputation'] != '0'){
			$reputation = $_COOKIE['reputation'];
			$reputation = array();
			$reputation['Reputation']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
			$reputation['Reputation']['tsumego_id'] = abs($_COOKIE['reputation']);
			if($_COOKIE['reputation']>0) $reputation['Reputation']['value'] = 1;
			else $reputation['Reputation']['value'] = -1;
			$this->Reputation->create();
			$this->Reputation->save($reputation);
			unset($_COOKIE['reputation']);
		}
		
		$_SESSION['loggedInUser']['User']['activeRank'] = $u['User']['activeRank'];
		$_SESSION['loggedInUser']['User']['premium'] = $u['User']['premium'];
		$_SESSION['loggedInUser']['User']['completed'] = $u['User']['completed'];
		$_SESSION['loggedInUser']['User']['level'] = $u['User']['level'];
		$_SESSION['loggedInUser']['User']['reuse5'] = $u['User']['reuse5'];
		
		if(isset($noUser)) $_SESSION['noUser'] = $noUser;
		if(isset($_SESSION['loggedInUser']['User']['id']) && $u['User']['id']!=33){
			$u['User']['mode'] = $_SESSION['loggedInUser']['User']['mode'];
			$userDate = new DateTime($u['User']['created']);
			$userDate = $userDate->format('Y-m-d');
			if($userDate!=date('Y-m-d')){
				$u['User']['created'] = date('Y-m-d H:i:s');
				$this->deleteUnusedStatuses($_SESSION['loggedInUser']['User']['id']);
			}
			$this->User->save($u);
		}
		if($mode==1 || $mode==3){
			if($ut==null && isset($_SESSION['loggedInUser']['User']['id'])){
				$this->TsumegoStatus->create();
				$ut['TsumegoStatus'] = array();
				$ut['TsumegoStatus']['user_id'] = $u['User']['id'];
				$ut['TsumegoStatus']['tsumego_id'] = $id;
				$ut['TsumegoStatus']['status'] = 'V';
				if($mode!=3){
					if($onlineMode) $this->TsumegoStatus->save($ut);
					$_SESSION['loggedInUser']['uts'][$ut['TsumegoStatus']['tsumego_id']] = $ut['TsumegoStatus']['status'];
					$utsMap[$ut['TsumegoStatus']['tsumego_id']] = $ut['TsumegoStatus']['status'];
				}
			}
		}elseif($mode==2){
			$ut['TsumegoStatus'] = array();
			$ut['TsumegoStatus']['user_id'] = $u['User']['id'];
			$ut['TsumegoStatus']['tsumego_id'] = $id;
			$ut['TsumegoStatus']['status'] = 'V';
		}
		$set = $this->Set->findById($t['Tsumego']['set_id']);
		$amountOfOtherCollection = count($this->findTsumegoSet($t['Tsumego']['set_id']));
		$search3ids = array();
		for($i=0; $i<count($search3); $i++)
			array_push($search3ids, $this->TagName->findByName($search3[$i])['TagName']['id']);

		$sgf = array();
		if($t['Tsumego']['duplicate']<=9)
			$sgfdb = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' => array('tsumego_id' => $id)));
		else
			$sgfdb = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' => array('tsumego_id' => $t['Tsumego']['duplicate'])));
		if($sgfdb==null){
			$sgf['Sgf']['sgf'] = file_get_contents('placeholder2.sgf');
			$sgf['Sgf']['user_id'] = 33;
			$sgf['Sgf']['tsumego_id'] = $id;
			$sgf['Sgf']['version'] = 1;
			$this->Sgf->save($sgf);
		}else
			$sgf = $sgfdb;
		if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210){
			$aw = strpos($sgf['Sgf']['sgf'], 'AW');
			$ab = strpos($sgf['Sgf']['sgf'], 'AB');
			$tr = strpos($sgf['Sgf']['sgf'], 'TR');
			$sq = strpos($sgf['Sgf']['sgf'], 'SQ');
			$seq1 = strpos($sgf['Sgf']['sgf'], ';B');
			$seq2 = strpos($sgf['Sgf']['sgf'], ';W');
			$sequencesSign = strpos($sgf['Sgf']['sgf'], ';B');
			$p4 = substr($sgf['Sgf']['sgf'], $tr, $sq-$tr);
			$trX = str_split(substr($p4, 2), 4);
			$p5 = substr($sgf['Sgf']['sgf'], $sq, $sequencesSign-$sq);
			$sqX = str_split(substr($p5, 2), 4);
			for($i=0;$i<count($sqX);$i++)
				if(strlen($sqX[$i])<4)
					unset($sqX[$i]);
			$this->set('multipleChoiceTriangles', count($trX));
			$this->set('multipleChoiceSquares', count($sqX));
		}
		$sgf2 = str_replace("\n", ' ', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
		if(isset($this->params['url']['requestProblem'])){
			if(($this->params['url']['requestProblem']/1337)==$id){
				$requestProblem = $_POST['sgfForBesogo'];
				$requestProblem = str_replace('@', ';', $requestProblem);
				$requestProblem = str_replace('€', "\n", $requestProblem);
				$requestProblem = str_replace('%2B', "+", $requestProblem);
				if($t['Tsumego']['duplicate']<=9)
					$lastV = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' => array('tsumego_id' => $id)));
				else
					$lastV = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' => array('tsumego_id' => $t['Tsumego']['duplicate'])));
				if($requestProblem !== $lastV['Sgf']['sgf']){
					$sgf = array();
					$sgf['Sgf']['sgf'] = $requestProblem;
					$sgf['Sgf']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
					$sgf['Sgf']['tsumego_id'] = $id;
					if($_SESSION['loggedInUser']['User']['isAdmin'] > 0)
						$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, $_SESSION['loggedInUser']['User']['id']);
					else
						$sgf['Sgf']['version'] = 0;
					$this->Sgf->save($sgf);
					$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
					$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
					if($_SESSION['loggedInUser']['User']['isAdmin'] > 0){
						$this->AdminActivity->create();
						$adminActivity = array();
						$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
						$adminActivity['AdminActivity']['answer'] = $t['Tsumego']['num'].'.sgf'.' <font color="grey">(direct save)</font>';
						$this->AdminActivity->save($adminActivity);
						$this->handleContribution($_SESSION['loggedInUser']['User']['id'], 'made_proposal');
					}
				}
			}
		}
		
		if($query == 'difficulty'){
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = array();
			if(count($search1) > 0){
				$search1ids = array();
				for($i=0; $i<count($search1); $i++){
					$search1id = $this->Set->find('first', array('conditions' => array('title' => $search1[$i])));
					$search1ids[$i] = $search1id['Set']['id'];
				}
				$setConditions['set_id'] = $search1ids;
			}
			$lastSet = $this->getTsumegoElo($_SESSION['lastSet']);
			$ftFrom['elo_rating_mode >='] =  $lastSet;
			$ftTo['elo_rating_mode <'] =  $lastSet+100;
			if($_SESSION['lastSet']=='15k')
				$ftFrom['elo_rating_mode >='] = 50;
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
					if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) 
						$t['Tsumego']['num'] = $ts[$i]['Tsumego']['num'];
					array_push($ts1, $ts[$i]);
					$i2++;
				}
			}
			$ts = $ts1;

			if(count($ts) > $collectionSize){
				for($i=0; $i<count($ts); $i++){
					if($i % $collectionSize == 0)
						$partition++;
					if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id'])
						break;
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
			}
			if($partition == -1)
				$partitionText = '';
			else
				$partitionText = '#'.($partition+1);
			$anzahl2 = 1;
			for($i=0; $i<count($ts); $i++)
				if($ts[$i]['Tsumego']['num']>$anzahl2)
					$anzahl2 = $ts[$i]['Tsumego']['num'];
			$queryTitle = $_SESSION['lastSet'].' '.$partitionText.' '.$t['Tsumego']['num'].'/'.$anzahl2;
		}else if($query == 'tags'){
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = array();
			$rankConditions = array();
			$tagIds = array();
			$tagName = $this->TagName->findByName($_SESSION['lastSet']);
			
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
				if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) 
					$t['Tsumego']['num'] = $ts[$i]['Tsumego']['num'];
			}
			
			if(count($ts) > $collectionSize){
				for($i=0; $i<count($ts); $i++){
					if($i % $collectionSize == 0)
						$partition++;
					if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id'])
						break;
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
			}
			if($partition == -1)
				$partitionText = '';
			else
				$partitionText = '#'.($partition+1);
			$anzahl2 = 1;
			for($i=0; $i<count($ts); $i++)
				if($ts[$i]['Tsumego']['num']>$anzahl2)
					$anzahl2 = $ts[$i]['Tsumego']['num'];
			$queryTitle = $_SESSION['lastSet'].' '.$partitionText.' '.$t['Tsumego']['num'].'/'.$anzahl2;
		}else if($query == 'topics'){
			$setConnectionIds = array();
			$tsTsumegosMap = array();
			$rankConditions = array();
			$ts = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array('set_id' => $set['Set']['id'])));

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
			for($i=0; $i<count($ts); $i++)
				array_push($setConnectionIds, $ts[$i]['SetConnection']['tsumego_id']);

			$tsTsumegos = $this->Tsumego->find('all', array('order' => 'num ASC', 'conditions' => array(
				'id' => $setConnectionIds,
				$rankConditions
			)));
			for($i=0; $i<count($tsTsumegos); $i++)
				$tsTsumegosMap[$tsTsumegos[$i]['Tsumego']['id']] = $tsTsumegos[$i];

			$tsBuffer = array();
			for($i=0; $i<count($ts); $i++){
				if(isset($tsTsumegosMap[$ts[$i]['SetConnection']['tsumego_id']]))
					$tagValid = true;
				else
					$tagValid = false;
				if($tagValid == true){
					if(count($search3) > 0){
						$tagForTsumego = $this->Tag->find('first', array('conditions' => array(
							'tsumego_id' => $ts[$i]['SetConnection']['tsumego_id'],
							'tag_name_id' => $search3ids,
						)));
						if($tagForTsumego!=null)
							$tagValid = true;
						else
							$tagValid = false;
					}
				}
				if($tagValid)
					array_push($tsBuffer, $ts[$i]);
			}
			$ts = $tsBuffer;
			if(count($ts) > $collectionSize){
				for($i=0; $i<count($ts); $i++){
					if($i % $collectionSize == 0)
						$partition++;
					if($ts[$i]['SetConnection']['tsumego_id'] == $t['Tsumego']['id'])
						break;
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = array();
				for($i=$fromTo[0]; $i<=$fromTo[1]; $i++){
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
				$queryTitleSets = '#'.($partition+1);
			}
			$anzahl2 = 1;
			for($i=0; $i<count($ts); $i++)
				if($ts[$i]['SetConnection']['num']>$anzahl2)
					$anzahl2 = $ts[$i]['SetConnection']['num'];
		}
		$anzahl = count($ts);
		
	  if($query == 'topics')
			$_SESSION['title'] = $set['Set']['title'].' '.$t['Tsumego']['num'].'/'.$anzahl2.' on Tsumego Hero';
		else
			$_SESSION['title'] = $_SESSION['lastSet'].' '.$t['Tsumego']['num'].'/'.$anzahl2.' on Tsumego Hero';
		$prev = 0;
		$next = 0;
		$tsBack = array();
		$tsNext = array();
		if(!$inFavorite){
			if($query == 'difficulty' || $query == 'tags'){
				for($i=0; $i<count($ts); $i++){
					if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']){
						$a = 5;
						while($a>0){
							if($i-$a >= 0){
								$tsBackA = $ts[$i-$a];
								array_push($tsBack, $tsBackA);
								if($a==1)
									$prev = $ts[$i-$a]['Tsumego']['id'];
								$newUT = $this->findUt($ts[$i-$a]['Tsumego']['id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status']))
									$newUT['TsumegoStatus']['status'] = 'N';
								$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$a--;
						}
						$bMax = 10 - count($tsBack);
						$b = 1;
						if($ts[0]['Tsumego']['id']==$t['Tsumego']['id']) $bMax++;
						while($b <= $bMax){
							if($i+$b<count($ts)){
								$tsNextA = $ts[$i+$b];
								array_push($tsNext, $tsNextA);
								if($b==1)
									$next = $ts[$i+$b]['Tsumego']['id'];
								$newUT = $this->findUt($ts[$i+$b]['Tsumego']['id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status']))
									$newUT['TsumegoStatus']['status'] = 'N';
								$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$b++;
						}
						if(count($tsNext)<5 || $t['Tsumego']['id'] == $ts[count($ts)-6]['Tsumego']['id']){
							$tsBack = array();
							$a = 5 + (5-count($tsNext));
							$a++;
							while($a > 0){
								if($i-$a >= 0){
									$tsBackA = $ts[$i-$a];
									array_push($tsBack, $tsBackA);
									if($a==1)
										$prev = $ts[$i-$a]['Tsumego']['id'];
									$newUT = $this->findUt($ts[$i-$a]['Tsumego']['id'], $utsMap);
									if(!isset($newUT['TsumegoStatus']['status']))
										$newUT['TsumegoStatus']['status'] = 'N';
									$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
								}
								$a--;
							}
						}
						if((count($tsBack)<5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id']!=$t['Tsumego']['id']){
							$tsNextAdjust = count($tsNext) + 1;
							$tsNext = array();
							$b = 1;
							while($b <= $tsNextAdjust){
								if($i+$b<count($ts)){
									$tsNextA = $ts[$i+$b];
									array_push($tsNext, $tsNextA);
									if($b==1)
										$next = $ts[$i+$b]['Tsumego']['id'];
									$newUT = $this->findUt($ts[$i+$b]['Tsumego']['id'], $utsMap);
									if(!isset($newUT['TsumegoStatus']['status']))
										$newUT['TsumegoStatus']['status'] = 'N';
									$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
								}
								$b++;
							}
						}
					}
				}
			}else if($query == 'topics'){
				//topics
				for($i=0; $i<count($ts); $i++){
					if($ts[$i]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']){
						$a = 5;
						while($a>0){
							if($i-$a >= 0){
								$tsBackA = $tsTsumegosMap[$ts[$i-$a]['SetConnection']['tsumego_id']];
								$scTsBack = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[$i-$a]['SetConnection']['tsumego_id'])));
								if(count($scTsBack)<=1)
									$tsBackA['Tsumego']['duplicateLink'] = '';
								else
									$tsBackA['Tsumego']['duplicateLink'] = '?sid='.$ts[$i-$a]['SetConnection']['set_id'];
								$tsBackA['Tsumego']['num'] = $ts[$i-$a]['SetConnection']['num'];
								array_push($tsBack, $tsBackA);
								if($a==1)
									$prev = $ts[$i-$a]['SetConnection']['tsumego_id'];
								$newUT = $this->findUt($ts[$i-$a]['SetConnection']['tsumego_id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status']))
									$newUT['TsumegoStatus']['status'] = 'N';
								$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$a--;
						}
						$bMax = 10 - count($tsBack);
						$b = 1;
						if($ts[0]['SetConnection']['tsumego_id']==$t['Tsumego']['id']) $bMax++;
						while($b <= $bMax){
							if($i+$b<count($ts)){
								$tsNextA = $tsTsumegosMap[$ts[$i+$b]['SetConnection']['tsumego_id']];
								$scTsNext = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[$i+$b]['SetConnection']['tsumego_id'])));
								if(count($scTsNext)<=1)
									$tsNextA['Tsumego']['duplicateLink'] = '';
								else
									$tsNextA['Tsumego']['duplicateLink'] = '?sid='.$ts[$i+$b]['SetConnection']['set_id'];
								$tsNextA['Tsumego']['num'] = $ts[$i+$b]['SetConnection']['num'];
								array_push($tsNext, $tsNextA);
								if($b==1)
									$next = $ts[$i+$b]['SetConnection']['tsumego_id'];
								$newUT = $this->findUt($ts[$i+$b]['SetConnection']['tsumego_id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status']))
									$newUT['TsumegoStatus']['status'] = 'N';
								$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$b++;
						}
						if(count($tsNext)<5 || $t['Tsumego']['id'] == $ts[count($ts)-6]['SetConnection']['tsumego_id']){
							$tsBack = array();
							$a = 5 + (5-count($tsNext));
							$a++;
							while($a > 0){
								if($i-$a >= 0){
									$tsBackA = $tsTsumegosMap[$ts[$i-$a]['SetConnection']['tsumego_id']];
									$scTsBack = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[$i-$a]['SetConnection']['tsumego_id'])));
									if(count($scTsBack)<=1)
										$tsBackA['Tsumego']['duplicateLink'] = '';
									else
										$tsBackA['Tsumego']['duplicateLink'] = '?sid='.$ts[$i-$a]['SetConnection']['set_id'];
									$tsBackA['Tsumego']['num'] = $ts[$i-$a]['SetConnection']['num'];
									array_push($tsBack, $tsBackA);
									if($a==1)
										$prev = $ts[$i-$a]['SetConnection']['tsumego_id'];
									$newUT = $this->findUt($ts[$i-$a]['SetConnection']['tsumego_id'], $utsMap);
									if(!isset($newUT['TsumegoStatus']['status']))
										$newUT['TsumegoStatus']['status'] = 'N';
									$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
								}
								$a--;
							}
						}
						if((count($tsBack)<5 || $t['Tsumego']['id'] == $ts[5]['SetConnection']['tsumego_id']) && $ts[0]['SetConnection']['tsumego_id']!=$t['Tsumego']['id']){
							$tsNextAdjust = count($tsNext) + 1;
							$tsNext = array();
							$b = 1;
							while($b <= $tsNextAdjust){
								if($i+$b<count($ts)){
									$tsNextA = $tsTsumegosMap[$ts[$i+$b]['SetConnection']['tsumego_id']];
									$scTsNext = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[$i+$b]['SetConnection']['tsumego_id'])));
									if(count($scTsNext)<=1)
										$tsNextA['Tsumego']['duplicateLink'] = '';
									else
										$tsNextA['Tsumego']['duplicateLink'] = '?sid='.$ts[$i+$b]['SetConnection']['set_id'];
									$tsNextA['Tsumego']['num'] = $ts[$i+$b]['SetConnection']['num'];
									array_push($tsNext, $tsNextA);
									if($b==1)
										$next = $ts[$i+$b]['SetConnection']['tsumego_id'];
									$newUT = $this->findUt($ts[$i+$b]['SetConnection']['tsumego_id'], $utsMap);
									if(!isset($newUT['TsumegoStatus']['status']))
										$newUT['TsumegoStatus']['status'] = 'N';
									$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
								}
								$b++;
							}
						}
					}
				}
			}
			$inFavorite = '';
		}else{
			//fav
			$fav = $this->Favorite->find('all', array('order' => 'created',	'direction' => 'DESC', 'conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
			$ts = array();
			for($i=0; $i<count($fav); $i++){
				$tx = $this->Tsumego->findById($fav[$i]['Favorite']['tsumego_id']);
				array_push($ts, $tx);
			}
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']){
					$a = 5;
					while($a>0){
						if($i-$a >= 0){
							array_push($tsBack, $ts[$i-$a]);
							if($a==1) $prev = $ts[$i-$a]['Tsumego']['id'];
							$newUT = $this->findUt($ts[$i-$a]['Tsumego']['id'], $utsMap);
							if(!isset($newUT['TsumegoStatus']['status'])) $newUT['TsumegoStatus']['status'] = 'N';
							$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
						}
						$a--;
					}
					$bMax = 10 - count($tsBack);
					$b = 1;
					if($ts[0]['Tsumego']['id']==$t['Tsumego']['id']) $bMax++;
					while($b <= $bMax){
						if($i+$b < count($ts)){
							array_push($tsNext, $ts[$i+$b]);
							if($b==1) $next = $ts[$i+$b]['Tsumego']['id'];
							$newUT = $this->findUt($ts[$i+$b]['Tsumego']['id'], $utsMap);
							if(!isset($newUT['TsumegoStatus']['status'])) $newUT['TsumegoStatus']['status'] = 'N';
							$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
						}
						$b++;
					}
					if(count($tsNext)<5 || $t['Tsumego']['id'] == $ts[count($ts)-6]['Tsumego']['id']){
						$tsBack = array();
						$a = 5 + (5-count($tsNext));
						$a++;
						while($a > 0){
							if($i-$a >= 0){
								array_push($tsBack, $ts[$i-$a]);
								if($a==1) $prev = $ts[$i-$a]['Tsumego']['id'];
								$newUT = $this->findUt($ts[$i-$a]['Tsumego']['id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status'])) $newUT['TsumegoStatus']['status'] = 'N';
								$tsBack[count($tsBack)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$a--;
						}
					}
					if((count($tsBack)<5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id']!=$t['Tsumego']['id']){
						$tsNextAdjust = count($tsNext) + 1;
						$tsNext = array();
						$b = 1;
						while($b <= $tsNextAdjust){
							if($i+$b < count($ts)){
								array_push($tsNext, $ts[$i+$b]);
								if($b==1) $next = $ts[$i+$b]['Tsumego']['id'];
								$newUT = $this->findUt($ts[$i+$b]['Tsumego']['id'], $utsMap);
								if(!isset($newUT['TsumegoStatus']['status'])) $newUT['TsumegoStatus']['status'] = 'N';
								$tsNext[count($tsNext)-1]['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
							}
							$b++;
						}
					}
				}
			}
			$inFavorite = '?favorite=1';
		}
		if($query == 'difficulty' || $query == 'tags'){
			//tsFirst
			$tsFirst = $ts[0];
			$isInArray = -1;
			for($i=0; $i<count($tsBack); $i++)
				if($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id'])
					$isInArray = $i;
			if($isInArray!=-1){
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			$newUT = $this->findUt($ts[0]['Tsumego']['id'], $utsMap);
			if(!isset($newUT['TsumegoStatus']['status']))
				$newUT['TsumegoStatus']['status'] = 'N';
			$tsFirst['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';	
			if($t['Tsumego']['id'] == $tsFirst['Tsumego']['id'])
				$tsFirst = null;
			//tsLast
			$tsLast = $ts[count($ts)-1];
			$isInArray = -1;
			for($i=0; $i<count($tsNext); $i++)
				if($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id'])
					$isInArray = $i;
			if($isInArray!=-1){
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			$newUT = $this->findUt($ts[count($ts)-1]['Tsumego']['id'], $utsMap);
		}else if($query == 'topics' && !$inFavorite){
			//tsFirst
			$tsFirst = $this->Tsumego->findById($ts[0]['SetConnection']['tsumego_id']);
			$scTsFirst = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[0]['SetConnection']['tsumego_id'])));
			if(count($scTsFirst)<=1)
				$tsFirst['Tsumego']['duplicateLink'] = '';
			else
				$tsFirst['Tsumego']['duplicateLink'] = '?sid='.$ts[0]['SetConnection']['set_id'];
			$tsFirst['Tsumego']['num'] = $ts[0]['SetConnection']['num'];
			$isInArray = -1;
			for($i=0; $i<count($tsBack); $i++)
				if($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id']) $isInArray = $i;
			if($isInArray!=-1){
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			$newUT = $this->findUt($ts[0]['SetConnection']['tsumego_id'], $utsMap);
			if(!isset($newUT['TsumegoStatus']['status']))
				$newUT['TsumegoStatus']['status'] = 'N';
			$tsFirst['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';	
			if($t['Tsumego']['id'] == $tsFirst['Tsumego']['id'])
				$tsFirst = null;
			//tsLast
			$tsLast = $this->Tsumego->findById($ts[count($ts)-1]['SetConnection']['tsumego_id']);
			$scTsLast = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $ts[count($ts)-1]['SetConnection']['tsumego_id'])));
			if(count($scTsLast)<=1)
				$tsLast['Tsumego']['duplicateLink'] = '';
			else
				$tsLast['Tsumego']['duplicateLink'] = '?sid='.$ts[count($ts)-1]['SetConnection']['set_id'];
			$tsLast['Tsumego']['num'] = $ts[count($ts)-1]['SetConnection']['num'];
			$isInArray = -1;
			for($i=0; $i<count($tsNext); $i++)
				if($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id'])
					$isInArray = $i;
			if($isInArray!=-1){
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			$newUT = $this->findUt($ts[count($ts)-1]['SetConnection']['tsumego_id'], $utsMap);
		}else if($inFavorite){
			//tsFirst
			$tsFirst = $this->Tsumego->findById($fav[0]['Favorite']['tsumego_id']);
			$tsFirst['Tsumego']['duplicateLink'] = '';
			$isInArray = -1;
			for($i=0; $i<count($tsBack); $i++)
				if($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id']) $isInArray = $i;
			if($isInArray!=-1){
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			if($t['Tsumego']['id'] == $tsFirst['Tsumego']['id'])
				$lastInFav = -1;
			$newUT = $this->findUt($fav[0]['Favorite']['tsumego_id'], $utsMap);
			if(!isset($newUT['TsumegoStatus']['status']))
				$newUT['TsumegoStatus']['status'] = 'N';
			$tsFirst['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';	
			if($t['Tsumego']['id'] == $tsFirst['Tsumego']['id'])
				$tsFirst = null;
			
			//tsLast
			$tsLast = $this->Tsumego->findById($fav[count($fav)-1]['Favorite']['tsumego_id']);
			$tsLast['Tsumego']['duplicateLink'] = '';
			$isInArray = -1;
			for($i=0; $i<count($tsNext); $i++)
				if($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id'])
					$isInArray = $i;
			if($isInArray!=-1){
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			if($t['Tsumego']['id'] == $tsLast['Tsumego']['id'])
				$lastInFav = 1;
			$newUT = $this->findUt($fav[count($fav)-1]['Favorite']['tsumego_id'], $utsMap);
		}

		if(!isset($newUT['TsumegoStatus']['status']))
			$newUT['TsumegoStatus']['status'] = 'N';
		$tsLast['Tsumego']['status'] = 'set'.$newUT['TsumegoStatus']['status'].'1';
		if($t['Tsumego']['id'] == $tsLast['Tsumego']['id'])
			$tsLast = null;
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if(!isset($ut['TsumegoStatus']['status']))
				$t['Tsumego']['status'] = 'V';
			$t['Tsumego']['status'] = 'set'.$ut['TsumegoStatus']['status'].'2';
			$half='';
			if($ut['TsumegoStatus']['status']=='W' || $ut['TsumegoStatus']['status']=='X'){
				$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty']/2);
				$half='(1/2)';
			}	
		}else
			$t['Tsumego']['status'] = 'setV2';

		if(!isset($t['Tsumego']['file']) || $t['Tsumego']['file']=='') $t['Tsumego']['file'] = $t['Tsumego']['num'];
		$file = 'placeholder2.sgf';
		if($t['Tsumego']['variance']==100) $file = 'placeholder2.sgf';
		$orientation = null;
		$colorOrientation = null;
		if(isset($this->params['url']['orientation'])) $orientation = $this->params['url']['orientation'];
		if(isset($this->params['url']['playercolor'])) $colorOrientation = $this->params['url']['playercolor'];
		
		$checkBSize = 19;
		for($i=2; $i<=19; $i++)
			if(strpos(';'.$set['Set']['title'], $i.'x'.$i))
				$checkBSize = $i;
		if(!isset($_SESSION['loggedInUser'])) $u['User'] = $noUser;
		
		$navi = array();
		array_push($navi, $tsFirst);
		for($i=0; $i<count($tsBack); $i++) 
			array_push($navi, $tsBack[$i]);
		array_push($navi, $t);
		for($i=0; $i<count($tsNext); $i++) 
			array_push($navi, $tsNext[$i]);
		array_push($navi, $tsLast);
		
		$tooltipSgfs = array();
		$tooltipInfo = array();
		$tooltipBoardSize = array();
		for($i=0; $i<count($navi); $i++){
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $navi[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		
		if($t['Tsumego']['set_id']==161){
			$joseki = $this->Joseki->find('first', array('conditions' =>  array('tsumego_id' => $t['Tsumego']['id'])));
			$josekiLevel = $joseki['Joseki']['hints'];
			for($i=0; $i<count($navi); $i++){
				$j = $this->Joseki->find('first', array('conditions' =>  array('tsumego_id' => $navi[$i]['Tsumego']['id'])));
				$navi[$i]['Tsumego']['type'] = $j['Joseki']['type'];
				$navi[$i]['Tsumego']['thumbnail'] = $j['Joseki']['thumbnail'];
				
			}
		}
		if(isset($_SESSION['noLogin'])){
			for($i=0; $i<count($navi); $i++){
				for($j=0; $j<count($noLogin); $j++){
					if($navi[$i]['Tsumego']['id'] == $noLogin[$j]){
						$navi[$i]['Tsumego']['status'] = 'set'.$noLoginStatus[$j].substr($navi[$i]['Tsumego']['status'], -1);
					}
				}
			}
			for($j=0; $j<count($noLogin); $j++){
				if($noLogin[$j]==$t['Tsumego']['id'] && ($noLoginStatus[$j]=='S' || $noLoginStatus[$j]=='W' || $noLoginStatus[$j]=='C')){
					$t['Tsumego']['status'] = 'setS2';
				}
				if($noLogin[$j]==$t['Tsumego']['id'] && $noLoginStatus[$j]=='F'){
					$t['Tsumego']['status'] = 'setF2';
				}
			}
		}
		if($u['User']['health']>=8){
			$fullHeart = 'heart1small';
			$emptyHeart = 'heart2small';
		}else{
			$fullHeart = 'heart1';
			$emptyHeart = 'heart2';
		}
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$this->set('sprintEnabled', $u['User']['sprint']);
			$this->set('intuitionEnabled', $u['User']['intuition']);
			$this->set('rejuvenationEnabled', $u['User']['rejuvenation']);
			$this->set('refinementEnabled', $u['User']['refinement']);
			$this->set('maxNoUserLevel', false);
			if($u['User']['reuse4']==0) $_SESSION['loggedInUser']['User']['reuse4'] = 0;
			if($_SESSION['loggedInUser']['User']['premium']>0) $_SESSION['loggedInUser']['User']['reuse4'] = 0;
			if($_SESSION['loggedInUser']['User']['reuse4']==1) $dailyMaximum = true;
			if($_SESSION['loggedInUser']['User']['reuse5']==1) $suspiciousBehavior = true;
		}else{
			if($noUser['level']>=10) $this->set('maxNoUserLevel', true);
			else $this->set('maxNoUserLevel', false);
		}
		if($isSandbox || $t['Tsumego']['set_id']==51){
			$this->set('sandboxXP', $t['Tsumego']['difficulty']);
			$t['Tsumego']['difficulty2'] = $t['Tsumego']['difficulty'];
			$t['Tsumego']['difficulty'] = 10;
		}
		if($goldenTsumego)
			$t['Tsumego']['difficulty'] *= 8;
		$refinementT = $this->Tsumego->find('all', array('limit' => 5000, 'conditions' => array(
			'difficulty >' => 35
		)));
		
		$hasAnyFavorite = $this->Favorite->find('first', array('conditions' => array('user_id' => $u['User']['id'])));
		$folderString = $t['Tsumego']['num'].'number'.$set['Set']['folder'];
		$hash = $this->encrypt($folderString);
		
		if($pdCounter==1) $t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty']*.5);
		else if($pdCounter==2) $t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty']*.2);
		else if($pdCounter==3) $t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty']*.1);
		else if($pdCounter>3) $t['Tsumego']['difficulty'] = 1;
		
		if($pdCounter>0) $sandboxComment2 = true;
		else $sandboxComment2 = false;
		
		$score1 = $t['Tsumego']['num'].'-'.$t['Tsumego']['difficulty'].'-'.$t['Tsumego']['set_id'];
		$score1 = $this->encrypt($score1);
		$t2 = $t['Tsumego']['difficulty']*2;
		$score2 = $t['Tsumego']['num'].'-'.$t2.'-'.$t['Tsumego']['set_id'];
		$score2 = $this->encrypt($score2);
		
		$score3 = $t['Tsumego']['num'].'-'.$eloScore.'-'.$t['Tsumego']['set_id'];
		$score3 = $this->encrypt($score3);
		
		shuffle($refinementT);
		
		$refinementPublic = false;
		$refinementPublicCounter = 0;
		
		while(!$refinementPublic){
			$scRefinement = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $refinementT[$refinementPublicCounter]['Tsumego']['id'])));
			$setScRefinement = $this->Set->findById($scRefinement['SetConnection']['set_id']);
			if($setScRefinement['Set']['public']==1 && $setScRefinement['Set']['premium']!=1)
				$refinementPublic = true;
			else
				$refinementPublicCounter++;
		}
		$activate = true;
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['premium']>0 || $_SESSION['loggedInUser']['User']['level']>=50){
				if($u['User']['potion']!=-69){
					if($u['User']['health']-$u['User']['damage']<=0) $potionActive = true;
				}
			}
			if(!isset($_SESSION['loggedInUser']['User']['id']) && isset($_SESSION['loggedInUser']['User']['premium']))
				unset($_SESSION['loggedInUser']);
			$achievementUpdate1 = $this->checkLevelAchievements();
			$achievementUpdate2 = $this->checkProblemNumberAchievements();
			$achievementUpdate3 = $this->checkNoErrorAchievements();
			$achievementUpdate4 = $this->checkRatingAchievements();
			$achievementUpdate5 = $this->checkDanSolveAchievements();
			$achievementUpdate = array_merge($achievementUpdate1, $achievementUpdate2, $achievementUpdate3, $achievementUpdate4, $achievementUpdate5);
			if(count($achievementUpdate)>0) $this->updateXP($_SESSION['loggedInUser']['User']['id'], $achievementUpdate);
		}
		
		$admins = $this->User->find('all', array('conditions' => array('isAdmin' => 1)));
		if($mode==2 || $mode==3) $_SESSION['title'] = 'Tsumego Hero';
		if($isSandbox) $t['Tsumego']['userWin'] = 0;
		
		$crs = 0;
		if($mode==3){
			$t['Tsumego']['status'] = 'setV2';
			for($i=0;$i<count($ranks);$i++){
				if($ranks[$i]['Rank']['result']=='solved') $crs++;
			}
		}
		if(isset($this->params['url']['rank'])) $raName = $this->params['url']['rank'];
		else{
			if(!isset($ranks[0]['Rank']['rank'])) $ranks[0]['Rank']['rank'] = '';
			$raName = $ranks[0]['Rank']['rank'];
		}
		
		if($mode==1)
			$_SESSION['page'] = 'level mode';
		elseif($mode==2)
			$_SESSION['page'] = 'rating mode';
		elseif($mode==3)
			$_SESSION['page'] = 'time mode';
		
		if($requestProblem!=''){
			$requestProblem = '?v='.strlen($requestProblem);
		}else{
			$sgfx = file_get_contents($file);
			$requestProblem = '?v='.strlen($sgfx);
		}
		
		$ui = 2;
		$file = 'placeholder2.sgf';
		
		if($mode==1){
			$scPrev = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $prev)));
			for($i=0;$i<count($scPrev);$i++)
				if(count($scPrev)>1 && $scPrev[$i]['SetConnection']['set_id']==$t['Tsumego']['set_id'])
					$prev .= '?sid='.$t['Tsumego']['set_id'];
			$scNext = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $next)));
			for($i=0;$i<count($scNext);$i++)
				if(count($scNext)>1 && $scNext[$i]['SetConnection']['set_id']==$t['Tsumego']['set_id'])
					$next .= '?sid='.$t['Tsumego']['set_id'];
		}else if($mode==2){
			$next = $nextMode['Tsumego']['id'];
		}else if($mode==3){
			$next = $currentRank2['Tsumego']['id'];
		}
		$this->startPageUpdate();
		$startingPlayer = $this->getStartingPlayer($sgf2);
		
		if($avActive==true)
			$avActiveText = '';
		else
			$avActiveText = '<font style="color:gray;"> (recently played)</font>';
		if($avActive2==false)
			$avActiveText = '<font style="color:gray;"> (out of range)</font>';
		
		$eloScoreRounded = round($eloScore);
		$eloScore2Rounded = round($eloScore2);
		
		$existingSignatures = $this->Signature->find('all', array('conditions' => array('tsumego_id' => $id)));
		if($existingSignatures==null || $existingSignatures[0]['Signature']['created']<date('Y-m-d', strtotime('-1 week')))
			$requestSignature = 'true';
		else
			$requestSignature = 'false';
		if(isset($_COOKIE['signatures']) && $set['Set']['public']==1){
			$signature = explode('/', $_COOKIE['signatures']);
			$oldSignatures = $this->Signature->find('all', array('conditions' => array('tsumego_id' => $signature[count($signature)-1])));
			
			for($i=0;$i<count($oldSignatures);$i++)
				$this->Signature->delete($oldSignatures[$i]['Signature']['id']);
			
			for($i=0;$i<count($signature)-1;$i++){
				$this->Signature->create();
				$newSignature = array();
				$newSignature['Signature']['tsumego_id'] = $signature[count($signature)-1];
				$newSignature['Signature']['signature'] = $signature[$i];
				$this->Signature->save($newSignature);
			}
			unset($_COOKIE['signatures']);
		}
		$idForSignature = -1;
		$idForSignature2 = -1;
		if(isset($this->params['url']['idForTheThing'])){
			$idForSignature2 = $this->params['url']['idForTheThing']+1;
			$idForSignature = $this->getTheIdForTheThing($idForSignature2);
		}
		if(!isset($difficulty))
			$difficulty = 4;

		$u['User']['name'] = $this->checkPicture($u);
		$tags = $this->getTags($id);
		$tags = $this->checkTagDuplicates($tags);

		$allTags = $this->getAllTags($tags);
		$popularTags = $this->getPopularTags($tags);
		$uc = $this->UserContribution->find('first', array('conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
		$hasRevelation = false;
		if($uc!=null)
			$hasRevelation = $uc['UserContribution']['reward3'];
		if($_SESSION['loggedInUser']['User']['premium']>0 && $_SESSION['loggedInUser']['User']['level']>=100)
			$hasRevelation = true;
		
		$sgfProposal = $this->Sgf->find('first', array('conditions' => array('tsumego_id' => $id, 'version' => 0, 'user_id' => $_SESSION['loggedInUser']['User']['id'])));
		$isAllowedToContribute = false;
		$isAllowedToContribute2 = false;
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['level'] >= 40)
				$isAllowedToContribute = true;
			else if($_SESSION['loggedInUser']['User']['elo_rating_mode'] >= 1500)
				$isAllowedToContribute = true;
			
			if($_SESSION['loggedInUser']['User']['isAdmin']>0){
				$isAllowedToContribute2 = true;
			}else{
				$tagsToCheck = $this->Tag->find('all', array('limit' => 20,'order' => 'created DESC', 'conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
				$datex = date('Y-m-d', strtotime('today'));
				for($i=0; $i<count($tagsToCheck); $i++){
					$datexx = new DateTime($tagsToCheck[$i]['Tag']['created']);
					$datexx = $datexx->format('Y-m-d');
					if($datex!==$datexx){
						$isAllowedToContribute2 = true;
					}
				}
				if(count($tagsToCheck)<20)
					$isAllowedToContribute2 = true;
			}
		}
		if(in_array($t['Tsumego']['set_id'], $setsWithPremium))
			$t['Tsumego']['premium'] = 1;
		else
			$t['Tsumego']['premium'] = 0;

		$checkFav = $inFavorite;
		if($inFavorite)
			$query = 'topics';

		if(count($navi)==3 && !isset($navi[0]['Tsumego']['id']) && !isset($navi[count($navi)-1]['Tsumego']['id']))
			$checkNotInSearch = true;
		else
			$checkNotInSearch = false;

		$isTSUMEGOinFAVORITE = $this->Favorite->find('first', array('conditions' => array('user_id' => $u['User']['id'], 'tsumego_id' => $id)));

		$this->set('isAllowedToContribute', $isAllowedToContribute);
		$this->set('isAllowedToContribute2', $isAllowedToContribute2);
		$this->set('hasSgfProposal', $sgfProposal!=null);
		$this->set('hasRevelation', $hasRevelation);
		$this->set('allTags', $allTags);
		$this->set('tags', $tags);
		$this->set('popularTags', $popularTags);
		$this->set('requestSignature', $requestSignature);
		$this->set('idForSignature', $idForSignature);
		$this->set('idForSignature2', $idForSignature2);
		$this->set('score3', $score3);
		$this->set('activityValue', $activityValue);
		$this->set('avActiveText', $avActiveText);
		$this->set('nothingInRange', $nothingInRange);
		$this->set('tRank', $tRank);
		$this->set('sgf', $sgf);
		$this->set('sgf2', $sgf2);
		$this->set('sandboxComment2', $sandboxComment2);
		$this->set('raName', $raName);
		$this->set('crs', $crs);
		$this->set('admins', $admins);
		$this->set('refresh', $refresh);
		$this->set('anz', $anzahl2);
		$this->set('showComment', $co);	
		$this->set('orientation', $orientation);
		$this->set('colorOrientation', $colorOrientation);
		$this->set('g', $refinementT[$refinementPublicCounter]);
		$this->set('favorite', $checkFav);
		$this->set('isTSUMEGOinFAVORITE', $isTSUMEGOinFAVORITE!=null);
		$this->set('hasAnyFavorite', $hasAnyFavorite!=null);
		$this->set('inFavorite', $inFavorite);
		$this->set('lastInFav', $lastInFav);
		$this->set('dailyMaximum', $dailyMaximum);
		$this->set('suspiciousBehavior', $suspiciousBehavior);
		$this->set('isSandbox', $isSandbox);
		$this->set('goldenTsumego', $goldenTsumego);
		$this->set('fullHeart', $fullHeart);
		$this->set('emptyHeart', $emptyHeart);
		$this->set('libertyCount', $t['Tsumego']['libertyCount']);
		$this->set('semeaiType', $t['Tsumego']['semeaiType']);
		$this->set('insideLiberties', $t['Tsumego']['insideLiberties']);
		$this->set('doublexp', $doublexp);
		$this->set('half', $half);
		$this->set('set', $set);
		$this->set('barPercent', $u['User']['xp']/$u['User']['nextlvl']*100);
		$this->set('user', $u);
		$this->set('t', $t);
		$this->set('score1', $score1);
		$this->set('score2', $score2);
		$this->set('navi', $navi);
		$this->set('prev', $prev);
		$this->set('next', $next);
		$this->set('hash', $hash);
		$this->set('nextMode', $nextMode);
		$this->set('mode', $mode);
		$this->set('rating', $u['User']['elo_rating_mode']);
		$this->set('eloScore', $eloScore);
		$this->set('eloScore2', $eloScore2);
		$this->set('eloScoreRounded', $eloScoreRounded);
		$this->set('eloScore2Rounded', $eloScore2Rounded);
		$this->set('activate', $activate);
		$this->set('tsumegoElo', $t['Tsumego']['elo_rating_mode']);
		$this->set('trs', $trs);
		$this->set('difficulty', $difficulty);
		$this->set('potion', $potion);
		$this->set('potionSuccess', $potionSuccess);
		$this->set('potionActive', $potionActive);
		$this->set('reviewCheat', $reviewCheat);
		$this->set('commentCoordinates', $commentCoordinates);
		$this->set('part', $t['Tsumego']['part']);
		$this->set('josekiLevel', $josekiLevel);
		$this->set('checkBSize', $checkBSize);
		$this->set('rankTs', $rankTs);
		$this->set('ranks', $ranks);
		$this->set('currentRank', $currentRank);
		$this->set('currentRankNum', $currentRankNum);
		$this->set('firstRanks', $firstRanks);
		$this->set('r10', $r10);
		$this->set('stopParameter', $stopParameter);
		$this->set('stopParameter2', $stopParameter2);
		$this->set('mode3ScoreArray', $mode3ScoreArray);
		$this->set('potionAlert', $potionAlert);
		$this->set('file', $file);
		$this->set('ui', $ui);
		$this->set('requestProblem', $requestProblem);
		$this->set('alternative_response', $t['Tsumego']['alternative_response']);
		$this->set('passEnabled', $t['Tsumego']['pass']);
		$this->set('virtual_children', $t['Tsumego']['virtual_children']);
		$this->set('set_duplicate', $t['Tsumego']['duplicate']);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('duplicates', $duplicates);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('requestSolution', $requestSolution);
		$this->set('startingPlayer', $startingPlayer);
		$this->set('tv', $tv);
		$this->set('query', $query);
		$this->set('queryTitle', $queryTitle);
		$this->set('queryTitleSets', $queryTitleSets);
		$this->set('search1', $search1);
		$this->set('search2', $search2);
		$this->set('search3', $search3);
		$this->set('amountOfOtherCollection', $amountOfOtherCollection);
		$this->set('partition', $partition);
		$this->set('checkNotInSearch', $checkNotInSearch);
		$this->set('hasPremium', $hasPremium);
  }

	private function getPopularTags($tags){
		$json = json_decode(file_get_contents('json/popular_tags.json'));
		$a = array();
		$tn = $this->TagName->find('all');
		$tnKeys = array();
		for($i=0;$i<count($tn);$i++)
			$tnKeys[$tn[$i]['TagName']['id']] = $tn[$i]['TagName']['name'];
		for($i=0;$i<count($json);$i++)
			array_push($a, $tnKeys[$json[$i]->id]);
		$aNew = array();
		$added = 0;
		$x = 0;
		while($added<10 && $x<count($a)){
			$found = false;
			for($i=0;$i<count($tags);$i++)
				if($a[$x] == $tags[$i]['Tag']['name'])
					$found = true;
			if(!$found){
				array_push($aNew, $a[$x]);
				$added++;
			}
			$x++;
		}
		return $aNew;
	}

	private function getTags($tsumego_id){
		$tags = $this->Tag->find('all', array('conditions' => array('tsumego_id' => $tsumego_id)));
		for($i=0;$i<count($tags);$i++){
			$tn = $this->TagName->findById($tags[$i]['Tag']['tag_name_id']);
			$tags[$i]['Tag']['name'] = $tn['TagName']['name'];
			$tags[$i]['Tag']['hint'] = $tn['TagName']['hint'];
		}
		return $tags;
	}

	private function checkTagDuplicates($array){
		$tagIds = array();
		$foundDuplicate = 0;
		$newArray = array();
		for($i=0; $i<count($array); $i++){
			if(!$this->inArrayX($array[$i], $newArray)){
				array_push($newArray, $array[$i]);
			}
		}
		return $newArray;
	}

	private function inArrayX($x, $newArray){
		for($i=0; $i<count($newArray); $i++){
			if($x['Tag']['tag_name_id'] == $newArray[$i]['Tag']['tag_name_id'] && $x['Tag']['approved'] == 1)
				return true;
		}
		return false;
	}

	public function open($id=null, $sgf1=null, $sgf2=null){
		$this->loadModel('Sgf');
		$s2 = null;
		$t = $this->Tsumego->findById($id);
		$s1 = $this->Sgf->findById($sgf1);
		$s1['Sgf']['sgf'] = str_replace('ß', 'ss', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace(';', '@', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\r", '', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\n", "€", $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("+", "%2B", $s1['Sgf']['sgf']);
		if($sgf2!=null){
			$s2 = $this->Sgf->findById($sgf2);
			$s2['Sgf']['sgf'] = str_replace('ß', 'ss', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace(';', '@', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\r", '', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\n", "€", $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("+", "%2B", $s2['Sgf']['sgf']);
		}
		$this->set('t', $t);
		$this->set('s1', $s1);
		$this->set('s2', $s2);
	}
	
	public function duplicatesearchx($id=null){
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		
		$maxDifference = 1;
		$includeSandbox = 'false';
		$includeColorSwitch = 'false';
		$hideSandbox=false;
		
		if(isset($this->params['url']['diff'])){
			$maxDifference = $this->params['url']['diff'];
			$includeSandbox = $this->params['url']['sandbox'];
			$includeColorSwitch = $this->params['url']['colorSwitch'];
			$loop = false;
		}else{
			$loop = true;
		}
		$similarId = array();
		$similarArr = array();
		$similarArrInfo = array();
		$similarArrBoardSize = array();
		$similarTitle = array();
		$similarDiff = array();
		$similarDiffType = array();
		$similarOrder = array();
		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', array('conditions' =>  array('tsumego_id' => $id)));
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'].' - '.$t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $id)));
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = count($tSgfArr[1]);
		
		$sets2 = array();
		$sets3 = array();
		$sets3content = array();
		$sets1 = $this->Set->find('all', array('conditions' => array(
			'public' => '1',
			'NOT' => array(
				'id' => array(6473, 11969, 29156, 31813, 33007, 71790, 74761, 81578)
			)
		)));
		
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['premium']!=1){
				$includeSandbox='false';
				$hideSandbox=true;
			}
			if($_SESSION['loggedInUser']['User']['premium']>=1){
				array_push($sets3content, 6473);
				array_push($sets3content, 11969);
				array_push($sets3content, 29156);
				array_push($sets3content, 31813);
				array_push($sets3content, 33007);
				array_push($sets3content, 71790);
				array_push($sets3content, 74761);
				array_push($sets3content, 81578);
			}
			$sets3 = $this->Set->find('all', array('conditions' => array('id' => $sets3content)));
		}else{
			$includeSandbox='false';
			$hideSandbox=true;
		}
		if($includeSandbox=='true')
			$sets2 = $this->Set->find('all', array('conditions' => array('public' => '0')));
		$sets = array_merge($sets1, $sets2, $sets3);
		
		for($h=0; $h<count($sets); $h++){
				$ts = $this->findTsumegoSet($sets[$h]['Set']['id']);
				for($i=0; $i<count($ts); $i++){
					if($ts[$i]['Tsumego']['id']!=$id){
						$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
						$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
						$numStones = count($sgfArr[1]);
						$stoneNumberDiff = abs($numStones-$tNumStones);
						if($stoneNumberDiff<=$maxDifference){
							if($includeColorSwitch=='true')
								$compare = $this->compare($tSgfArr[0], $sgfArr[0], true);
							else
								$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
							if($compare[0]<=$maxDifference){
								array_push($similarId, $ts[$i]['Tsumego']['id']);
								array_push($similarArr, $sgfArr[0]);
								array_push($similarArrInfo, $sgfArr[2]);
								array_push($similarArrBoardSize, $sgfArr[3]);
								array_push($similarDiff, $compare[0]);
								if($compare[1]==0) array_push($similarDiffType, '');
								else if($compare[1]==1) array_push($similarDiffType, 'Shifted position.');
								else if($compare[1]==2) array_push($similarDiffType, 'Shifted and rotated.');
								else if($compare[1]==3) array_push($similarDiffType, 'Switched colors.');
								else if($compare[1]==4) array_push($similarDiffType, 'Switched colors and shifted position.');
								else if($compare[1]==5) array_push($similarDiffType, 'Switched colors, shifted and rotated.');
								array_push($similarOrder, $compare[2]);
								$set = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
								$title2 = $set['Set']['title'].' - '.$ts[$i]['Tsumego']['num'];
								array_push($similarTitle, $title2);
							}
						}
					}
				}
			}
		
		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId);
		
		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
		$this->set('maxDifference', $maxDifference);
		$this->set('includeSandbox', $includeSandbox);
		$this->set('includeColorSwitch', $includeColorSwitch);
		$this->set('hideSandbox', $hideSandbox);
	}
	
	public function duplicatesearch($id=null){
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$this->loadModel('Signature');
		$_SESSION['page'] = 'play';
		
		$similarId = array();
		$similarArr = array();
		$similarArrInfo = array();
		$similarArrBoardSize = array();
		$similarTitle = array();
		$similarDiff = array();
		$similarDiffType = array();
		$similarOrder = array();
		
		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', array('conditions' =>  array('tsumego_id' => $id)));
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'].' - '.$t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $id)));
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = count($tSgfArr[1]);
		
		$_SESSION['title'] = $s['Set']['title'].' '.$t['Tsumego']['num'].' on Tsumego Hero';
		
		$t = $this->Tsumego->findById($id);
		$sig = $this->Signature->find('all', array('conditions' => array('tsumego_id' => $id)));
		$ts = array();
		for($i=0; $i<count($sig); $i++){
			$sig2 = $this->Signature->find('all', array('conditions' => array('signature' => $sig[$i]['Signature']['signature'])));
			for($j=0; $j<count($sig2); $j++){
				array_push($ts, $this->Tsumego->findById($sig2[$j]['Signature']['tsumego_id']));
			}
		}
		
		for($i=0; $i<count($ts); $i++){
			if($ts[$i]['Tsumego']['id']!=$id){
				$sgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
				$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
				$numStones = count($sgfArr[1]);
				$stoneNumberDiff = abs($numStones-$tNumStones);
				$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
				array_push($similarId, $ts[$i]['Tsumego']['id']);
				array_push($similarArr, $sgfArr[0]);
				array_push($similarArrInfo, $sgfArr[2]);
				array_push($similarArrBoardSize, $sgfArr[3]);
				array_push($similarDiff, $compare[0]);
				if($compare[1]==0) array_push($similarDiffType, '');
				else if($compare[1]==1) array_push($similarDiffType, 'Shifted position.');
				else if($compare[1]==2) array_push($similarDiffType, 'Shifted and rotated.');
				else if($compare[1]==3) array_push($similarDiffType, 'Switched colors.');
				else if($compare[1]==4) array_push($similarDiffType, 'Switched colors and shifted position.');
				else if($compare[1]==5) array_push($similarDiffType, 'Switched colors, shifted and rotated.');
				array_push($similarOrder, $compare[2]);
				$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
				$set = $this->Set->findById($scx['SetConnection']['set_id']);
				$title2 = $set['Set']['title'].' - '.$ts[$i]['Tsumego']['num'];
				array_push($similarTitle, $title2);
			}
		}
		
		if(count($similarOrder)>30){
			$orderCounter = 0;
			$orderThreshold = 5;
			while($orderCounter<30){
				$orderThreshold++;
				$orderCounter = 0;
				for($i=0; $i<count($similarOrder); $i++){
					if(substr($similarOrder[$i],0,2)<$orderThreshold)
						$orderCounter++;
				}
			}
			$similarOrder2 = array();
			$similarArr2 = array();
			$similarArrInfo2 = array();
			$similarTitle2 = array();
			$similarDiff2 = array();
			$similarDiffType2 = array();
			$similarId2 = array();
			$similarArrBoardSize2 = array();
			
			for($i=0; $i<count($similarOrder); $i++){
				if(substr($similarOrder[$i],0,2)<$orderThreshold){
					array_push($similarOrder2, $similarOrder[$i]);
					array_push($similarArr2, $similarArr[$i]);
					array_push($similarArrInfo2, $similarArrInfo[$i]);
					array_push($similarTitle2, $similarTitle[$i]);
					array_push($similarDiff2, $similarDiff[$i]);
					array_push($similarDiffType2, $similarDiffType[$i]);
					array_push($similarId2, $similarId[$i]);
					array_push($similarArrBoardSize2, $similarArrBoardSize[$i]);
				}
			}
			$similarOrder = $similarOrder2;
			$similarArr = $similarArr2;
			$similarArrInfo = $similarArrInfo2;
			$similarTitle = $similarTitle2;
			$similarDiff = $similarDiff2;
			$similarDiffType = $similarDiffType2;
			$similarId = $similarId2;
			$similarArrBoardSize = $similarArrBoardSize2;
		}
		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId, $similarArrBoardSize);
	
		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
	}

	private function getTheIdForTheThing($num){
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$t = array();
		$s = $this->Set->find('all', array('order' => 'id ASC', 'conditions' => array('public' => 1)));
		for($i=0; $i<count($s); $i++){
			$sc = $this->SetConnection->find('all', array('order' => 'tsumego_id ASC', 'conditions' => array('set_id' => $s[$i]['Set']['id'])));
			for($j=0; $j<count($sc); $j++){
				array_push($t, $sc[$j]['SetConnection']['tsumego_id']);
			}
		}
		if($num>=count($t))
			return -1;
		return $t[$num];
	}
	
	private function checkEloAdjust($t){
		$ta = $this->TsumegoAttempt->find('all', array('limit' => 10, 'order' => 'created DESC', 'conditions' => array(
			'tsumego_id' => $t['Tsumego']['id'],
			'NOT' => array(
				'tsumego_elo' => 0
			)
		)));
		$taPre = 3000;
		$jumpBack = $ta[0]['TsumegoAttempt']['tsumego_elo'];
		$jumpBackAmount = 0;
		$jumpI = 99;
		for($i=0;$i<count($ta);$i++){
			$taPreSum = $ta[$i]['TsumegoAttempt']['tsumego_elo'] - $taPre;
			$taPre = $ta[$i]['TsumegoAttempt']['tsumego_elo'];
			if($taPreSum > 500){
				$jumpBack = $taPre;
				$jumpBackAmount = $taPreSum;
				$jumpI = $i;
			}
		}

		if($jumpBackAmount!=0){
			for($i=0;$i<count($ta);$i++){
				if($i<$jumpI)
					$this->TsumegoAttempt->delete($ta[$i]['TsumegoAttempt']['id']);
				if($i==$jumpI)
					$t['Tsumego']['elo_rating_mode'] = $ta[$i]['TsumegoAttempt']['tsumego_elo'];
			}
		}
		return $t;
	}

	private function checkCommentValid($uid){
		$comments = $this->Comment->find('all', array('limit' => 5, 'order' => 'created DESC', 'conditions' => array('user_id' => $uid)));
		$limitReachedCounter = 0;
		for($i=0;$i<count($comments);$i++){
			$d = new DateTime($comments[$i]['Comment']['created']);
			$d = $d->format('Y-m-d');
			if($d == date('Y-m-d'))
				$limitReachedCounter++;
		}
		if($limitReachedCounter>=50) return false;
		return true;
	}
	
	private function getStartingPlayer($sgf){
		$bStart = strpos($sgf, ';B');
		$wStart = strpos($sgf, ';W');
		if($wStart==0)
			return 0;
		else if($bStart==0 && $wStart!=0)
			return 1;
		if($bStart<=$wStart)
			return 0;
		else
			return 1;
	}
	
	private function getLowest($a){
		$lowestX = 19;
		$lowestY = 19;
		for($y=0; $y<count($a); $y++){
			for($x=0; $x<count($a[$y]); $x++){
				if($a[$x][$y]=='x' || $a[$x][$y]=='o'){
					if($x<$lowestX)
						$lowestX = $x;
					if($y<$lowestY)
						$lowestY = $y;
				}					
			}
		}
		$arr = array();
		array_push($arr, $lowestX);
		array_push($arr, $lowestY);
		return $arr;
	}
	private function shiftToCorner($a, $lowestX, $lowestY){
		if($lowestX!=0){
			for($y=0; $y<count($a); $y++){
				for($x=0; $x<count($a[$y]); $x++){
					if($a[$x][$y]=='x' || $a[$x][$y]=='o'){
						$c = $a[$x][$y];
						$a[$x-$lowestX][$y] = $c;
						$a[$x][$y] = '-';
					}					
				}
			}
		}
		if($lowestY!=0){
			for($y=0; $y<count($a); $y++){
				for($x=0; $x<count($a[$y]); $x++){
					if($a[$x][$y]=='x' || $a[$x][$y]=='o'){
						$c = $a[$x][$y];
						$a[$x][$y-$lowestY] = $c;
						$a[$x][$y] = '-';
					}					
				}
			}
		}
		return $a;
	}
	private function displayArray($b, $trigger=false){
		if($trigger)
		for($y=0; $y<count($b); $y++){
			for($x=0; $x<count($b[$y]); $x++){
				echo '&nbsp;&nbsp;'.$b[$x][$y].' ';
			}
			if($y!=18) echo '<br>';
		}
	}
	private function compare($a, $b, $switch=false){
		$compare = array();
		$this->displayArray($a);
		$diff1 = $this->compareSingle($a, $b);
		array_push($compare, $diff1);
		$this->displayArray($b);
		if($switch)
			$d = $this->colorSwitch($b);
		$arr = $this->getLowest($a);
		$a = $this->shiftToCorner($a, $arr[0], $arr[1]);
		$arr = $this->getLowest($b);
		$b = $this->shiftToCorner($b, $arr[0], $arr[1]);
		if($switch)
			$c = $this->colorSwitch($b);
		$diff2 = $this->compareSingle($a, $b);
		array_push($compare, $diff2);
		$this->displayArray($b);
	
		$b = $this->mirror($b);
		$diff3 = $this->compareSingle($a, $b);
		array_push($compare, $diff3);
		$this->displayArray($b);
		
		if($switch){
			$diff4 = $this->compareSingle($a, $d);
			array_push($compare, $diff4);
			$this->displayArray($d);
			
			$this->displayArray($c);
			$diff5 = $this->compareSingle($a, $c);
			array_push($compare, $diff5);
			
			$c = $this->mirror($c);
			$diff6 = $this->compareSingle($a, $c);
			array_push($compare, $diff6);
			$this->displayArray($c);
		}
		$lowestCompare = 6;
		$lowestCompareNum = 100;
		for($i=0; $i<count($compare); $i++){
			if($compare[$i]<$lowestCompareNum){
				$lowestCompareNum = $compare[$i];
				$lowestCompare = $i;
			}	
		}
		if($lowestCompareNum<10)
			$lowestCompareNum = '0'.$lowestCompareNum;
		else if($lowestCompareNum>99)
			$lowestCompareNum = 99;
		$order = $lowestCompareNum.'-'.$lowestCompare;
		return array($lowestCompareNum, $lowestCompare, $order);
	}
	
	private function colorSwitch($b){
		for($y=0; $y<count($b); $y++){
			for($x=0; $x<count($b[$y]); $x++){
				if($b[$x][$y]=='x')
					$b[$x][$y]='o';
				else if($b[$x][$y]=='o')
					$b[$x][$y]='x';
			}
		}
		return $b;
	}
	
	private function compareSingle($a, $b){
		$diff = 0;
		for($y=0; $y<count($b); $y++){
			for($x=0; $x<count($b[$y]); $x++){
				if($a[$x][$y]!=$b[$x][$y])
					$diff++;
			}
		}
		return $diff;
	}
	
	private function mirror($a){
		$a1 = array();
		$black = array();
		$white = array();
		for($y=0; $y<count($a); $y++){
			$a1[$y] = array();
			for($x=0; $x<count($a[$y]); $x++)
				$a1[$y][$x] = $a[$x][$y];
		}
		return $a1;
	}

	private function findUt($id=null, $utsMap=null){
		if(!isset($utsMap[$id]))
			return null;
		$ut = array();
		$ut['TsumegoStatus']['tsumego_id'] = $id;
		$ut['TsumegoStatus']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
		$ut['TsumegoStatus']['status'] = $utsMap[$id];
		$ut['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		return $ut;
	}
	
	private function compileBoardState($black=null, $white=null, $m=null, $ms=null, $index=null){
		$array = array();
		$bString = '';
		for($i=0; $i<count($black); $i++){
			if($i%2==0){
				array_push($array, 'B-'.$black[$i].'-'.$black[$i+1].';');
			}
		}
		for($i=0; $i<count($white); $i++){
			if($i%2==0){
				array_push($array, 'W-'.$white[$i].'-'.$white[$i+1].';');
			}
		}
		
		$array2 = array();
		array_push($array2, 'B-'.$ms[$index][0].'-'.$ms[$index][1].';');
		if(is_numeric($ms[$index][4])) array_push($array2, 'W-'.$ms[$index][4].'-'.$ms[$index][5].';');
		
		$parent = $ms[$index][3];
		$layer = $ms[$index][2];
		while($layer!=0){
			$layer--;
			for($i=0; $i<count($ms); $i++){
				if($ms[$i][6]==$parent && $ms[$i][2]==$layer){
					array_push($array2, 'B-'.$ms[$i][0].'-'.$ms[$i][1].';');
					array_push($array2, 'W-'.$ms[$i][4].'-'.$ms[$i][5].';');
					$parent = $ms[$i][3];
				}
			}
			
		}
		
		$array3 = $array2;
		sort($array3);
		
		for($i=0; $i<count($array3); $i++){
			$bString .= $array3[$i];
		}
		
		return $bString;
	}
	
	private function findCoords($master=null){
		$m = str_split($master);
		$n = '';
		for($i=0; $i<count($m); $i++){
			if(preg_match('/[a-tA-T]/', $m[$i]) && is_numeric($m[$i+1])){
				if(!preg_match('/[a-tA-T]/', $m[$i-1]) && !is_numeric($m[$i-1])){
					if(is_numeric($m[$i+2])){
						if(!preg_match('/[a-tA-T]/', $m[$i+3]) && !is_numeric($m[$i+3])){
							$n .= $m[$i].$m[$i+1].$m[$i+2].' '.$i.'/'.($i+2).' ';
						}
					}else{
						if(!preg_match('/[a-tA-T]/', $m[$i+2])){
							$n .= $m[$i].$m[$i+1].' '.$i.'/'.($i+1).' ';
						}
					}
				}
			}			
		}
		if(substr($n, -1)==' ') $n = substr($n, 0, -1);
		return $n;
	}
	
	private function commentCoordinates($c=null, $counter=null, $noSyntax=null){
		$m = str_split($c);
		$n = '';
		$n2 = '';
		$hasLink = false;
		for($i=0; $i<count($m); $i++){
			if(isset($m[$i+1]))
			if(preg_match('/[a-tA-T]/', $m[$i]) && is_numeric($m[$i+1])){
				if(!preg_match('/[a-tA-T]/', $m[$i-1]) && !is_numeric($m[$i-1])){
					if(is_numeric($m[$i+2])){
						if(!preg_match('/[a-tA-T]/', $m[$i+3]) && !is_numeric($m[$i+3])){
							$n .= $m[$i].$m[$i+1].$m[$i+2].' ';
							$n2 .= $i.'/'.($i+2).' ';
						}
					}else{
						if(!preg_match('/[a-tA-T]/', $m[$i+2])){
							$n .= $m[$i].$m[$i+1].' ';
							$n2 .= $i.'/'.($i+1).' ';
						}
					}
				}
			}
			
			if($m[$i]=='<' && $m[$i+1]=='/' && $m[$i+2]=='a' && $m[$i+3]=='>') $hasLink=true;
		}
		if(substr($n, -1)==' ') $n = substr($n, 0, -1);
		if(substr($n2, -1)==' ') $n2 = substr($n2, 0, -1);
		
		if($hasLink)
			$n2=array();
		$coordForBesogo = array();
		
		if(strlen($n2)>1){
			$n2x = explode(' ', $n2);
			$fn = 1;
			for($i=count($n2x)-1; $i>=0; $i--){
				$n2xx = explode('/', $n2x[$i]);
				$a = substr($c, 0, $n2xx[0]);
				$cx = substr($c, $n2xx[0], $n2xx[1]-$n2xx[0]+1);
				if($noSyntax) $b = '<a href="#" title="original: '.$cx.'" id="ccIn'.$counter.$fn.'" onmouseover="ccIn'.$counter.$fn.'()" onmouseout="ccOut'.$counter.$fn.'()" return false;>';
				else $b = '<a href=\"#\" title="original: '.$cx.'" id="ccIn'.$counter.$fn.'" onmouseover=\"ccIn'.$counter.$fn.'()\" onmouseout=\"ccOut'.$counter.$fn.'()\" return false;>';
				
				$d = '</a>';
				$e = substr($c, $n2xx[1]+1, strlen($c)-1);
				$coordForBesogo[$i] = $cx;
				$c = $a.$b.$cx.$d.$e;
				$fn++;
			}
		}
		
		$xx = explode(' ', $n);
		$coord1 = 0;
		$finalCoord = '';
		for($i=0; $i<count($xx); $i++){
			if(strlen($xx[$i])>1){
				$xxxx = array();
				$xxx = str_split($xx[$i]);
				for($j=0; $j<count($xxx); $j++){
					if(preg_match('/[0-9]/', $xxx[$j])) array_push($xxxx, $xxx[$j]);
					if(preg_match('/[a-tA-T]/', $xxx[$j])) $coord1 = $this->convertCoord($xxx[$j]);
				}
				$coord2 = $this->convertCoord2(implode($xxxx));
				if($coord1!=-1 &&$coord2!=-1) $finalCoord .= $coord1.'-'.$coord2.'-'.$coordForBesogo[$i].' ';
			}
		}
		if(substr($finalCoord, -1)==' ') $finalCoord = substr($finalCoord, 0, -1);
		
		$array = array();
		array_push($array, $c);
		array_push($array, $finalCoord);
		return $array;
	}
	
	private function findNextMoves($master=null, $m=null){
		$a = array();
		$solves = array();
		$root = $m[6];
		$rootlen = strlen($root);
		$level = $m[2];
		if($m[8]!=='w'){
			for($i=0; $i<count($master); $i++){
				if(substr($master[$i][6], 0, $rootlen) === $root){
					if($master[$i][2]==$level+1){
						array_push($a, $master[$i]);
					}
					if($master[$i][8]==='+'){
						array_push($solves, $master[$i]);
					}
				}
				
			}
			$subrootlen = strlen($a[0][6]);
			for($i=0; $i<count($a); $i++){
				for($j=0; $j<count($solves); $j++){
					if(substr($solves[$j][6], 0, $subrootlen) === $a[$i][6]) $a[$i][3] = '+';
				}
			}
		}
		return $a;
	}
	
	private function convertCoord($l=null){
		switch(strtolower($l)){
			case 'a': return 0;
			case 'b': return 1;
			case 'c': return 2;
			case 'd': return 3;
			case 'e': return 4;
			case 'f': return 5;
			case 'g': return 6;
			case 'h': return 7;
			case 'j': return 8;
			case 'k': return 9;
			case 'l': return 10;
			case 'm': return 11;
			case 'n': return 12;
			case 'o': return 13;
			case 'p': return 14;
			case 'q': return 15;
			case 'r': return 16;
			case 's': return 17;
			case 't': return 18;
		}
		return 0;
	}
	private function convertCoord2($n=null){
		switch($n){
			case '0': return 19;
			case '1': return 18;
			case '2': return 17;
			case '3': return 16;
			case '4': return 15;
			case '5': return 14;
			case '6': return 13;
			case '7': return 12;
			case '8': return 11;
			case '9': return 10;
			case '10': return 9;
			case '11': return 8;
			case '12': return 7;
			case '13': return 6;
			case '14': return 5;
			case '15': return 4;
			case '16': return 3;
			case '17': return 2;
			case '18': return 1;
		}
		return 0;
	}
	
	private function isAllowedSet($sets, $s){
		$found = false;
		for($i=0; $i<count($sets); $i++)
			if($sets[$i]==$s)
				$found = true;
		return $found;
	}
	
	// Returns 2 updated ratings, RD initialization for user has to be done manually before this function
	// old_u is an array [old_r, old_rd] for the user and old_t for the tsumego
	// Success is given from the point of view of the user and is 1.0 or 0
	private function compute_rating($old_u, $old_t, $success){
		$old_u['old_rd'] += 40;
		$new_u = $this->compute_single_rating($old_u, $old_t, $success);
		$new_u[1] -= 40;
		
		$new_t = $this->compute_single_rating($old_t, $old_u, 1.0 - $success);
		
		return array($new_u, $new_t);
	}

	// Computes the rating of the first provided one given its own success
	private function compute_single_rating($old_1, $old_2, $success){
		$r_1 = $old_1['old_r'];
		$rd_1 = $old_1['old_rd'];
		$r_2 = $old_2['old_r'];
		$rd_2 = $old_2['old_rd'];
		$g_rd_2 = 1;
		$g_rd = $this->g_rd($rd_2);
		$E_s = $this->E_s($r_1, $r_2, $g_rd_2);
		$d_sq = 1.0 / (Q**2 * $g_rd**2 * $E_s * (1.0 - $E_s));
		$new_rd = 1 / sqrt(1 / $rd_1**2 + 1 / $d_sq);
		$coeff = Q / (1 / $r_1**2 + 1 / $d_sq);
		$new_rating = $r_1 + $coeff * $g_rd * ($success - $E_s);
		return array($new_rating, $new_rd);
	}

	private function g_rd($rd) {
		return 1.0 / sqrt(1.0 + 3.0 * Q**2 * $rd**2 / pi() ** 2);
	}

	private function E_s($r_0, $r_i, $g_rd_i) {
		return 1.0 / (1.0 + 10.0 ** ($g_rd_i * ($r_0 - $r_i) / -400.0));
	}

	// Computes the rating deviation of a user after t days
	// Resets days not played to 0
	// Does nothing if the user already played rating mode today
	// This function has to be called before computing the new user rating and RD
	// TODO do we also do it for tsumego and not only for user ?
	private function compute_initial_user_rd($user){
		if(false){
			$t = $user['User']['t_glicko'];
			$old_rd = $user['User']['rd'];
			// Computes new RD after some time
			$current_rd = min(sqrt($old_rd**2 + C**2 * $t), INITIAL_RD);
			// Resets counter for next day
			$user['User']['t_glicko'] = 0;
			// Assigns initial RD for the day
			$user['User']['rd'] = $current_rd;
			
		}
		return $user;
	}
	
	public function valuetable(){
		$win = array();
		$loss = array();
		$twin = array();
		$tloss = array();
		$win2 = array();
		$loss2 = array();
		$win3 = array();
		$loss3 = array();
		$win4 = array();
		$loss4 = array();
		$u = 500;
		$t = 1600;
		$add = 50;
		$activityValue = 29;
		
		while($activityValue<=90){
			$u = 500;
			$t = 1600;
			$add = 50;
			while($u<=2600){
				$u += $add;
				$diff = abs($u-$t);
				if($u > $t)
					$eloBigger = 'u';
				else if($t >= $u)
					$eloBigger = 't';
				if($diff==0) $diff = .1;
				$newUserEloW = $this->getNewElo($diff, $eloBigger, $activityValue, 15352, 'w');
				$newUserEloL = $this->getNewElo($diff, $eloBigger, $activityValue, 15352, 'l');
				$tsumegoEloWin = $t + $newUserEloW['tsumego2'];
				$tsumegoEloLoss = $t + $newUserEloL['tsumego2'];
				$userEloWin = $u+$newUserEloW['user2'];
				$userEloLoss = $u+$newUserEloL['user2'];
				$varianceWin = $userEloWin-$u;
				$varianceLoss = $userEloLoss-$u;
				$varianceTsumegoWin = $tsumegoEloWin -$t;
				$varianceTsumegoLoss = $tsumegoEloLoss - $t;
				if($t>$u)
					$diff *= -1;
				if($activityValue==29){
					$win[$diff] = round($varianceWin, 2);
					$loss[$diff] = round($varianceLoss, 2);
					$twin[$diff] = round($varianceTsumegoWin);
					$tloss[$diff] = round($varianceTsumegoLoss);
				}else if($activityValue==49){
					$win2[$diff] = round($varianceWin, 2);
					$loss2[$diff] = round($varianceLoss, 2);
				}else if($activityValue==69){
					$win3[$diff] = round($varianceWin, 2);
					$loss3[$diff] = round($varianceLoss, 2);
				}else{
					$win4[$diff] = round($varianceWin, 2);
					$loss4[$diff] = round($varianceLoss, 2);
				}
			}
			$activityValue+=20;
		}
		$this->set('win', $win);
		$this->set('loss', $loss);
		$this->set('twin', $twin);
		$this->set('tloss', $tloss);
		$this->set('win2', $win2);
		$this->set('loss2', $loss2);
		$this->set('win3', $win3);
		$this->set('loss3', $loss3);
		$this->set('win4', $win4);
		$this->set('loss4', $loss4);
	}
}
?>


