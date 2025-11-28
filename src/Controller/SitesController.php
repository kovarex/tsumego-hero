<?php

App::uses('SgfParser', 'Utility');

class SitesController extends AppController
{
	public $helpers = ['Html', 'Form'];

	/**
	 * @param mixed $var Variable parameter
	 * @return void
	 */
	public function index($var = null)
	{
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero');

		// Set default lastVisit tsumego ID if not already set (for first-time visitors)
		if (!$this->Session->check('lastVisit'))
			$this->Session->write('lastVisit', Constants::$DEFAULT_TSUMEGO_ID);

		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Schedule');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('PublishDate');

		$tdates = [];

		$newTS = [];
		$uReward = $this->User->find('all', ['limit' => 5, 'order' => 'reward DESC']) ?: [];
		$urNames = [];
		foreach ($uReward as $user)
			$urNames[] = $this->checkPicture($user);

		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', ['conditions' => ['date' => $today]]);
		if (!$dateUser)
			$dateUser = $this->DayRecord->find('first', ['conditions' => ['date' => date('Y-m-d', strtotime('yesterday'))]]);

		$tsumegoDates = [];
		$popularTooltip = [];
		$popularTooltipInfo = [];
		$popularTooltipBoardSize = [];
		$setsWithPremium = [];
		$currentQuote = 'q13';
		$d1day = date('d. ');
		$d1year = date('Y');
		if ($d1day[0] == 0)
			$d1day = substr($d1day, -3);
		$month = date('F', strtotime(date('Y-m-d')));
		$d1 = $d1day . $month . ' ' . $d1year;
		$totd = null;
		$newT = null;

		$tsumegoFilters = new TsumegoFilters('published');
		$tsumegoButtonsOfPublishedTsumegos = new TsumegoButtons($tsumegoFilters);

		if ($dateUser)
		{
			$totd = $this->Tsumego->findById($dateUser['DayRecord']['tsumego']);
			$ptts = $this->Sgf->find('first', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $totd['Tsumego']['id']]]);
			if ($ptts)
			{
				$ptResult = SgfParser::process($ptts['Sgf']['sgf']);
				$popularTooltip = $ptResult->board;
				$popularTooltipInfo = $ptResult->info;
				$popularTooltipBoardSize = $ptResult->size;

				$newT = $this->Tsumego->findById($dateUser['DayRecord']['newTsumego']);

				if (!isset($totd['Tsumego']['status']))
					$totd['Tsumego']['status'] = 'N';
				if (!isset($newT['Tsumego']['status']))
					$newT['Tsumego']['status'] = 'N';

				$currentQuote = $dateUser['DayRecord']['quote'];
				$userOfTheDay = $this->User->find('first', ['conditions' => ['id' => $dateUser['DayRecord']['user_id']]]);
				if (!$userOfTheDay)
					$userOfTheDay = ['User' => ['id' => 0, 'name' => 'Guest']];

				$totdSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $totd['Tsumego']['id']]]);
				if ($totdSc)
				{
					$totdS = $this->Set->findById($totdSc['SetConnection']['set_id']);
					if ($totdS)
					{
						$totd['Tsumego']['set'] = $totdS['Set']['title'];
						$totd['Tsumego']['set2'] = $totdS['Set']['title2'];
						$totd['Tsumego']['set_id'] = $totdS['Set']['id'];
					}
				}
				$newTSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newT['Tsumego']['id']]]);
				if ($newTSc)
				{
					$newTS = $this->Set->findById($newTSc['SetConnection']['set_id']);
					if ($newTS)
					{
						$newT['Tsumego']['set'] = $newTS['Set']['title'];
						$newT['Tsumego']['set2'] = $newTS['Set']['title2'];
						$newT['Tsumego']['set_id'] = $newTS['Set']['id'];
					}
				}

				$this->set('userOfTheDay', $this->checkPictureLarge($userOfTheDay));
				$this->set('uotdbg', $dateUser['DayRecord']['userbg']);

				$pd = $this->PublishDate->find('all', ['order' => 'date ASC']) ?: [];
				foreach ($pd as $date)
					$tsumegoDates[] = $date['PublishDate']['date'];

				$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]) ?: [];
				foreach ($swp as $set)
					$setsWithPremium[] = $set['Set']['id'];
				$totd = $this->checkForLocked($totd, $setsWithPremium);
			}
		}

		$this->set('tsumegoButtonsOfPublishedTsumegos', $tsumegoButtonsOfPublishedTsumegos);
		$this->set('hasPremium', Auth::hasPremium());
		$this->set('tsumegos', $tsumegoDates);
		$this->set('quote', $currentQuote);
		$this->set('d1', $d1);
		$this->set('totd', $totd);
		$this->set('newT', $newT);
		$this->set('dateUser', $dateUser);
		$this->set('popularTooltip', $popularTooltip);
		$this->set('popularTooltipInfo', $popularTooltipInfo);
		$this->set('popularTooltipBoardSize', $popularTooltipBoardSize);
		$this->set('urNames', $urNames);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null)
	{
		$news = $this->Site->find('all');
		$this->set('news', $news[$id]);
	}

	/**
	 * @return void
	 */
	public function impressum()
	{
		$this->Session->write('page', 'about');
		$this->Session->write('title', 'Tsumego Hero - Legal Notice');
	}

	/**
	 * @return void
	 */
	public function websitefunctions()
	{
		$this->Session->write('page', 'websitefunctions');
		$this->Session->write('title', 'Tsumego Hero - Website Functions');
	}

	/**
	 * @return void
	 */
	public function gotutorial()
	{
		$this->Session->write('page', 'gotutorial');
		$this->Session->write('title', 'Tsumego Hero - Go Tutorial');
	}

	/**
	 * @return void
	 */
	public function privacypolicy()
	{
		$this->Session->write('page', 'privacypolicy');
		$this->Session->write('title', 'Tsumego Hero - Privacy Policy');
	}

}
