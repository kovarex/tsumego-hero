<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Controller\Controller;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\Event\EventInterface;
use ReflectionMethod;
use App\Model\Table\UserTable;
use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\BoardSelector;
use App\Utility\Changelog;
use App\Utility\Constants;
use App\Utility\Mailer;
use App\Utility\Play;
use App\Utility\Preferences;
use App\Utility\TimeMode;
use App\Utility\TsumegoFilters;
use App\Utility\Util;

/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @property \App\Controller\Component\AuthorizationComponent $Authorization
 * @property \App\Controller\Component\PlayResultProcessorComponent $PlayResultProcessor
 */
class AppController extends Controller
{
	//What $viewClass, $helpers and $components of Cake 2 set up.
	public function initialize(): void
	{
		parent::initialize();
		$this->viewBuilder()->setClassName('App');
		$this->viewBuilder()->addHelpers(['Html', 'Form', 'Pagination']);
		$this->loadComponent('PlayResultProcessor');
		$this->loadComponent('Authorization');
	}

	//Shows the problem of the set connection on the play page. $viewVars are what only the route has
	//(its canonical address, its menu item, its links), they win over what the problem would show.
	//$markAsVisited: the problem gets the status visited when the player has none on it yet.
	/**
	 * @param array<string, mixed> $viewVars
	 * @param array<string, mixed>|null $classroomAssignment With its collections, see ClassroomAssignmentTable::withCollections()
	 */
	protected function renderPlay(int $setConnectionID, array $viewVars = [], ?array $classroomAssignment = null, bool $markAsVisited = false): void
	{
		$play = new Play($setConnectionID, $this->request, $classroomAssignment, $markAsVisited);
		$play->addRouteValues($viewVars);
		$this->set($play->viewVars());
		$this->set('playPage', $play->clientData((string) $this->viewBuilder()->getVar('lightDark')));
		$this->render('/Tsumegos/play');
	}

	//The actions read the request through these few methods. A dotted name reads a nested field ('Set.title').

	//A field the form or the request body sent, null when it was not sent.
	protected function input(string $name): mixed
	{
		return $this->request->getData($name);
	}

	//Every field the form sent.
	/** @return array<string, mixed> */
	protected function inputs(): array
	{
		return (array) $this->request->getData();
	}

	//A value of the query string (?name=value), null when it is not there.
	protected function query(string $name): mixed
	{
		return $this->request->getQuery($name);
	}

	//The whole query string as an array.
	/** @return array<string, mixed> */
	protected function queries(): array
	{
		return $this->request->getQueryParams();
	}

	//A part of the address the router recognised: controller, action, or a named route element.
	protected function routeParam(string $name): mixed
	{
		return $this->request->getParam($name);
	}

	//The request body as it was sent, for the endpoints that take JSON.
	protected function requestBody(): string
	{
		return (string) $this->request->getBody();
	}

	//A file the form uploaded, as its entry of $_FILES (name, type, tmp_name, error, size), null
	//when none came. The one place a controller reads $_FILES.
	/** @return array<string, mixed>|null */
	protected function uploadedFile(string $name): ?array
	{
		return $_FILES[$name] ?? null;
	}

	//Hands a new version of the problem's SGF to the page that sets it up in the browser
	//(Tsumegos/setup_new_sgf), for an upload of a file or a save of the board editor
	protected function renderNewSgfSetup(int $setConnectionID, string $sgf, string $description, ?string $sourceSgfID = null, ?string $transformationFromSource = null): void
	{
		$this->set('sgf', $sgf);
		$this->set('setConnectionID', (string) $setConnectionID);
		// sent by the board editor, see SgfCommentReorientation
		$this->set('sourceSgfID', $sourceSgfID);
		$this->set('transformationFromSource', $transformationFromSource);
		$this->set('description', $description);
		$this->render('/Tsumegos/setup_new_sgf');
	}

	//GET, POST and so on.
	protected function httpMethod(): string
	{
		return $this->request->getMethod();
	}

	//The size the client says the request body has, in bytes.
	protected function contentLength(): int
	{
		return (int) env('CONTENT_LENGTH');
	}

	//The actions set the response through these few methods, which keep the immutable response
	//of the controller up to date. An action returns what they return.

