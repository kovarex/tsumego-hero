<?php
class SitesController extends AppController{
    public $helpers = array('Html', 'Form');

	public function index($var=null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero';
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('User');
		$this->LoadModel('DayRecord');
		$this->LoadModel('UserBoard');
		$this->LoadModel('Schedule');
		$this->LoadModel('RankOverview');
		$this->LoadModel('Sgf');
		$this->LoadModel('SetConnection');
		$this->LoadModel('PublishDate');
		
		$tdates = array();
		$tSum = array();
		
		array_push($tdates, '2021-06-09 22:00:00');
		array_push($tdates, '2021-06-10 22:00:00');
		//array_push($tdates, '2020-11-22 22:00:00');
		
		for($i=0; $i<count($tdates); $i++){
			$ts1 = $this->Tsumego->find('all', array('conditions' =>  array('created' => $tdates[$i])));
			for($j=0; $j<count($ts1); $j++){
				array_push($tSum, $ts1[$j]);
			}
		}
		
		$newTS = array();
		$setIDs = array();
		//$setNames = array();
		//$setDates = array();
		/*
		for($i=0; $i<count($tSum); $i++){
			array_push($newTS, $tSum[$i]);
			if(!in_array($tSum[$i]['Tsumego']['set_id'], $setIDs)){
				array_push($setIDs, $tSum[$i]['Tsumego']['set_id']);
				$date = new DateTime($tSum[$i]['Tsumego']['created']);
				$month = date("F", strtotime($tSum[$i]['Tsumego']['created']));
				$tday = $date->format('d. ');
				$tyear = $date->format('Y');
				if($tday[0]==0) $tday = substr($tday, -3);
				$tSum[$i]['Tsumego']['created'] = $tday.$month.' '.$tyear;
				$setDates[$tSum[$i]['Tsumego']['set_id']] = $tSum[$i]['Tsumego']['created'];
			}
		}*/
		$uReward = $this->User->find('all', array('limit' => 5, 'order' => 'reward DESC'));
		$urNames = array();
		for($i=0;$i<count($uReward);$i++)
			array_push($urNames, $this->checkPicture($uReward[$i]));
		
		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', array('conditions' =>  array('date' => $today)));
		if(count($dateUser)==0) $dateUser = $this->DayRecord->find('first', array('conditions' =>  array('date' => date('Y-m-d', strtotime('yesterday')))));
		
		$totd = $this->Tsumego->findById($dateUser['DayRecord']['tsumego']);
		$popularTooltip = array();
		$popularTooltipInfo = array();
		$popularTooltipBoardSize = array();
		$ptts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $totd['Tsumego']['id'])));
		$ptArr = $this->processSGF($ptts[0]['Sgf']['sgf']);
		$popularTooltip = $ptArr[0];
		$popularTooltipInfo = $ptArr[2];
		$popularTooltipBoardSize = $ptArr[3];
		
		$newT = $this->Tsumego->findById($dateUser['DayRecord']['newTsumego']);
		$newTschedule = $this->Schedule->find('all', array('conditions' =>  array('date' => $today)));
		
		$scheduleTsumego = array();
		for($i=0; $i<count($newTschedule); $i++){
			array_push($scheduleTsumego, $this->Tsumego->findById($newTschedule[$i]['Schedule']['tsumego_id']));
		}
		
		$tooltipSgfs = array();
		$tooltipInfo = array();
		$tooltipBoardSize = array();
		for($i=0; $i<count($scheduleTsumego); $i++){
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $scheduleTsumego[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		
		if(isset($_SESSION['loggedInUser'])){
			$idArray = array();
			array_push($idArray, $totd['Tsumego']['id']);
			for($i=0; $i<count($scheduleTsumego); $i++){
				array_push($idArray, $scheduleTsumego[$i]['Tsumego']['id']);
			}
			
		
			$uts = $this->TsumegoStatus->find('all', array('order' => 'created DESC', 'conditions' => array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'],
				'tsumego_id' => $idArray
			)));
			
			for($i=0; $i<count($uts); $i++){
				for($j=0; $j<count($newTS); $j++){
					if($uts[$i]['TsumegoStatus']['tsumego_id'] == $newTS[$j]['Tsumego']['id']){
						$newTS[$j]['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}
				for($j=0; $j<count($scheduleTsumego); $j++){
					if($uts[$i]['TsumegoStatus']['tsumego_id'] == $scheduleTsumego[$j]['Tsumego']['id']){
						$scheduleTsumego[$j]['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}
				
				if(isset($totd['Tsumego']['id']))
					if($uts[$i]['TsumegoStatus']['tsumego_id'] == $totd['Tsumego']['id']) 
						$totd['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
				
				if(isset($newT['Tsumego']['id']))
					if($uts[$i]['TsumegoStatus']['tsumego_id'] == $newT['Tsumego']['id']) 
						$newT['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
			}
		}
		if(!isset($_SESSION['loggedInUser'])){
			if(isset($_SESSION['noLogin'])){
				$noLogin = $_SESSION['noLogin'];
				$noLoginStatus = $_SESSION['noLoginStatus'];
				for($i=0; $i<count($noLogin); $i++){
					for($f=0; $f<count($newTS); $f++){
						if($newTS[$f]['Tsumego']['id']==$noLogin[$i]){
							$newTS[$f]['Tsumego']['status'] = $noLoginStatus[$i];
						}
					}
					for($f=0; $f<count($scheduleTsumego); $f++){
						if($scheduleTsumego[$f]['Tsumego']['id']==$noLogin[$i]){
							$scheduleTsumego[$f]['Tsumego']['status'] = $noLoginStatus[$i];
						}
					}
					if($noLogin[$i] == $totd['Tsumego']['id']) $totd['Tsumego']['status'] = $noLoginStatus[$i];
					if($noLogin[$i] == $newT['Tsumego']['id']) $newT['Tsumego']['status'] = $noLoginStatus[$i];
				}
			}
		}
		
		if(!isset($totd['Tsumego']['status'])) $totd['Tsumego']['status'] = 'N';
		if(!isset($newT['Tsumego']['status'])) $newT['Tsumego']['status'] = 'N';
		for($i=0; $i<count($scheduleTsumego); $i++){
			if(!isset($scheduleTsumego[$i]['Tsumego']['status']))
				$scheduleTsumego[$i]['Tsumego']['status'] = 'N';
		}
		
		$d1 = date(' d, Y');
		$d1day = date('d. ');
		$d1year = date('Y');
		if($d1day[0]==0) $d1day = substr($d1day, -3);
		$d2 = date('Y-m-d H:i:s');
		$month = date("F", strtotime(date('Y-m-d')));
		$d1 = $d1day.$month.' '.$d1year;
		$currentQuote = $dateUser['DayRecord']['quote'];
		$currentQuote = 'q13';
		$userOfTheDay = $this->User->find('first', array('conditions' => array('id' => $dateUser['DayRecord']['user_id'])));
		
		//echo '<pre>';print_r($dateUser);echo '</pre>';

		$totdSc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $totd['Tsumego']['id'])));
		$totdS = $this->Set->findById($totdSc['SetConnection']['set_id']);
		$newTSc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $newT['Tsumego']['id'])));
		$newTS = $this->Set->findById($newTSc['SetConnection']['set_id']);
		
		$totd['Tsumego']['set'] = $totdS['Set']['title'];
		$totd['Tsumego']['set2'] = $totdS['Set']['title2'];
		$totd['Tsumego']['set_id'] = $totdS['Set']['id'];
		$newT['Tsumego']['set'] = $newTS['Set']['title'];
		$newT['Tsumego']['set2'] = $newTS['Set']['title2'];
		$newT['Tsumego']['set_id'] = $newTS['Set']['id'];
		

		$this->set('userOfTheDay', $this->checkPictureLarge($userOfTheDay));
		$this->set('uotdbg', $dateUser['DayRecord']['userbg']);
		
		//recently visited
		/*if(isset($_SESSION['loggedInUser'])){
			$currentUser = $this->User->find('first', array('conditions' =>  array('id' => $_SESSION['loggedInUser']['User']['id'])));
			$currentUser['User']['created'] = date('Y-m-d H:i:s');
			$this->User->save($currentUser);
			
			$visit = $this->Visit->find('all', 
				array('order' => 'created',	'direction' => 'DESC', 'conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id']))
			);
			
			$setVisit1 = $this->Set->find('first', array('conditions' => array('id' => $visit[count($visit)-1]['Visit']['set_id'])));
			$this->set('visit1', $setVisit1);
			if(count($visit)>1){
				$setVisit2 = $this->Set->find('first', array('conditions' => array('id' => $visit[count($visit)-2]['Visit']['set_id'])));
				$this->set('visit2', $setVisit2);
				if(count($visit)>2){
					$setVisit3 = $this->Set->find('first', array('conditions' => array('id' => $visit[count($visit)-3]['Visit']['set_id'])));
					$this->set('visit3', $setVisit3);
				}
			}
		}
		
		$tsumegoDates = array();
		$scDates = array();
		$tsumegos = $this->SetConnection->find('all');
		
		$setKeys = array();
		$setArray = $this->Set->find('all', array('conditions' => array('public' => 1)));
		for($i=0; $i<count($setArray); $i++)
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];
		
		for($j=0; $j<count($tsumegos); $j++)
			if(isset($setKeys[$tsumegos[$j]['SetConnection']['set_id']]))
				array_push($scDates, $tsumegos[$j]['SetConnection']['tsumego_id']);
		
		$tdates = $this->Tsumego->find('all', array('conditions' => array('id' => $scDates)));
		for($j=0; $j<count($tdates); $j++){
			array_push($tsumegoDates, $tdates[$j]['Tsumego']['created']);
		}
		*/
		$tsumegoDates = array();
		$pd = $this->PublishDate->find('all', array('order' => 'date ASC'));
		for($j=0; $j<count($pd); $j++)
			array_push($tsumegoDates, $pd[$j]['PublishDate']['date']);
		$deletedS = $this->getDeletedSets();
		
		$setsWithPremium = array();
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++)
			array_push($setsWithPremium, $swp[$i]['Set']['id']);
		$totd = $this->checkForLocked($totd, $setsWithPremium);
		
		for($i=0;$i<count($scheduleTsumego);$i++)
			$scheduleTsumego[$i] = $this->checkForLocked($scheduleTsumego[$i], $setsWithPremium);

		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			$hasPremium = false;
		else
			$hasPremium = true;

		//echo '<pre>';print_r($currentQuote);echo '</pre>';

		$this->set('hasPremium', $hasPremium);
		$this->set('tsumegos', $tsumegoDates);
		$this->set('quote', $currentQuote);
		$this->set('d1', $d1);
		//$this->set('setNames', $setNames);
		//$this->set('setDates', $setDates);
		$this->set('totd', $totd);
		$this->set('newT', $newT);
		$this->set('scheduleTsumego', $scheduleTsumego);
		$this->set('dateUser', $dateUser);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('popularTooltip', $popularTooltip);
		$this->set('popularTooltipInfo', $popularTooltipInfo);
		$this->set('popularTooltipBoardSize', $popularTooltipBoardSize);
		$this->set('urNames', $urNames);
    }
	
	public function view($id=null){
		$news = $this->Site->find('all');	
		$this->set('news', $news[$id]);
	}
	
	public function impressum(){
		$_SESSION['page'] = 'about';
		$_SESSION['title'] = 'Tsumego Hero - Legal Notice';
	}
	
	public function websitefunctions(){
		$_SESSION['page'] = 'websitefunctions';
		$_SESSION['title'] = 'Tsumego Hero - Website Functions';
	}
	
	public function gotutorial(){
		$_SESSION['page'] = 'gotutorial';
		$_SESSION['title'] = 'Tsumego Hero - Go Tutorial';
	}

	public function privacypolicy(){
		$_SESSION['page'] = 'privacypolicy';
		$_SESSION['title'] = 'Tsumego Hero - Privacy Policy';
	}
	
	public function ugco5ujc(){
	
	}
}




