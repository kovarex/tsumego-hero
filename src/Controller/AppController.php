<?php

use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\BoardSelector;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\TimeMode;
use App\Utility\TsumegoFilters;
use App\Utility\Util;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class AppController extends Controller
{
	public $viewClass = 'App';

	public $helpers = ['Pagination'];

	public $components = [
		//'DebugKit.Toolbar',
		'PlayResultProcessor',
		'Authorization'
	];

	public static function _getAllTags(): array
	{
		return Util::query("SELECT * from tag WHERE approved = 1 ORDER BY tag.name");
	}

	//The pages of a deleted account (profile, its sets, tags, solve history...) are gone for everyone but the admins.
	/** @param UserRow $user */
	protected function assertUserVisible(array $user): void
	{
		if (!empty($user['deleted']) && !Auth::isAdmin())
			throw new NotFoundException('User not found');
	}

	public static function _saveDanSolveCondition(string $solvedTsumegoRank, int $tId): void
	{
		if (isset(AchievementCondition::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]))
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::DAN_SOLVE_CATEGORIES[$solvedTsumegoRank]);
	}

	public static function _updateSprintCondition(bool $trigger = false): void
	{
		if (!Auth::isLoggedIn())
			return;
		if ($trigger)
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_SPRINT);
		else
			AchievementCondition::put(Auth::getUserID(), AchievementCondition::CATEGORY_SPRINT, 0);
	}

	public static function _updateGoldenCondition(bool $trigger = false): void
	{
		if ($trigger)
			AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_GOLDEN);
		else
			AchievementCondition::put(Auth::getUserID(), AchievementCondition::CATEGORY_GOLDEN, 0);
	}

	public static function _updatePotionCondition(): void
	{
		AchievementCondition::increment(Auth::getUserID(), AchievementCondition::CATEGORY_POTION);
	}

	public static function _updateGems(string $rank): void
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
			$counterField = null; // Which counter to increment (emerald_solves, sapphire_solves, or ruby_solves)
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
					$counterField = 'emerald_solves';
					$achievementCategory = AchievementCondition::CATEGORY_EMERALD;
					if ($dateGem['DayRecord']['emerald_solves'] + 1 == $condition1)
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
					$counterField = 'sapphire_solves';
					$achievementCategory = AchievementCondition::CATEGORY_SAPPHIRE;
					if ($dateGem['DayRecord']['sapphire_solves'] + 1 == $condition2)
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
					$counterField = 'ruby_solves';
					$achievementCategory = AchievementCondition::CATEGORY_RUBY;
					if ($dateGem['DayRecord']['ruby_solves'] + 1 == $condition3)
						$conditionMet = true;
				}
			}

			// If we matched a gem rank, update the counter atomically
			if ($counterField !== null)
			{
				$increment = 1;
				if ($conditionMet)
				{
					if (AchievementCondition::value(Auth::getUserID(), $achievementCategory) === null)
						AchievementCondition::put(Auth::getUserID(), $achievementCategory, 1);
					else
						$increment = 0;
				}

				if ($increment > 0)
					ClassRegistry::init('DayRecord')->updateAll([$counterField => $counterField . ' + ' . $increment], ['date' => $today]);
			}
		}
	}

	//The mailer of a running test, and null outside of one.
	private static $testEmailer = null;

	public static function _setTestEmailer($emailer): void
	{
		self::$testEmailer = $emailer;
	}

	//The mailer of the emails a controller sends.
	//A test puts its own mailer here through _setTestEmailer(), so it reads what the site
	//wrote instead of receiving it. The test suite and the test host build the message and
	//stop there; the dev site hands it to the local mail catcher, like it always did.
	/** @return CakeEmail */
	public function _getEmailer()
	{
		return self::$testEmailer
			?? (Util::isOnTestSite() ? new CakeEmail(['transport' => 'Debug']) : new CakeEmail());
	}

	protected function signIn(array $user): void
	{
		Auth::init($user);
		$vs = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $user['User']['id']], 'order' => 'updated DESC']);
		if ($vs)
			Util::setCookie('lastVisit', $vs['TsumegoStatus']['tsumego_id']);
	}

	//Enforce HTTP-method attributes (e.g. #[HttpPost]) on the dispatched action.
	//Mirrors CakePHP 5's Cake\Http\Attribute\* so migration is a use-swap: the
	//attributes stay, this enforcement method is deleted.
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

		// HEAD asks for the headers of a GET response, so a page that answers GET answers it
		$method = $this->request->method();
		if ($method === 'HEAD' && in_array('GET', $allowed, true))
			$method = 'GET';

		if (!in_array($method, $allowed, true))
		{
			$e = new MethodNotAllowedException();
			$e->responseHeader('Allow', implode(', ', $allowed));
			throw $e;
		}
	}

	//Bumped when browsers hold files that no deploy can replace: the old static editor page (until
	//2026-10-23) was cached with the besogo scripts it loaded, so its users kept running the old editor.
	public const BROWSER_CACHE_VERSION = '2026-10-23';

	//Drops the site's HTTP cache in a browser once per BROWSER_CACHE_VERSION, on whatever request of
	//the site comes first. The cookie keeps it from happening again.
	private function resetStaleBrowserCache(): void
	{
		if (($_COOKIE['browser_cache_version'] ?? null) === self::BROWSER_CACHE_VERSION)
			return;
		$this->response->header('Clear-Site-Data', '"cache"');
		Util::setCookie('browser_cache_version', self::BROWSER_CACHE_VERSION);
	}

	public function beforeFilter(): void
	{
		$this->enforceHttpMethodAttribute();
		$this->resetStaleBrowserCache();

		// Boards are drawn without a session: a list of a thousand players would otherwise
		// pay for the login of a visitor who is not there.
		if (strtolower((string) ($this->request->params['controller'] ?? '')) === 'avatars')
			return;

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
		$levelBar = User::LEVEL_BAR_LEVEL;

		if (Auth::isLoggedIn())
		{
			if (!empty(Auth::getUser()['needs_display_name_change']))
			{
				$controller = $this->request->params['controller'];
				$action = $this->request->params['action'];
				// The links that arrive by e-mail and the ways out of the account stay
				// reachable: asking for a password reset or for deletion must not require
				// picking a name first.
				$allowedChangeActions = [
					'users/changename',
					'users/updatename',
					'users/logout',
					'users/login',
					'users/newpassword',
					'users/confirm_delete_account',
					'users/delete_account',
				];

				if (!in_array(strtolower($controller . '/' . $action), $allowedChangeActions, true))
				{
					$this->redirect(['controller' => 'users', 'action' => 'changename']);
					return;
				}
			}

			if ($lastTimeModeCategoryID = Util::clearCookie('lastTimeModeCategoryID'))
				Auth::saveUserField('last_time_mode_category_id', $lastTimeModeCategoryID);
			if (Auth::getUser()['highscore_page_id'] == Constants::$HIGHSCORE_LEVEL)
				$highscoreLink = 'highscore';
			elseif (Auth::getUser()['highscore_page_id'] == Constants::$HIGHSCORE_RATING)
				$highscoreLink = 'rating';
			elseif (Auth::getUser()['highscore_page_id'] == Constants::$HIGHSCORE_DAILY)
				$highscoreLink = 'leaderboard';
			elseif (Auth::getUser()['highscore_page_id'] == Constants::$HIGHSCORE_TIME_MODE)
				$highscoreLink = 'time_mode';

			if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0')
			{
				// The cookie says 'on' or 'off'
				Auth::saveUserField('sound', $_COOKIE['sound'] === 'on');
				unset($_COOKIE['sound']);
			}
			$this->set('user', Auth::getUser());
		}

		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0')
		{
			$lightDark = $_COOKIE['lightDark'];
			if (Auth::isLoggedIn())
				Auth::saveUserField('theme_id', $lightDark === 'dark' ? User::THEME_DARK : User::THEME_LIGHT);
		}
		elseif (Auth::isLoggedIn())
			$lightDark = (Auth::getUser()['theme_id'] == User::THEME_DARK) ? 'dark' : 'light';

		if (Auth::isLoggedIn())
		{
			new TsumegoFilters(); // keeps the collection filters the pages set in cookies
			if (isset($_COOKIE['levelBar']) && $_COOKIE['levelBar'] != '0')
			{
				$levelBar = ((int) $_COOKIE['levelBar']) === User::LEVEL_BAR_RATING ? User::LEVEL_BAR_RATING : User::LEVEL_BAR_LEVEL;
				Auth::saveUserField('level_bar_id', $levelBar);
			}
			else
				$levelBar = ((int) Auth::getUser()['level_bar_id']) === User::LEVEL_BAR_RATING ? User::LEVEL_BAR_RATING : User::LEVEL_BAR_LEVEL;
		}

		if (HeroPowers::getSprintRemainingSeconds() == 0)
			$this->_updateSprintCondition();

		if (Auth::isLoggedIn() && !$this->request->is('ajax'))
		{
			$achievementChecker = new AchievementChecker();
			$achievementChecker->checkStandardAchievements()->finalize();
			$this->set('achievementUpdates', $achievementChecker->updated);
		}
		if (!is_null($boardsBitmask = Util::clearCookie('boards_bitmask')))
		{
			if (Auth::isLoggedIn())
				Auth::saveUserField('boards_bitmask', BoardSelector::filterValidBits((int) $boardsBitmask));
		}
		else
			$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);

		$this->set('boardsBitmask', $boardsBitmask);

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn())
			$this->set('user', Auth::getUser());
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('highscoreLink', $highscoreLink);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('timeMode', $timeMode);
		$this->set('changelogTimestamps', ChangelogController::changelogTimestamps());
	}

	public function afterFilter(): void {}
}