	//Answers with the data as JSON in place of a rendered page.
	protected function respondJson(mixed $data, int $status = 200): Response
	{
		return $this->respond((string) json_encode($data), 'json', $status);
	}

	//Answers with the body as it is in place of a rendered page. The type is a short name
	//('xml', 'png', 'svg', 'webp') or a full mime type; without one it stays text/html.
	protected function respond(string $body, ?string $type = null, int $status = 200): Response
	{
		$this->autoRender = false;
		$response = $this->response;
		if ($type !== null)
			$response = $response->withType($type);
		return $this->response = $response->withStatus($status)->withStringBody($body);
	}

	protected function setResponseHeader(string $name, string $value): void
	{
		$this->response = $this->response->withHeader($name, $value);
	}

	//The browser asks again with this tag and gets a 304 while the answer stays the same.
	protected function setEtag(string $etag): void
	{
		$this->response = $this->response->withEtag($etag);
	}

	//The browser and proxies may keep the answer until $until (a strtotime() expression).
	protected function cacheUntil(string $lastModified, string $until): void
	{
		$this->response = $this->response->withCache($lastModified, $until);
	}

	//The pages of a deleted account (profile, its sets, tags, solve history...) are gone for everyone but the admins.
	/** @param UserRow $user */
	protected function assertUserVisible(array $user): void
	{
		if (!empty($user['deleted']) && !Auth::isAdmin())
			throw new NotFoundException('User not found');
	}

	//The mailer of a running test, and null outside of one.
	private static ?Mailer $testEmailer = null;

	public static function _setTestEmailer(?Mailer $emailer): void
	{
		self::$testEmailer = $emailer;
	}

	//The mailer of the emails a controller sends.
	//A test puts its own mailer here through _setTestEmailer(), so it reads what the site
	//wrote instead of receiving it. The test suite and the test host build the message and
	//stop there; the dev site hands it to the local mail catcher, like it always did.
	public function _getEmailer(): Mailer
	{
		return self::$testEmailer ?? new Mailer(Util::isOnTestSite());
	}

	/** @param UserRow $user */
	protected function signIn(array $user): void
	{
		Auth::init($user);
		$lastStatus = $this->fetchTable('TsumegoStatus')->find()
			->select(['tsumego_id'])
			->where(['user_id' => $user['id']])
			->orderByDesc('updated')
			->first();
		if ($lastStatus)
			Util::setCookie('lastVisit', $lastStatus->tsumego_id);
	}

	//Answers 405 to a request whose method the action's attributes (App\Attribute\HttpGet,
	//HttpPost...) don't allow. CakePHP has no such attributes of its own.
	protected function enforceHttpMethodAttribute(): void
	{
		$action = $this->request->getParam('action');
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
		$method = $this->httpMethod();
		if ($method === 'HEAD' && in_array('GET', $allowed, true))
			$method = 'GET';

		if (!in_array($method, $allowed, true))
		{
			$exception = new MethodNotAllowedException();
			$exception->setHeader('Allow', implode(', ', $allowed));
			throw $exception;
		}
	}

	//Bumped when browsers hold files that no deploy can replace: the old static editor page (until
	//2026-10-23) was cached with the besogo scripts it loaded, so its users kept running the old editor.
	public const BROWSER_CACHE_VERSION = '2026-10-23';

	//Drops the site's HTTP cache in a browser once per BROWSER_CACHE_VERSION, on whatever request of
	//the site comes first. The cookie keeps it from happening again.
	private function resetStaleBrowserCache(): void
	{
		if (Util::cookie('browser_cache_version') === self::BROWSER_CACHE_VERSION)
			return;
		$this->setResponseHeader('Clear-Site-Data', '"cache"');
		Util::setCookie('browser_cache_version', self::BROWSER_CACHE_VERSION);
	}

	//The pages a player who has to pick a new display name can still open: the links that arrive
	//by e-mail and the ways out of the account must not require picking a name first (AccountController).
	private const ACTIONS_ALLOWED_BEFORE_NAME_CHANGE = [
		'account/changename',
		'account/updatename',
		'account/logout',
		'account/login',
		'account/newpassword',
		'account/confirm_delete_account',
		'account/delete_account',
	];

