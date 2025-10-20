<?php
class SitesController extends AppController {

	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function index($var = null) {
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero');
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

		$tdates = [];
		$tSum = [];

		array_push($tdates, '2021-06-09 22:00:00');
		array_push($tdates, '2021-06-10 22:00:00');
		//array_push($tdates, '2020-11-22 22:00:00');

		foreach ($tdates as $tdate) {
			$ts1 = $this->Tsumego->find('all', ['conditions' => ['created' => $tdate]]);
			if (!$ts1) {
				$ts1 = [];
			}
			foreach ($ts1 as $item) {
				$tSum[] = $item;
			}
		}

		$newTS = [];
		$setIDs = [];
		//$setNames = array();
		//$setDates = array();
		/*
		$tSumCount = count($tSum);
		for ($i=0;$i<$tSumCount;$i++) {
			array_push($newTS, $tSum[$i]);
			if (!in_array($tSum[$i]['Tsumego']['set_id'], $setIDs)) {
				array_push($setIDs, $tSum[$i]['Tsumego']['set_id']);
				$date = new DateTime($tSum[$i]['Tsumego']['created']);
				$month = date("F", strtotime($tSum[$i]['Tsumego']['created']));
				$tday = $date->format('d. ');
				$tyear = $date->format('Y');
				if ($tday[0]==0) $tday = substr($tday, -3);
				$tSum[$i]['Tsumego']['created'] = $tday.$month.' '.$tyear;
				$setDates[$tSum[$i]['Tsumego']['set_id']] = $tSum[$i]['Tsumego']['created'];
			}
		}*/
		$uReward = $this->User->find('all', ['limit' => 5, 'order' => 'reward DESC']);
		if (!$uReward) {
			$uReward = [];
		}
		$urNames = [];
		foreach ($uReward as $user) {
			$urNames[] = $this->checkPicture($user);
		}

		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', ['conditions' => ['date' => $today]]);
		if (!$dateUser) {
			$dateUser = $this->DayRecord->find('first', ['conditions' => ['date' => date('Y-m-d', strtotime('yesterday'))]]);
		}
		if (!$dateUser) {
			$this->redirect(['controller' => 'sets', 'action' => 'index']);

			return;
		}

		$totd = $this->Tsumego->findById($dateUser['DayRecord']['tsumego']);
		$popularTooltip = [];
		$popularTooltipInfo = [];
		$popularTooltipBoardSize = [];
		$ptts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $totd['Tsumego']['id']]]);
		if (!$ptts) {
			$ptts = [];
		}
		$ptArr = $this->processSGF($ptts[0]['Sgf']['sgf']);
		$popularTooltip = $ptArr[0];
		$popularTooltipInfo = $ptArr[2];
		$popularTooltipBoardSize = $ptArr[3];

		$newT = $this->Tsumego->findById($dateUser['DayRecord']['newTsumego']);
		$newTschedule = $this->Schedule->find('all', ['conditions' => ['date' => $today]]);
		if (!$newTschedule) {
			$newTschedule = [];
		}

		$scheduleTsumego = [];
		foreach ($newTschedule as $scheduleItem) {
			$scheduleTsumego[] = $this->Tsumego->findById($scheduleItem['Schedule']['tsumego_id']);
		}

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		foreach ($scheduleTsumego as $tsumego) {
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $tsumego['Tsumego']['id']]]);
			if (!$tts) {
				$tts = [];
			}
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			$tooltipSgfs[] = $tArr[0];
			$tooltipInfo[] = $tArr[2];
			$tooltipBoardSize[] = $tArr[3];
		}

		if ($this->isLoggedIn()) {
			$idArray = [];
			$idArray[] = $totd['Tsumego']['id'];
			foreach ($scheduleTsumego as $tsumego) {
				$idArray[] = $tsumego['Tsumego']['id'];
			}

			$uts = $this->TsumegoStatus->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'user_id' => $this->loggedInUserID(),
					'tsumego_id' => $idArray,
				],
			]);
			if (!$uts) {
				$uts = [];
			}

			$utsCount = count($uts);
			for ($i = 0;$i < $utsCount;$i++) {
				$newTSCount = count($newTS);
				for ($j = 0;$j < $newTSCount;$j++) {
					if ($uts[$i]['TsumegoStatus']['tsumego_id'] == $newTS[$j]['Tsumego']['id']) {
						$newTS[$j]['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}
				$scheduleTsumegoCount = count($scheduleTsumego);
				for ($j = 0;$j < $scheduleTsumegoCount;$j++) {
					if ($uts[$i]['TsumegoStatus']['tsumego_id'] == $scheduleTsumego[$j]['Tsumego']['id']) {
						$scheduleTsumego[$j]['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}

				if (isset($totd['Tsumego']['id'])) {
					if ($uts[$i]['TsumegoStatus']['tsumego_id'] == $totd['Tsumego']['id']) {
						$totd['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}

				if (isset($newT['Tsumego']['id'])) {
					if ($uts[$i]['TsumegoStatus']['tsumego_id'] == $newT['Tsumego']['id']) {
						$newT['Tsumego']['status'] = $uts[$i]['TsumegoStatus']['status'];
					}
				}
			}
		}
		if (!$this->isLoggedIn()) {
			if ($this->Session->check('noLogin')) {
				$noLogin = $this->Session->read('noLogin');
				$noLoginStatus = $this->Session->read('noLoginStatus');
				$noLoginCount = count($noLogin);
				for ($i = 0;$i < $noLoginCount;$i++) {
					$newTSCount = count($newTS);
					for ($f = 0;$f < $newTSCount;$f++) {
						if ($newTS[$f]['Tsumego']['id'] == $noLogin[$i]) {
							$newTS[$f]['Tsumego']['status'] = $noLoginStatus[$i];
						}
					}
					$scheduleTsumegoCount = count($scheduleTsumego);
					for ($f = 0;$f < $scheduleTsumegoCount;$f++) {
						if ($scheduleTsumego[$f]['Tsumego']['id'] == $noLogin[$i]) {
							$scheduleTsumego[$f]['Tsumego']['status'] = $noLoginStatus[$i];
						}
					}
					if ($noLogin[$i] == $totd['Tsumego']['id']) {
						$totd['Tsumego']['status'] = $noLoginStatus[$i];
					}
					if ($noLogin[$i] == $newT['Tsumego']['id']) {
						$newT['Tsumego']['status'] = $noLoginStatus[$i];
					}
				}
			}
		}

		if (!isset($totd['Tsumego']['status'])) {
			$totd['Tsumego']['status'] = 'N';
		}
		if (!isset($newT['Tsumego']['status'])) {
			$newT['Tsumego']['status'] = 'N';
		}
		$scheduleTsumegoCount = count($scheduleTsumego);
		for ($i = 0;$i < $scheduleTsumegoCount;$i++) {
			if (!isset($scheduleTsumego[$i]['Tsumego']['status'])) {
				$scheduleTsumego[$i]['Tsumego']['status'] = 'N';
			}
		}

		$d1 = date(' d, Y');
		$d1day = date('d. ');
		$d1year = date('Y');
		if ($d1day[0] == 0) {
			$d1day = substr($d1day, -3);
		}
		$d2 = date('Y-m-d H:i:s');
		$month = date('F', strtotime(date('Y-m-d')));
		$d1 = $d1day . $month . ' ' . $d1year;
		$currentQuote = $dateUser['DayRecord']['quote'];
		$currentQuote = 'q13';
		$userOfTheDay = $this->User->find('first', ['conditions' => ['id' => $dateUser['DayRecord']['user_id']]]);
		if (!$userOfTheDay) {
			$userOfTheDay = ['User' => ['id' => 0, 'name' => 'Guest']];
		}

		//echo '<pre>';print_r($dateUser);echo '</pre>';

		$totdSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $totd['Tsumego']['id']]]);
		if ($totdSc) {
			$totdS = $this->Set->findById($totdSc['SetConnection']['set_id']);
			if ($totdS) {
				$totd['Tsumego']['set'] = $totdS['Set']['title'];
				$totd['Tsumego']['set2'] = $totdS['Set']['title2'];
				$totd['Tsumego']['set_id'] = $totdS['Set']['id'];
			}
		}
		$newTSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newT['Tsumego']['id']]]);
		if ($newTSc) {
			$newTS = $this->Set->findById($newTSc['SetConnection']['set_id']);
			if ($newTS) {
				$newT['Tsumego']['set'] = $newTS['Set']['title'];
				$newT['Tsumego']['set2'] = $newTS['Set']['title2'];
				$newT['Tsumego']['set_id'] = $newTS['Set']['id'];
			}
		}

		$this->set('userOfTheDay', $this->checkPictureLarge($userOfTheDay));
		$this->set('uotdbg', $dateUser['DayRecord']['userbg']);

		//recently visited
		/*if ($this->isLoggedIn()) {
			$currentUser = $this->User->find('first', array('conditions' =>  array('id' => $this->loggedInUserID())));
			$currentUser['User']['created'] = date('Y-m-d H:i:s');
			$this->User->save($currentUser);

			$visit = $this->Visit->find('all',
				array('order' => 'created',	'direction' => 'DESC', 'conditions' =>  array('user_id' => $this->loggedInUserID()))
			);

			$setVisit1 = $this->Set->find('first', array('conditions' => array('id' => $visit[count($visit)-1]['Visit']['set_id'])));
			$this->set('visit1', $setVisit1);
			if (count($visit)>1) {
				$setVisit2 = $this->Set->find('first', array('conditions' => array('id' => $visit[count($visit)-2]['Visit']['set_id'])));
				$this->set('visit2', $setVisit2);
				if (count($visit)>2) {
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
		foreach ($setArray as $set) {
			$setKeys[$set['Set']['id']] = $set['Set']['id'];
		}

		$tsumegosCount = count($tsumegos);
		for ($j=0; $j<$tsumegosCount; $j++)
			if (isset($setKeys[$tsumegos[$j]['SetConnection']['set_id']]))
				array_push($scDates, $tsumegos[$j]['SetConnection']['tsumego_id']);

		$tdates = $this->Tsumego->find('all', array('conditions' => array('id' => $scDates)));
		$tdatesCount = count($tdates);
		for ($j=0;$j<$tdatesCount;$j++) {
			array_push($tsumegoDates, $tdates[$j]['Tsumego']['created']);
		}
		*/
		$tsumegoDates = [];
		$pd = $this->PublishDate->find('all', ['order' => 'date ASC']);
		if (!$pd) {
			$pd = [];
		}
		foreach ($pd as $date) {
			$tsumegoDates[] = $date['PublishDate']['date'];
		}
		$deletedS = $this->getDeletedSets();

		$setsWithPremium = [];
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		if (!$swp) {
			$swp = [];
		}
		foreach ($swp as $set) {
			$setsWithPremium[] = $set['Set']['id'];
		}
		$totd = $this->checkForLocked($totd, $setsWithPremium);

		foreach ($scheduleTsumego as $i => $tsumego) {
			$scheduleTsumego[$i] = $this->checkForLocked($tsumego, $setsWithPremium);
		}

		$this->set('hasPremium', $this->hasPremium());
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

	/**
	 * @return void
	 */
	public function view($id = null) {
		$news = $this->Site->find('all');
		$this->set('news', $news[$id]);
	}

	/**
	 * @return void
	 */
	public function impressum() {
		$this->Session->write('page', 'about');
		$this->Session->write('title', 'Tsumego Hero - Legal Notice');
	}

	/**
	 * @return void
	 */
	public function websitefunctions() {
		$this->Session->write('page', 'websitefunctions');
		$this->Session->write('title', 'Tsumego Hero - Website Functions');
	}

	/**
	 * @return void
	 */
	public function gotutorial() {
		$this->Session->write('page', 'gotutorial');
		$this->Session->write('title', 'Tsumego Hero - Go Tutorial');
	}

	/**
	 * @return void
	 */
	public function privacypolicy() {
		$this->Session->write('page', 'privacypolicy');
		$this->Session->write('title', 'Tsumego Hero - Privacy Policy');
	}

	/**
	 * @return void
	 */
	public function ugco5ujc() {
	}

}
