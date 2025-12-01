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

		$uReward = $this->User->find('all', ['limit' => 5, 'order' => 'reward DESC']) ?: [];
		$urNames = [];
		foreach ($uReward as $user)
			$urNames[] = $this->checkPicture($user);

		$today = date('Y-m-d');
		$dayRecord = $this->DayRecord->find('first', ['conditions' => ['date' => $today]]);
		if (!$dayRecord)
			$dayRecord = $this->DayRecord->find('first', ['conditions' => ['date' => date('Y-m-d', strtotime('yesterday'))]]);

		$currentQuote = 'q13';

		$tsumegoFilters = new TsumegoFilters('published');
		$tsumegoButtonsOfPublishedTsumegos = new TsumegoButtons($tsumegoFilters);

		if ($dayRecord)
		{
			$currentQuote = $dayRecord['DayRecord']['quote'];
			$userOfTheDay = $this->User->find('first', ['conditions' => ['id' => $dayRecord['DayRecord']['user_id']]]);
			if (!$userOfTheDay)
				$userOfTheDay = ['User' => ['id' => 0, 'name' => 'Guest']];
			$this->set('userOfTheDay', $this->checkPictureLarge($userOfTheDay));
		}

		$this->set('tsumegoButtonsOfPublishedTsumegos', $tsumegoButtonsOfPublishedTsumegos);
		$this->set('dayRecords', ClassRegistry::init('DayRecord')->find('all', ['order' => 'date ASC']));
		$this->set('quote', $currentQuote);
		$this->set('dayRecord', $dayRecord);
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