	private function mustChangeDisplayNameFirst(): bool
	{
		if (empty(Auth::getUser()['needs_display_name_change']))
			return false;
		$page = strtolower($this->routeParam('controller') . '/' . $this->routeParam('action'));
		return !in_array($page, self::ACTIONS_ALLOWED_BEFORE_NAME_CHANGE, true);
	}

	//The pages put the settings a signed in player changes in cookies, which carry the change to the
	//account on the next request and are dropped then: the account is what counts, so a browser
	//with an older cookie does not set the setting back. Only what differs is written, in one update.
	private function saveSettingsFromCookies(): void
	{
		$settings = [];
		if ($lastTimeModeCategoryID = self::takeCookie('lastTimeModeCategoryID'))
			$settings['last_time_mode_category_id'] = (int) $lastTimeModeCategoryID;
		if (($sound = self::takeCookie('sound')) !== null && $sound != '0')
			$settings['sound'] = $sound === 'on';
		if (($lightDark = self::takeCookie('lightDark')) !== null && $lightDark != '0')
			$settings['theme_id'] = $lightDark === 'dark' ? UserTable::THEME_DARK : UserTable::THEME_LIGHT;
		if (($levelBar = self::takeCookie('levelBar')) !== null && $levelBar != '0')
			$settings['level_bar_id'] = self::levelBar((int) $levelBar);
		if (!is_null($boardsBitmask = self::takeCookie('boards_bitmask')))
			$settings['boards_bitmask'] = BoardSelector::filterValidBits((int) $boardsBitmask);
		$user = Auth::getUser();
		$changed = array_filter($settings, fn(int|bool $value, string $field) => !isset($user[$field]) || (int) $user[$field] !== (int) $value, ARRAY_FILTER_USE_BOTH);
		if ($changed)
			Auth::saveUserFields($changed);
		//keeps the collection filters the pages set in cookies; without one, the stored ones stay as they are
		if (Preferences::anyCookie())
			new TsumegoFilters();
	}

	//The value of a cookie the browser sent, which is dropped; null when it sent none
	private static function takeCookie(string $name): ?string
	{
		return Util::cookie($name) === null ? null : Util::clearCookie($name);
	}

	private static function levelBar(int $levelBar): int
	{
		return $levelBar === UserTable::LEVEL_BAR_RATING ? UserTable::LEVEL_BAR_RATING : UserTable::LEVEL_BAR_LEVEL;
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

	public function beforeFilter(EventInterface $event): void
	{
		//Cake 5 takes the answer of a filter from its event
		$response = $this->filterRequest();
		if ($response !== null)
		{
			$event->setResult($response);
			$event->stopPropagation();
		}
	}

	private function filterRequest(): ?Response
	{
		$this->enforceHttpMethodAttribute();
		$this->resetStaleBrowserCache();

		//Boards are drawn without a session: a list of a thousand players would otherwise
		//pay for the login of a visitor who is not there.
		if ($this instanceof AvatarsController)
			return null;

		Auth::init();
		if (Auth::isLoggedIn())
		{
			if ($this->mustChangeDisplayNameFirst())
				return $this->redirect(['controller' => 'users', 'action' => 'changename']);
			$this->saveSettingsFromCookies();
			if (!$this->request->is('ajax'))
				$this->set('achievementUpdates', new AchievementChecker()->checkStandardAchievements()->finalize()->updated);
			$this->set('user', Auth::getUser());
			$lightDark = Auth::getUser()['theme_id'] == UserTable::THEME_DARK ? 'dark' : 'light';
			$levelBar = self::levelBar((int) Auth::getUser()['level_bar_id']);
		}
		else
		{
			$lightDark = Util::cookie('lightDark') === 'dark' ? 'dark' : 'light';
			$levelBar = UserTable::LEVEL_BAR_LEVEL;
		}

		$this->set('highscoreLink', self::highscoreLink());
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('boardsBitmask', BoardSelector::playerBitmask());
		$this->set('timeMode', new TimeMode());
		return null;
	}

	//Only a rendered page shows what is new, an answer of an endpoint (respond()) is no page
	public function beforeRender(EventInterface $event): void
	{
		parent::beforeRender($event);
		$this->set('changelogTimestamps', Changelog::timestamps());
	}
}
