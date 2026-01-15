<?php

App::uses('Auth', 'Utility');
App::uses('BoardSelector', 'Utility');
App::uses('TsumegoFilters', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('TimeMode', 'Utility');

class AppController extends Controller
{
	public $viewClass = 'App';

	public $helpers = ['Pagination', 'AssetCompress.AssetCompress'];

	public $components = [
		//'DebugKit.Toolbar',
		'PlayResultProcessor'
	];

	protected function getDeletedSets()
	{
		$dSets = [];
		$de = $this->Set->find('all', ['conditions' => ['public' => -1]]);
		if (!$de)
			$de = [];
		foreach ($de as $item)
			$dSets[] = $item['Set']['id'];

		return $dSets;
	}

	public static function getStartpage(): string
	{
		$result = '';
		$latest = ClassRegistry::init('AchievementStatus')->find('all', ['limit' => 7, 'order' => 'created DESC']) ?: [];
		$latestCount = count($latest);
		for ($i = 0; $i < $latestCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($latest[$i]['AchievementStatus']['achievement_id']);
			$u = ClassRegistry::init('User')->findById($latest[$i]['AchievementStatus']['user_id']);
			if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null)
				$startPageUser = AppController::checkPicture($u);
			else
				$startPageUser = $u['User']['name'];
			$latest[$i]['AchievementStatus']['name'] = $a['Achievement']['name'];
			$latest[$i]['AchievementStatus']['color'] = $a['Achievement']['color'];
			$latest[$i]['AchievementStatus']['image'] = $a['Achievement']['image'];
			$latest[$i]['AchievementStatus']['user'] = $startPageUser;
			$result .= '<div class="quote1"><div class="quote1a"><a href="/achievements/view/' . $a['Achievement']['id'] . '"><img src="/img/' . $a['Achievement']['image'] . '.png" width="34px"></a></div>';
			$result .= '<div class="quote1b">Achievement gained by ' . $startPageUser . ':<br><div class=""><b>' . $a['Achievement']['name'] . '</b></div></div></div>';
		}
		return $result;
	}

	/**
	 * @param int $uid User ID
	 * @param string $action Action type
	 *
	 * @return void
	 */
	public static function handleContribution($uid, $action)
	{
		$uc = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null)
		{
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			ClassRegistry::init('UserContribution')->create();
		}
		$uc['UserContribution'][$action] += 1;
		$uc['UserContribution']['score']
		= $uc['UserContribution']['created_tag'] * 3
		+ $uc['UserContribution']['made_proposal'] * 5
		+ $uc['UserContribution']['reviewed'] * 2;
		ClassRegistry::init('UserContribution')->save($uc);
	}

	public static function getAllTags()
	{
		return Util::query("SELECT * from tag WHERE approved = 1 ORDER BY tag.name");
	}

	public static function encrypt($str = null)
	{
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		return base64_encode(openssl_encrypt($str, $encrypt_method, $key, 0, $iv));
	}

	protected function checkPictureLarge($u)
	{
		if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null)
			return '<img class="google-profile-image-large" src="/img/google/' . $u['User']['picture'] . '">' . substr($u['User']['name'], 3);

		return $u['User']['name'];
	}
	public static function checkPicture($user)
	{
		if (substr($user['name'], 0, 3) == 'g__' && $user['external_id'] != null)
			return '<img class="google-profile-image" src="/img/google/' . $user['picture'] . '">' . substr($user['name'], 3);

		return $user['name'];
	}

	public static function saveDanSolveCondition($solvedTsumegoRank, $tId): void
	{
		if ($solvedTsumegoRank == '1d' || $solvedTsumegoRank == '2d' || $solvedTsumegoRank == '3d' || $solvedTsumegoRank == '4d' || $solvedTsumegoRank == '5d')
		{
			$danSolveCategory = 'danSolve' . $solvedTsumegoRank;
			$danSolveCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => $danSolveCategory,
				],
			]);
			if (!$danSolveCondition)
			{
				$danSolveCondition = [];
				$danSolveCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$danSolveCondition['AchievementCondition']['category'] = $danSolveCategory;
			$danSolveCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			$danSolveCondition['AchievementCondition']['value']++;

			ClassRegistry::init('AchievementCondition')->save($danSolveCondition);
		}
	}

	public static function updateSprintCondition(bool $trigger = false): void
	{
		if (Auth::isLoggedIn())
		{
			$sprintCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'sprint',
				],
			]);
			if (!$sprintCondition)
			{
				$sprintCondition = [];
				$sprintCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$sprintCondition['AchievementCondition']['category'] = 'sprint';
			$sprintCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			if ($trigger)
				$sprintCondition['AchievementCondition']['value']++;
			else
				$sprintCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->save($sprintCondition);
		}
	}

	public static function updateGoldenCondition(bool $trigger = false): void
	{
		$goldenCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'golden',
			],
		]);
		if (!$goldenCondition)
		{
			$goldenCondition = [];
			$goldenCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->create();
		}
		$goldenCondition['AchievementCondition']['category'] = 'golden';
		$goldenCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		if ($trigger)
			$goldenCondition['AchievementCondition']['value']++;
		else
			$goldenCondition['AchievementCondition']['value'] = 0;
		ClassRegistry::init('AchievementCondition')->save($goldenCondition);
	}

	public static function setPotionCondition(): void
	{
		$potionCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'potion',
			],
		]);
		if (!$potionCondition)
		{
			$potionCondition = [];
			ClassRegistry::init('AchievementCondition')->create();
		}
		$potionCondition['AchievementCondition']['category'] = 'potion';
		$potionCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		$potionCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($potionCondition);
	}

	public static function updateGems(string $rank): void
	{
		$datex = new DateTime('today');
		$dateGem = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => $datex->format('Y-m-d')]]);
		if ($dateGem != null)
		{
			$gems = explode('-', $dateGem['DayRecord']['gems']);
			$gemValue = '';
			$gemValue2 = '';
			$gemValue3 = '';
			$condition1 = 500;
			$condition2 = 200;
			$condition3 = 5;
			$found1 = false;
			$found2 = false;
			$found3 = false;
			if ($rank == '15k' || $rank == '14k' || $rank == '13k' || $rank == '12k' || $rank == '11k' || $rank == '10k')
			{
				if ($gems[0] == 0)
					$gemValue = '15k';
				elseif ($gems[0] == 1)
					$gemValue = '12k';
				elseif ($gems[0] == 2)
					$gemValue = '10k';
				if ($rank == $gemValue)
				{
					$dateGem['DayRecord']['gemCounter1']++;
					if ($dateGem['DayRecord']['gemCounter1'] == $condition1)
						$found1 = true;
				}
			}
			elseif ($rank == '9k' || $rank == '8k' || $rank == '7k' || $rank == '6k' || $rank == '5k' || $rank == '4k' || $rank == '3k' || $rank == '2k' || $rank == '1k')
			{
				if ($gems[1] == 0)
				{
					$gemValue = '9k';
					$gemValue2 = 'x';
					$gemValue3 = 'y';
				}
				elseif ($gems[1] == 1)
				{
					$gemValue = '6k';
					$gemValue2 = '5k';
					$gemValue3 = '4k';
				}
				elseif ($gems[1] == 2)
				{
					$gemValue = 'x';
					$gemValue2 = '2k';
					$gemValue3 = '1k';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3)
				{
					$dateGem['DayRecord']['gemCounter2']++;
					if ($dateGem['DayRecord']['gemCounter2'] == $condition2)
						$found2 = true;
				}
			}
			elseif ($rank == '1d' || $rank == '2d' || $rank == '3d' || $rank == '4d' || $rank == '5d' || $rank == '6d' || $rank == '7d')
			{
				if ($gems[2] == 0)
				{
					$gemValue = '1d';
					$gemValue2 = '2d';
					$gemValue3 = '3d';
				}
				elseif ($gems[2] == 1)
				{
					$gemValue = '2d';
					$gemValue2 = '3d';
					$gemValue3 = '4d';
				}
				elseif ($gems[2] == 2)
				{
					$gemValue = '5d';
					$gemValue2 = '6d';
					$gemValue3 = '7d';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3)
				{
					$dateGem['DayRecord']['gemCounter3']++;
					if ($dateGem['DayRecord']['gemCounter3'] == $condition3)
						$found3 = true;
				}
			}
			if ($found1)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'emerald',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'emerald';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter1']--;
			}
			elseif ($found2)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'sapphire',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'sapphire';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter2']--;
			}
			elseif ($found3)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'ruby',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'ruby';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter3']--;
			}
		}
		ClassRegistry::init('DayRecord')->save($dateGem);
	}

	/**
	 * @param int $uid User ID
	 * @return void
	 */
	protected function handleSearchSettings($uid)
	{
		$this->loadModel('UserContribution');
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null)
		{
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			$this->UserContribution->create();
			$this->UserContribution->save($uc);
		}
		new TsumegoFilters();
	}

	protected function signIn(array $user): void
	{
		Auth::init($user);
		$vs = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $user['User']['id']], 'order' => 'updated DESC']);
		if ($vs)
			Util::setCookie('lastVisit', $vs['TsumegoStatus']['tsumego_id']);
		Util::setCookie('texture', $user['User']['texture']);
		Util::setCookie('check1', $user['User']['id']);
	}

	public function beforeFilter(): void
	{
		$this->loadModel('User');
		$this->loadModel('Activate');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TimeModeAttempt');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('AdminActivity');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('Favorite');

		Auth::init();
		$timeMode = new TimeMode();

		$highscoreLink = 'highscore';
		$lightDark = 'light';
		$resetCookies = false;
		$levelBar = 1;
		$lastProfileLeft = 1;
		$lastProfileRight = 2;
		$hasFavs = false;

		if (Auth::isLoggedIn())
		{
			if ($lastTimeModeCategoryID = Util::clearCookie('lastTimeModeCategoryID'))
				Auth::getUser()['last_time_mode_category_id'] = $lastTimeModeCategoryID;
			if (isset($_COOKIE['z_sess']) && $_COOKIE['z_sess'] != 0
			&& strlen($_COOKIE['z_sess']) > 5)
			{
				Auth::getUser()['_sessid'] = $_COOKIE['z_sess'];
				Auth::saveUser();
			}
			if (Auth::getUser()['lastHighscore'] == 1)
				$highscoreLink = 'highscore';
			elseif (Auth::getUser()['lastHighscore'] == 2)
				$highscoreLink = 'rating';
			elseif (Auth::getUser()['lastHighscore'] == 3)
				$highscoreLink = 'leaderboard';
			elseif (Auth::getUser()['lastHighscore'] == 4)
				$highscoreLink = 'highscore3';

			if (isset($_COOKIE['lastMode']) && $_COOKIE['lastMode'] != 0)
			{
				Auth::getUser()['lastMode'] = $_COOKIE['lastMode'];
				Auth::saveUser();
			}
			if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0')
			{
				Auth::getUser()['sound'] = $_COOKIE['sound'];
				Auth::saveUser();
				unset($_COOKIE['sound']);
			}
			$this->set('ac', true);
			$this->set('user', Auth::getUser());
		}

		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0')
		{
			$lightDark = $_COOKIE['lightDark'];
			if (Auth::isLoggedIn())
			{
				// Convert string to integer for database storage
				$lightDarkInt = ($lightDark === 'light') ? 0 : 2;
				Auth::getUser()['lastLight'] = $lightDarkInt;
			}
		}
		elseif (Auth::isLoggedIn())
			if (Auth::getUser()['lastLight'] == 0
			|| Auth::getUser()['lastLight'] == 1)
				$lightDark = 'light';
			else
				$lightDark = 'dark';

		if (Auth::isLoggedIn())
		{
			$this->handleSearchSettings(Auth::getUserID());
			if (isset($_COOKIE['levelBar']) && $_COOKIE['levelBar'] != '0')
			{
				$levelBar = $_COOKIE['levelBar'];
				Auth::getUser()['levelBar'] = $levelBar;
			}
			elseif (Auth::getUser()['levelBar'] == 0
		  || Auth::getUser()['levelBar'] == 'level')
		  	$levelBar = 1;
			else
				$levelBar = 2;

			if (isset($_COOKIE['lastProfileLeft']) && $_COOKIE['lastProfileLeft'] != '0')
			{
				$lastProfileLeft = $_COOKIE['lastProfileLeft'];
				Auth::getUser()['lastProfileLeft'] = $lastProfileLeft;
			}
			else
			{
				$lastProfileLeft = Auth::getUser()['lastProfileLeft'];
				if ($lastProfileLeft == 0)
					$lastProfileLeft = 1;
			}
			if (isset($_COOKIE['lastProfileRight']) && $_COOKIE['lastProfileRight'] != '0')
			{
				$lastProfileRight = $_COOKIE['lastProfileRight'];
				Auth::getUser()['lastProfileRight'] = $lastProfileRight;
			}
			else
			{
				$lastProfileRight = Auth::getUser()['lastProfileRight'];
				if ($lastProfileRight == 0)
					$lastProfileRight = 1;
			}
		}
		$mode = 1;
		if (isset($_COOKIE['mode']) && $_COOKIE['mode'] != '0')
			if ($_COOKIE['mode'] == 1)
				$mode = 1;
			else
				$mode = 2;

		if (Auth::isLoggedIn() && Auth::getUser()['mode'] == 2)
			$mode = 2;

		if ($_COOKIE['sprint'] != 1)
			$this->updateSprintCondition();

		if (Auth::isLoggedIn() && !$this->request->is('ajax'))
		{
			$this->PlayResultProcessor->checkPreviousPlay($timeMode);
			$achievementChecker = new AchievementChecker();
			$achievementChecker->checkLevelAchievements();
			$achievementChecker->checkProblemNumberAchievements();
			$achievementChecker->checkRatingAchievements();
			$achievementChecker->checkDanSolveAchievements();
			$achievementChecker->checkNoErrorAchievements();
			$achievementChecker->finalize();
			$this->set('achievementUpdates', $achievementChecker->updated);
		}
		$boardNames = [];

		if (!is_null($boardsBitmask = Util::clearCookie('boards_bitmask')))
		{
			if (Auth::isLoggedIn())
			{
				Auth::getUser()['boards_bitmask'] = BoardSelector::filterValidBits($boardsBitmask);
				Auth::saveUser();
			}
		}
		else
			$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);

		$this->set('boardsBitmask', $boardsBitmask);

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn())
		{
			Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());
			$this->set('user', Auth::getUser());
		}
		$this->set('mode', $mode);
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('boardNames', $boardNames);
		$this->set('highscoreLink', $highscoreLink);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('lastProfileLeft', $lastProfileLeft);
		$this->set('lastProfileRight', $lastProfileRight);
		$this->set('resetCookies', $resetCookies);
		$this->set('hasFavs', $hasFavs);
		$this->set('timeMode', $timeMode);
		if (Auth::isLoggedIn())
			Auth::saveUser();
	}

	public function afterFilter() {}
}
