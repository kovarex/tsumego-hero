<?php

use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\BoardSelector;
use App\Utility\Constants;
use App\Utility\TimeMode;
use App\Utility\TsumegoFilters;
use App\Utility\Util;
use Cake\ORM\Locator\LocatorAwareTrait;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class AppController extends Controller
{
	//fetchTable() of the Cake 5 tables, as Cake 5's controllers have it. Protected, as Cake 2
	//makes every public method an address (see ActionSurfaceTest).
	use LocatorAwareTrait {
		fetchTable as protected;
		getTableLocator as protected;
		setTableLocator as protected;
	}

	public $viewClass = 'App';

	public $helpers = ['Pagination'];

	public $components = [
		//'DebugKit.Toolbar',
		'PlayResultProcessor',
		'Authorization'
	];

	//The pages of a deleted account (profile, its sets, tags, solve history...) are gone for everyone but the admins.
	/** @param UserRow $user */
	protected function assertUserVisible(array $user): void
	{
		if (!empty($user['deleted']) && !Auth::isAdmin())
			throw new NotFoundException('User not found');
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

	//Models every controller can use as $this->Name, loaded on the first use instead of on every request
	private const SHARED_MODELS = ['User', 'Tsumego', 'Set', 'TimeModeAttempt', 'TsumegoStatus', 'TsumegoAttempt',
		'Achievement', 'AchievementStatus', 'AchievementCondition', 'SetConnection', 'Tag'];

	public function __isset($name)
	{
		if (in_array($name, self::SHARED_MODELS, true))
			return $this->loadModel($name);
		return parent::__isset($name);
	}

	//The pages a player who has to pick a new display name can still open: the links that arrive
	//by e-mail and the ways out of the account must not require picking a name first.
	private const ACTIONS_ALLOWED_BEFORE_NAME_CHANGE = [
		'users/changename',
		'users/updatename',
		'users/logout',
		'users/login',
		'users/newpassword',
		'users/confirm_delete_account',
		'users/delete_account',
	];

	private function mustChangeDisplayNameFirst(): bool
	{
		if (empty(Auth::getUser()['needs_display_name_change']))
			return false;
		$page = strtolower($this->request->params['controller'] . '/' . $this->request->params['action']);
		return !in_array($page, self::ACTIONS_ALLOWED_BEFORE_NAME_CHANGE, true);
	}

	//The pages change some settings in cookies, the player keeps them from the next request on
	private function saveSettingsFromCookies(): void
	{
		if ($lastTimeModeCategoryID = Util::clearCookie('lastTimeModeCategoryID'))
			Auth::saveUserField('last_time_mode_category_id', $lastTimeModeCategoryID);
		if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0')
		{
			Auth::saveUserField('sound', $_COOKIE['sound'] === 'on');
			unset($_COOKIE['sound']);
		}
		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0')
			Auth::saveUserField('theme_id', $_COOKIE['lightDark'] === 'dark' ? User::THEME_DARK : User::THEME_LIGHT);
		if (isset($_COOKIE['levelBar']) && $_COOKIE['levelBar'] != '0')
			Auth::saveUserField('level_bar_id', self::levelBar((int) $_COOKIE['levelBar']));
		if (!is_null($boardsBitmask = Util::clearCookie('boards_bitmask')))
			Auth::saveUserField('boards_bitmask', BoardSelector::filterValidBits((int) $boardsBitmask));
		new TsumegoFilters(); //keeps the collection filters the pages set in cookies
	}

	private static function levelBar(int $levelBar): int
	{
		return $levelBar === User::LEVEL_BAR_RATING ? User::LEVEL_BAR_RATING : User::LEVEL_BAR_LEVEL;
	}

	//The page the Highscore menu item opens, the one the player picked last
	private static function highscoreLink(): string
	{
		return match (Auth::isLoggedIn() ? (int) Auth::getUser()['highscore_page_id'] : null)
		{
			Constants::$HIGHSCORE_RATING => 'rating',
			Constants::$HIGHSCORE_DAILY => 'leaderboard',
			Constants::$HIGHSCORE_TIME_MODE => 'time_mode',
			default => 'highscore',
		};
	}

	public function beforeFilter(): void
	{
		$this->enforceHttpMethodAttribute();
		$this->resetStaleBrowserCache();

		//Boards are drawn without a session: a list of a thousand players would otherwise
		//pay for the login of a visitor who is not there.
		if (strtolower((string) ($this->request->params['controller'] ?? '')) === 'avatars')
			return;

		Auth::init();
		if (Auth::isLoggedIn())
		{
			if ($this->mustChangeDisplayNameFirst())
			{
				$this->redirect(['controller' => 'users', 'action' => 'changename']);
				return;
			}
			$this->saveSettingsFromCookies();
			if (!$this->request->is('ajax'))
				$this->set('achievementUpdates', new AchievementChecker()->checkStandardAchievements()->finalize()->updated);
			$this->set('user', Auth::getUser());
			$lightDark = Auth::getUser()['theme_id'] == User::THEME_DARK ? 'dark' : 'light';
			$levelBar = self::levelBar((int) Auth::getUser()['level_bar_id']);
			$boardsBitmask = BoardSelector::filterValidBits(Auth::getUser()['boards_bitmask']);
		}
		else
		{
			$lightDark = ($_COOKIE['lightDark'] ?? null) === 'dark' ? 'dark' : 'light';
			$levelBar = User::LEVEL_BAR_LEVEL;
			$boardsBitmask = BoardSelector::filterValidBits((int) (Util::clearCookie('boards_bitmask') ?? BoardSelector::$DEFAULT_BOARDS_BITMASK));
		}

		$this->set('highscoreLink', self::highscoreLink());
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('boardsBitmask', $boardsBitmask);
		$this->set('timeMode', new TimeMode());
		$this->set('changelogTimestamps', ChangelogController::changelogTimestamps());
	}
}
