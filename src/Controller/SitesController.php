<?php

use App\Model\Entity\DayRecord;
use App\Model\Entity\Schedule;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\MistakeTraining;
use App\Utility\SiteGrowth;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Attribute\HttpGet;
use App\Utility\Util;

class SitesController extends AppController
{
	public $helpers = ['Html', 'Form'];

	#[HttpGet]
	public function index(): void
	{
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero');

		// Set default lastVisit tsumego ID if not already set (for first-time visitors)
		if (empty($_COOKIE['lastVisit']))
			Util::setCookie('lastVisit', Constants::$DEFAULT_TSUMEGO_ID);

		$this->loadModel('AchievementStatus');
		$this->loadModel('User');

		$today = date('Y-m-d');
		$dayRecordTable = $this->fetchTable('DayRecord');
		/** @var DayRecord|null $dayRecord */
		$dayRecord = $dayRecordTable->find()->where(['date' => $today])->first()
			?? $dayRecordTable->find()->where(['date' => date('Y-m-d', strtotime('yesterday'))])->first();

		$currentQuote = 'q01';

		/** @var Schedule|null $latestPublished */
		$latestPublished = $this->fetchTable('Schedule')->find()
			->where(['published' => 1, 'date <=' => date('Y-m-d')])
			->orderByDesc('date')
			->first();
		$latestPublishDate = $latestPublished?->date->format('Y-m-d');
		$tsumegoFilters = new TsumegoFilters('published', $latestPublishDate);
		$tsumegoButtonsOfPublishedTsumegos = new TsumegoButtons($tsumegoFilters);

		if ($dayRecord)
		{
			$currentQuote = $dayRecord->quote;
			$userOfTheDay = $this->User->find('first', ['conditions' => ['id' => $dayRecord->user_id]]);
			$this->set('userOfTheDay', $userOfTheDay ? $userOfTheDay['User'] : null);
		}
		else
			$this->set('userOfTheDay', null);

		$this->set('tsumegoButtonsOfPublishedTsumegos', $tsumegoButtonsOfPublishedTsumegos);
		$this->set('chartData', SiteGrowth::chartData());
		$this->set('latestPublishDate', $latestPublishDate);
		$this->set('quote', $currentQuote);

		$this->set('recentAchievements', $this->AchievementStatus->getRecent());

		// The way into the mistake training: what is due today. The offer to bring past
		// mistakes in is on the card always, without a count: counting them reads the
		// whole attempt history, far too much for a page this hot.
		if (Auth::isLoggedIn())
		{
			$userId = (int) Auth::getUserID();
			$this->set('mistakeTrainingDue', MistakeTraining::dueCount($userId));
		}
	}

	#[HttpGet]
	public function impressum(): void
	{
		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - Legal Notice');
	}

	#[HttpGet]
	public function websitefunctions(): void
	{
		$this->set('_page', 'websitefunctions');
		$this->set('_title', 'Tsumego Hero - Website Functions');
	}

	#[HttpGet]
	public function gotutorial(): void
	{
		$this->set('_page', 'gotutorial');
		$this->set('_title', 'Tsumego Hero - Go Tutorial');
	}

	#[HttpGet]
	public function privacypolicy(): void
	{
		$this->set('_page', 'privacypolicy');
		$this->set('_title', 'Tsumego Hero - Privacy Policy');
	}

	#[HttpGet]
	public function about(): void
	{
		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - About');
	}

	//Blank page — renders the default layout with no content.
	//Used in tests as a lightweight alternative to the homepage.
	#[HttpGet]
	public function blank(): void
	{
		$this->set('_page', 'blank');
		$this->set('_title', 'Tsumego Hero');
	}

}
