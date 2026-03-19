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

		$currentQuote = 'q01';

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

	/**
	 * @return void
	 */
	public function about()
	{
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');

		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - About');

		$authors = $this->Tsumego->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				'NOT' => [
					'author' => ['Joschka Zimdars'],
				],
			],
		]);
		$set = $this->Set->find('all');
		$setMap = [];
		$setMap2 = [];
		$setCount = count($set);
		for ($i = 0; $i < $setCount; $i++)
		{
			$divider = ' ';
			$setMap[$set[$i]['Set']['id']] = $set[$i]['Set']['title'] . $divider . $set[$i]['Set']['title2'];
			$setMap2[$set[$i]['Set']['id']] = $set[$i]['Set']['public'];
		}

		$count = [];
		$count[0]['author'] = 'Innokentiy Zabirov';
		$count[0]['collections'] = 's: <a href="/sets/view/41">Life & Death - Intermediate</a> and <a href="/sets/view/122">Gokyo Shumyo 1-4</a>';
		$count[0]['count'] = 0;
		$count[1]['author'] = 'Alexandre Dinerchtein';
		$count[1]['collections'] = ': <a href="/sets/view/109">Problems from Professional Games</a>';
		$count[1]['count'] = 0;
		$count[2]['author'] = 'David Ulbricht';
		$count[2]['collections'] = ': <a href="/sets/view/41">Life & Death - Intermediate</a>';
		$count[2]['count'] = 0;
		$count[3]['author'] = 'Bradford Malbon';
		$count[3]['collections'] = 's: <a href="/sets/view/104">Easy Life</a> and <a href="/sets/view/105">Easy Kill</a>';
		$count[3]['count'] = 0;
		$count[4]['author'] = 'Ryan Smith';
		$count[4]['collections'] = 's: <a href="/sets/view/67">Korean Problem Academy 1-4</a>';
		$count[4]['count'] = 0;
		$count[5]['author'] = 'Fupfv';
		$count[5]['collections'] = ': <a href="/sets/view/139">Gokyo Shumyo 4</a>';
		$count[5]['count'] = 0;
		$count[6]['author'] = 'саша черных';
		$count[6]['collections'] = ': <a href="/sets/view/137">Tsumego Master</a>';
		$count[6]['count'] = 0;
		$count[7]['author'] = 'Timo Kreuzer';
		$count[7]['collections'] = ': <a href="/sets/view/137">Tsumego Master</a>';
		$count[7]['count'] = 0;
		$count[8]['author'] = 'David Mitchell';
		$count[8]['collections'] = ': <a href="/sets/view/143">Diabolical</a>';
		$count[8]['count'] = 10;
		$count[9]['author'] = 'Omicron';
		$count[9]['collections'] = ': <a href="/sets/view/145">Tesujis in Real Board Positions</a>';
		$count[9]['count'] = 0;
		$count[10]['author'] = 'Sadaharu';
		$count[10]['collections'] = ': <a href="/sets/view/146">Tsumego of Fortitude</a>, <a href="/sets/view/166">Secret Tsumego from Hong Dojo</a>, <a href="/sets/view/158">Beautiful Tsumego</a> and more.';
		$count[10]['count'] = 0;
		$count[11]['author'] = 'Jérôme Hubert';
		$count[11]['collections'] = ': <a href="/sets/view/150">Kanzufu</a> and more.';
		$count[11]['count'] = 0;
		$count[12]['author'] = 'Kaan Malçok';
		$count[12]['collections'] = ': <a href="/sets/view/163">Xuanxuan Qijing</a>';
		$count[12]['count'] = 0;

		$this->set('count', $count);
		$this->set('t', $authors);
	}

	/**
	 * Blank page — renders the default layout with no content.
	 * Used in tests as a lightweight alternative to the homepage.
	 *
	 * @return void
	 */
	public function blank()
	{
		$this->set('_page', 'blank');
		$this->set('_title', 'Tsumego Hero');
	}

}
