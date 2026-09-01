<?php

App::uses('Auth', 'Utility');
App::uses('BoardSelector', 'Utility');
App::uses('TsumegoFilters', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('TimeMode', 'Utility');

class AppController extends Controller
{
	public $viewClass = 'App';

	public $helpers = ['Pagination'];

	public $components = [
		//'DebugKit.Toolbar',
		'PlayResultProcessor',
		'Authorization'
	];

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

	protected function checkPictureLarge($u)
	{
		if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null)
			return substr($u['User']['name'], 3);

		return $u['User']['name'];
	}
	public static function checkPicture($user)
	{
		if (substr($user['name'], 0, 3) == 'g__' && $user['external_id'] != null)
			return substr($user['name'], 3);

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

	public static function updatePotionCondition(): void
	{
		$potionCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'potion',
			],
		]);
		if (!$potionCondition)
		{
			$potionCondition = [];
			$potionCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->create();
		}
		$potionCondition['AchievementCondition']['category'] = 'potion';
		$potionCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		$potionCondition['AchievementCondition']['value']++;
		ClassRegistry::init('AchievementCondition')->save($potionCondition);
	}

	public static function updateGems(string $rank): void
	{
		$datex = new DateTime('today');
		$today = $datex->format('Y-m-d');
		$dateGem = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => $today]]);
		if ($dateGem != null)
		{
			$gems = explode('-', $dateGem['DayRecord']['gems']);
			$gemValue = '';
			$gemValue2 = '';
			$gemValue3 = '';
			$condition1 = 500;
			$condition2 = 200;
			$condition3 = 5;
			$counterField = null; // Which counter to increment (gemCounter1, gemCounter2, or gemCounter3)
			$achievementCategory = null;
			$conditionMet = false;

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
					$counterField = 'gemCounter1';
					$achievementCategory = 'emerald';
					if ($dateGem['DayRecord']['gemCounter1'] + 1 == $condition1)
						$conditionMet = true;
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
					$counterField = 'gemCounter2';
					$achievementCategory = 'sapphire';
					if ($dateGem['DayRecord']['gemCounter2'] + 1 == $condition2)
						$conditionMet = true;
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
					$counterField = 'gemCounter3';
					$achievementCategory = 'ruby';
					if ($dateGem['DayRecord']['gemCounter3'] + 1 == $condition3)
						$conditionMet = true;
				}
			}

			// If we matched a gem rank, update the counter atomically
			if ($counterField !== null)
			{
				$increment = 1;
				if ($conditionMet)
				{
					$userHasAchievement = ClassRegistry::init('AchievementCondition')->find('first', [
						'order' => 'value DESC',
						'conditions' => [
							'user_id' => Auth::getUserID(),
							'category' => $achievementCategory,
						],
					]);
					if ($userHasAchievement == null)
					{
						$aCondition = [];
						$aCondition['AchievementCondition']['category'] = $achievementCategory;
						$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
						$aCondition['AchievementCondition']['value'] = 1;
						ClassRegistry::init('AchievementCondition')->save($aCondition);
					}
					else
						$increment = 0;
				}

				if ($increment > 0)
					ClassRegistry::init('DayRecord')->updateAll([$counterField => $counterField . ' + ' . $increment], ['date' => $today]);
			}
		}
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
	}

	/**
	 * Enforce HTTP-method attributes (e.g. #[HttpPost]) on the dispatched action.
	 * Mirrors CakePHP 5's Cake\Http\Attribute\* so migration is a use-swap: the
	 * attributes stay, this enforcement method is deleted.
	 */
	protected function enforceHttpMethodAttribute(): void
	{
		$action = $this->action;
		if (!is_string($action) || !method_exists($this, $action))
			return;

		$allowed = [];
		$reflection = new ReflectionMethod($this, $action);
		foreach ($reflection->getAttributes() as $attribute)
		{
			$method = match ($attribute->getName())
			{
				'App\Attribute\HttpGet' => 'GET',
				'App\Attribute\HttpPost' => 'POST',
				'App\Attribute\HttpPut' => 'PUT',
				'App\Attribute\HttpPatch' => 'PATCH',
				'App\Attribute\HttpDelete' => 'DELETE',
				default => null,
			};
			if ($method)
				$allowed[] = $method;
		}
		if (!$allowed)
			return;

		if (!in_array($this->request->method(), $allowed, true))
		{
			$e = new MethodNotAllowedException();
			$e->responseHeader('Allow', implode(', ', $allowed));
			throw $e;
		}
	}

	public function beforeFilter(): void
	{
		$this->enforceHttpMethodAttribute();
		$this->loadModel('User');
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

		Auth::init();
		$timeMode = new TimeMode();

		$highscoreLink = 'highscore';
		$lightDark = 'light';
		$levelBar = 1;
		$lastProfileLeft = 1;
		$lastProfileRight = 2;

		if (Auth::isLoggedIn())
		{
			if ($lastTimeModeCategoryID = Util::clearCookie('lastTimeModeCategoryID'))
				Auth::saveUserField('last_time_mode_category_id', $lastTimeModeCategoryID);
			if (Auth::getUser()['lastHighscore'] == Constants::$HIGHSCORE_LEVEL)
				$highscoreLink = 'highscore';
			elseif (Auth::getUser()['lastHighscore'] == Constants::$HIGHSCORE_RATING)
				$highscoreLink = 'rating';
			elseif (Auth::getUser()['lastHighscore'] == Constants::$HIGHSCORE_DAILY)
				$highscoreLink = 'leaderboard';
			elseif (Auth::getUser()['lastHighscore'] == Constants::$HIGHSCORE_TIME_MODE)
				$highscoreLink = 'time_mode';

			if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0')
			{
				Auth::saveUserField('sound', $_COOKIE['sound']);
				unset($_COOKIE['sound']);
			}
			$this->set('user', Auth::getUser());
		}

		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0')
		{
			$lightDark = $_COOKIE['lightDark'];
			if (Auth::isLoggedIn())
			{
				// Convert string to integer for database storage
				$lightDarkInt = ($lightDark === 'light') ? 0 : 2;
				Auth::saveUserField('lastLight', $lightDarkInt);
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
				Auth::saveUserField('levelBar', $levelBar);
			}
			elseif (Auth::getUser()['levelBar'] == 0
		  || Auth::getUser()['levelBar'] == 'level')
		  	$levelBar = 1;
			else
				$levelBar = 2;

			if (isset($_COOKIE['lastProfileLeft']) && $_COOKIE['lastProfileLeft'] != '0')
			{
				$lastProfileLeft = $_COOKIE['lastProfileLeft'];
				Auth::saveUserField('lastProfileLeft', $lastProfileLeft);
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
				Auth::saveUserField('lastProfileRight', $lastProfileRight);
			}
			else
			{
				$lastProfileRight = Auth::getUser()['lastProfileRight'];
				if ($lastProfileRight == 0)
					$lastProfileRight = 1;
			}
		}

		if (HeroPowers::getSprintRemainingSeconds() == 0)
			$this->updateSprintCondition();

		if (Auth::isLoggedIn() && !$this->request->is('ajax'))
		{
			$achievementChecker = new AchievementChecker();
			$achievementChecker->checkStandardAchievements()->finalize();
			$this->set('achievementUpdates', $achievementChecker->updated);
		}
		if (!is_null($boardsBitmask = Util::clearCookie('boards_bitmask')))
		{
			if (Auth::isLoggedIn())
				Auth::saveUserField('boards_bitmask', BoardSelector::filterValidBits($boardsBitmask));
		}
		else
			$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);

		$this->set('boardsBitmask', $boardsBitmask);

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn())
		{
			$user = Auth::getUser();
			$displayUser = $user;
			$displayUser['name'] = $this->checkPicture($user);
			$this->set('user', $displayUser);
		}
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('highscoreLink', $highscoreLink);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('lastProfileLeft', $lastProfileLeft);
		$this->set('lastProfileRight', $lastProfileRight);
		$this->set('timeMode', $timeMode);
	}

	public function afterFilter() {}
}
