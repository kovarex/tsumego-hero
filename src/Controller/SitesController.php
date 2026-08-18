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
		$this->loadModel('AchievementStatus');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Schedule');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('PublishDate');

		$today = date('Y-m-d');
		$dayRecord = $this->DayRecord->find('first', ['conditions' => ['date' => $today]]);
		if (!$dayRecord)
			$dayRecord = $this->DayRecord->find('first', ['conditions' => ['date' => date('Y-m-d', strtotime('yesterday'))]]);

		$currentQuote = 'q01';

		$latestPublishDate = ClassRegistry::init('Schedule')->field('date', ['published' => 1, 'date <=' => date('Y-m-d')], 'date DESC') ?: null;
		$tsumegoFilters = new TsumegoFilters('published', false, $latestPublishDate);
		$tsumegoButtonsOfPublishedTsumegos = new TsumegoButtons($tsumegoFilters);

		if ($dayRecord)
		{
			$currentQuote = $dayRecord['DayRecord']['quote'];
			$userOfTheDay = $this->User->find('first', ['conditions' => ['id' => $dayRecord['DayRecord']['user_id']]]);
			$this->set('userOfTheDay', $this->checkPictureLarge($userOfTheDay));
			$this->set('userOfTheDayId', $userOfTheDay['User']['id']);
		}

		$this->set('tsumegoButtonsOfPublishedTsumegos', $tsumegoButtonsOfPublishedTsumegos);
		$chartData = Cache::read('homepage_chart', 'long');
		if ($chartData === false)
		{
			$chartData = $this->buildChartData();
			Cache::write('homepage_chart', $chartData, 'long');
		}
		$this->set('chartData', $chartData);
		$this->set('latestPublishDate', $latestPublishDate);
		$this->set('quote', $currentQuote);
		$this->set('dayRecord', $dayRecord);

		$recentAchievements = $this->AchievementStatus->getRecent();
		$this->set('recentAchievements', $recentAchievements);
	}

	private function buildChartData(): array
	{
		$problemsRaw = $this->Tsumego->query(
			"SELECT DATE_FORMAT(created, '%Y-%m-01') AS date, COUNT(*) AS cnt
			 FROM tsumego GROUP BY 1 ORDER BY 1 ASC"
		);
		$usersRaw = $this->User->query(
			"SELECT DATE_FORMAT(created, '%Y-%m-01') AS date, COUNT(*) AS cnt
			 FROM user WHERE created IS NOT NULL GROUP BY 1 ORDER BY 1 ASC"
		);

		// Build maps of cumulative values by month
		$cumProblems = 0;
		$problemsByMonth = [];
		foreach ($problemsRaw as $r)
		{
			$cumProblems += (int) $r[0]['cnt'];
			$problemsByMonth[(string) $r[0]['date']] = $cumProblems;
		}
		$cumUsers = 0;
		$usersByMonth = [];
		foreach ($usersRaw as $r)
		{
			$cumUsers += (int) $r[0]['cnt'];
			$usersByMonth[(string) $r[0]['date']] = $cumUsers;
		}

		// Collect all unique months
		$allMonths = array_unique(array_merge(array_keys($problemsByMonth), array_keys($usersByMonth)));
		sort($allMonths);

		// Build unified chart data, carrying forward last known values
		$lastProblems = null;
		$lastUsers = null;
		$chartData = [];
		foreach ($allMonths as $date)
		{
			if (array_key_exists($date, $problemsByMonth))
				$lastProblems = $problemsByMonth[$date];
			if (array_key_exists($date, $usersByMonth))
				$lastUsers = $usersByMonth[$date];
			$chartData[] = [
				'date' => $date,
				'problems' => $lastProblems,
				'users' => $lastUsers,
			];
		}

		return $chartData;
	}

	/**
	 * Returns recent achievements for AJAX polling.
	 *
	 * @return void
	 */
	public function recentAchievements()
	{
		$this->autoRender = false;
		$this->loadModel('AchievementStatus');
		$this->response->type('json');
		$recentAchievements = $this->AchievementStatus->getRecent();
		$this->response->body(json_encode(['recentAchievements' => $recentAchievements]));
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
		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - About');
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
