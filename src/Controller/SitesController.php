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
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero');

		// Set default lastVisit tsumego ID if not already set (for first-time visitors)
		if (empty($_COOKIE['lastVisit']))
			Util::setCookie('lastVisit', Constants::$DEFAULT_TSUMEGO_ID);

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
		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - Legal Notice');
	}

	/**
	 * @return void
	 */
	public function websitefunctions()
	{
		$this->set('_page', 'websitefunctions');
		$this->set('_title', 'Tsumego Hero - Website Functions');
	}

	/**
	 * @return void
	 */
	public function gotutorial()
	{
		$this->set('_page', 'gotutorial');
		$this->set('_title', 'Tsumego Hero - Go Tutorial');
	}

	/**
	 * @return void
	 */
	public function privacypolicy()
	{
		$this->set('_page', 'privacypolicy');
		$this->set('_title', 'Tsumego Hero - Privacy Policy');
	}

}
