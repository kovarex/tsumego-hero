<?php

use App\Utility\Auth;
use Cake\ORM\Locator\LocatorAwareTrait;
use DOM\HTMLDocument;
use Facebook\WebDriver\WebDriverBy;
use PHPUnitRetry\RetryTrait;

//A test that dispatches a real request and asserts what a client gets back.
//The verbs and the assertions come from Cake 5's `IntegrationTestTrait`. The fork ships it in
//the global namespace, Cake 5 keeps it in `Cake\TestSuite\`, so the trait line below is what
//moves at the migration; the tests themselves never name it. The body of the last response is
//kept in `$view`, which is where the assertions that read the page look.
/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class IntegrationTestCase extends CakeTestCase
{
	use IntegrationTestTrait {
		_sendRequest as private dispatchRequest;
		controllerSpy as private spyOnController;
	}

	use RetryTrait;

	//fetchTable() of the Cake 5 tables, as Cake 5's test cases have it
	use LocatorAwareTrait;

	//The body of the last response, the page as the client received it.
	protected string $view = '';

	//The body the cached DOM was parsed from, so a changed body is parsed again.
	private string $domSource = '';

	private ?HTMLDocument $dom = null;

	//The cookie jar a browser keeps after the response: what the app set during the request,
	//plus what the request came with.
	//The trait restores $_COOKIE once the request is over, so the moment to read the cookies
	//the app wrote is the end of the action.
	private ?array $responseCookies = null;

	//Whether the files of the request came from a test's $_FILES, so they are not reused.
	private bool $uploadsHandedOver = false;

	public function setUp(): void
	{
		parent::setUp();

		// A mailer a previous test replaced stays behind in the static hook.
		AppController::_setTestEmailer(null);
	}

	protected function _sendRequest(array|string $url, string $method, array|string $data = []): void
	{
		// A test and the fixture hand their cookies over in $_COOKIE, but the trait builds
		// $_COOKIE from scratch for every request, so what the test set has to be carried in.
		// A cookie the test wrote by hand wins over one it set earlier with cookie().
		$this->_cookie = array_merge($this->_cookie, $_COOKIE);

		// The same goes for an upload a test puts into $_FILES by hand, which is what an
		// upload form sends. The next request carries only what $_FILES holds at that moment.
		if ($_FILES)
		{
			$this->_request['files'] = $_FILES;
			$this->uploadsHandedOver = true;
		}
		elseif ($this->uploadsHandedOver)
			unset($this->_request['files']);

		// A request that a real client makes starts on a fresh registry. Without this, a model a
		// fixture saved keeps its id and the next save() updates that row instead of inserting.
		ClassRegistry::flush();

		$this->responseCookies = null;
		$this->dispatchRequest($url, $method, $data);
		$this->view = $this->_getBodyAsString();
	}

	//The cookie jar a browser keeps after the response: what the app set during the request,
	//plus what the request came with.
	//The trait restores $_COOKIE once the request is over, so the moment to read the cookies
	//the app wrote is the end of the action.
	public function controllerSpy(Controller $controller): void
	{
		$this->spyOnController($controller);

		$capture = function (): void {
			$this->responseCookies = $_COOKIE;
		};
		$events = $controller->getEventManager();
		$events->attach($capture, 'Controller.beforeRender', ['priority' => 100]);
		$events->attach($capture, 'Controller.beforeRedirect', ['priority' => 100]);
	}

	//The cookies a browser would send along with the next request.
	public function browserCookies(): array
	{
		return $this->responseCookies ?? [];
	}

	//The fixture user has no name, so a null name signs in the one without a name.
	public function login(?string $username): void
	{
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $username]]);
		$this->assertNotNull($user);
		$_COOKIE['hackedLoggedInUserID'] = $user['User']['id'];
		$this->cookie('hackedLoggedInUserID', (string) $user['User']['id']);
		// The test also reads Auth for itself, for instance to hand a browser the session.
		Auth::init();
	}

	public function logout(): void
	{
		unset($_COOKIE['hackedLoggedInUserID']);
		$this->cookie('hackedLoggedInUserID', '');
		Auth::logout();
	}

	public function setLoggedIn(bool $loggedIn): void
	{
		if ($loggedIn)
			$this->login('kovarex');
		else
			$this->logout();
	}

	//The emails the app sends in this test end up in TestEmailer::$lastEmail.
	public function registerTestEmailer(): void
	{
		AppController::_setTestEmailer(new TestEmailer());
	}

	//The value of a response header, or null when the response does not carry it.
	protected function headerValue(string $name): ?string
	{
		return $this->_response->header()[$name] ?? null;
	}

	public function getStringDom(): HTMLDocument
	{
		if ($this->domSource !== $this->view)
		{
			$this->dom = HTMLDocument::createFromString($this->view, LIBXML_HTML_NOIMPLIED);
			$this->domSource = $this->view;
		}

		return $this->dom;
	}

	//The navigation buttons under a problem: their order, and the status each one carries.
	//The cases that read a page through Selenium use these, whether the page was opened by the
	//test or by a browser that plays the problem.
	protected function checkNavigationButtonsBeforeAndAfterSolving($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void
	{
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, $currentStatus);
		$browser->playWithResult('S'); // mark the problem solved
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, 'S');
	}

	protected function checkPlayNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void
	{
		$navigationButtons = $browser->getElements('div.problem-nav__inner li');

		$this->assertCount($count, $navigationButtons);
		foreach ($navigationButtons as $key => $button)
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key), $indexFunction($currentIndex), $currentStatus);
	}

	protected function checkNavigationButton($button, $context, int $index, int $order, ?int $currentIndex = null, ?string $currentStatus = null): void
	{
		$this->assertSame($button->getText(), strval($order));
		if (is_null($currentIndex) || $index != $currentIndex)
		{
			$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[$index]['id']]]);
			$statusValue = $status ? $status['TsumegoStatus']['status'] : 'N';
		}
		else
			$statusValue = $currentStatus;

		$this->assertStringContainsString('problem-nav__item--' . $statusValue, $button->getAttribute('class'));
		$link = $button->findElement(WebDriverBy::tagName('a'));
		$this->assertTextStartsWith('/' . $context->tsumegos[$index]['set-connections'][0]['id'], $link->getAttribute('href'));
	}
}
