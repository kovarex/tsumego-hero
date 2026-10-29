<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Interactions\WebDriverActions;
use App\Utility\Auth;
use App\Utility\Util;

//Selenium wrapper used by every browser test.
//Two families of lookups, pick by whether the page can still change:
//  find() / findById()         wait for the element, use these by default
//  getElements() / idExists()  look at the page as it is right now, no waiting
//When a wait gives up it writes the page to tmp/browser-timeout-*.html, so a
//timeout in CI comes with the HTML that caused it.
class Browser
{
	// Counter for per-page JS coverage snapshots written to /tmp/coverage/js-raw-*.json
	private static int $jsCoverageCounter = 0;

	// Counter for page source dumps written to tmp/browser-timeout-*.html on wait timeouts
	private static int $timeoutDumpCounter = 0;

	public function __construct()
	{
		$browser = getenv('SELENIUM_BROWSER') ?: 'firefox';

		if ($browser === 'chrome')
		{
			$serverUrl = getenv('SELENIUM_URL') ?: 'http://selenium-chrome:4444';

			$chromeOptions = new ChromeOptions();
			$chromeOptions->addArguments([
				'--headless',
				'--no-sandbox',
				'--disable-dev-shm-usage',
				'--ignore-certificate-errors',
			]);

			$desiredCapabilities = DesiredCapabilities::chrome();
			$desiredCapabilities->setCapability('acceptInsecureCerts', true);
			$desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
		}
		else
		{
			$serverUrl = getenv('SELENIUM_URL') ?: 'http://selenium-firefox:4444';

			$firefoxOptions = new FirefoxOptions();
			$firefoxOptions->addArguments(['--headless']);

			// Firefox needs these preferences to accept self-signed HTTPS from Caddy
			$firefoxOptions->setPreference('network.stricttransportsecurity.preloadlist', false);
			$firefoxOptions->setPreference('network.stricttransportsecurity.enabled', false);
			$firefoxOptions->setPreference('security.enterprise_roots.enabled', true);
			$firefoxOptions->setPreference('security.certerrors.mitm.auto_enable_enterprise_roots', true);
			$firefoxOptions->setPreference('security.ssl.enable_ocsp_stapling', false);
			$firefoxOptions->setPreference('security.ssl.errorReporting.enabled', false);
			$firefoxOptions->setPreference('security.remote_settings.crlite_filters.enabled', false);
			$firefoxOptions->setPreference('security.OCSP.require', false);
			// Stability: disable features that cause issues in headless Docker
			$firefoxOptions->setPreference('dom.disable_beforeunload', true);
			$firefoxOptions->setPreference('dom.ipc.reportProcessHangs', false);
			$firefoxOptions->setPreference('browser.safebrowsing.enabled', false);
			$firefoxOptions->setPreference('browser.safebrowsing.malware.enabled', false);
			$firefoxOptions->setPreference('datareporting.healthreport.uploadEnabled', false);
			$firefoxOptions->setPreference('app.normandy.enabled', false);
			$firefoxOptions->setPreference('app.shield.optoutstudies.enabled', false);
			$firefoxOptions->setPreference('extensions.getAddons.cache.enabled', false);

			$desiredCapabilities = DesiredCapabilities::firefox();
			$desiredCapabilities->setCapability('acceptInsecureCerts', true);
			$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);
		}

		try
		{
			// Wait for Selenium to be ready before attempting session creation.
			// Docker Selenium containers can reject /session requests for a few
			// seconds after startup or after a previous session ends.
			//
			// Throws \Exception (not WebDriverException): after 20s of waiting
			// Selenium is genuinely down, retrying tests would be pointless.
			$statusUrl = rtrim($serverUrl, '/') . '/status';
			for ($i = 0; $i < 10; $i++)
			{
				$ch = curl_init($statusUrl);
				curl_setopt_array($ch, [
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 2,
					CURLOPT_CONNECTTIMEOUT => 2,
				]);
				$response = curl_exec($ch);
				curl_close($ch);
				if ($response !== false)
				{
					$data = json_decode($response, true);
					if (!empty($data['value']['ready']))
						break;
				}
				if ($i === 9)
					throw new \Exception("Selenium not ready after 10 attempts at $statusUrl");
				sleep(2);
			}

			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities, 30000, 60000);

			$this->driver->manage()->timeouts()->pageLoadTimeout(30);

			// visit a dummy page
			$this->driver->get(Util::getMyAddress() . '/empty.php');

			// Xdebug cookies
			$this->driver->manage()->addCookie(['name' => "XDEBUG_MODE", 'value' => 'debug']);
			$this->driver->manage()->addCookie(['name' => "XDEBUG_SESSION", 'value' => "2"]);
		}
		catch (Exception $e)
		{
			if ($this->driver)
				$this->driver->quit();
			throw $e;
		}
	}

	public static function shutdown(): void
	{
		if (self::$browser)
		{
			try
			{
				// Capture JS coverage from the final page before closing the driver.
				// Only enabled when the app was built with VITE_COVERAGE=true (CI coverage run).
				self::$browser->captureJsCoverage();
				self::$browser->driver->quit();
			}
			catch (\Exception $e)
			{
				// Driver already disconnected, session will expire on Selenium side
			}
			self::$browser = null;
		}
	}

	//Snapshot window.__coverage__ (populated by vite-plugin-istanbul) to /tmp/coverage/js-raw-<n>.json.
	//Called when leaving a page (start of get/getAnonymous) and in shutdown(), so every visited page's
	//accumulated coverage is captured. Snapshots are merged afterwards by scripts/js-coverage-report.sh.
	private function captureJsCoverage(): void
	{
		if (getenv('TEST_ENVIRONMENT') !== 'github-ci' || !file_exists('/tmp/coverage'))
			return;

		// Wrapped so a failing driver (e.g. a page that never loaded) can't abort navigation.
		try
		{
			$coverage = $this->driver->executeScript('return window.__coverage__ || null;');
		}
		catch (\Throwable $e)
		{
			return;
		}
		if (!is_array($coverage) || empty($coverage))
			return;

		self::$jsCoverageCounter++;
		$file = '/tmp/coverage/js-raw-' . self::$jsCoverageCounter . '.json';
		file_put_contents($file, json_encode($coverage));
	}

	public function assertNoErrors(): void
	{
		$this->assertNoException();
		$this->assertNoJsErrors();
	}

	private function assertNoException(): void
	{
		if (str_contains($this->driver->getPageSource(), "<h2>Exception</h2>"))
			throw new Exception($this->driver->getPageSource());
	}

	public function assertNoJsErrors(): void
	{
		$errors = $this->driver->executeScript("return window.__jsErrors || [];");
		$console = $this->driver->executeScript("return window.__consoleErrors || [];");

		// on some special pages, like editor, we don't have the included special error reporting
		if (is_null($errors) || is_null($console))
			return;

		if (!empty($errors) || !empty($console))
		{
			$msg = "JavaScript errors detected:\n";

			foreach ($errors as $error)
			{
				$msg .= "[JS ERROR] ";
				if (!empty($error['raw']))
					$msg .= $error['raw'] . "\n\n";
				else
					$msg .= sprintf(
						"%s (%s:%d:%d)\n%s\n\n",
						$error['message'], $error['source'], $error['line'], $error['column'], $error['stack']
					);
			}

			foreach ($console as $consoleError)
				$msg .= "[console.error] " . implode(" ", $consoleError['args']) . "\n\n";

			throw new Exception($msg);
		}
	}

	public function get(string $url): void
	{
		// Capture JS coverage from the page we are leaving before navigating away.
		$this->captureJsCoverage();

		if ($url != 'empty.php' && Auth::isLoggedIn())
		{
			$this->driver->manage()->addCookie(['name' => "hackedLoggedInUserID", 'value' => (string) Auth::getUserID()]);
			if (!empty($_COOKIE['disable-achievements']))
				$this->driver->manage()->addCookie(['name' => "disable-achievements", 'value' => "true"]);
		}

		$url = ltrim($url, '/');
		$this->driver->manage()->timeouts()->pageLoadTimeout(60);
		$this->driver->get(Util::getMyAddress() . '/' . $url);
		$this->assertNoErrors();
	}

	// Get URL without setting auth cookie, for testing anonymous access
	public function getAnonymous(string $url): void
	{
		$this->captureJsCoverage();

		$url = ltrim($url, '/');
		$this->driver->manage()->timeouts()->pageLoadTimeout(60);
		$this->driver->get(Util::getMyAddress() . '/' . $url);
		$this->assertNoErrors();
	}

	//Posts to a URL from the page the browser is on, the way a form on it does.
	/** @param array<string, string|int> $postData Fields to submit; empty for a form without fields */
	public function post(string $url, array $postData = []): void
	{
		$this->captureJsCoverage();

		$this->driver->executeScript(
			'window.__postPending = true;'
			. 'var form = document.createElement("form");'
			. 'form.method = "post";'
			. 'form.action = ' . json_encode('/' . ltrim($url, '/')) . ';'
			. 'var data = ' . json_encode($postData) . ';'
			. 'for (var key in data) {'
			. 'var input = document.createElement("input");'
			. 'input.type = "hidden";'
			. 'input.name = key;'
			. 'input.value = data[key];'
			. 'form.appendChild(input);'
			. '}'
			. 'document.body.appendChild(form);'
			. 'form.submit();'
		);
		// The response can land on the same URL, so wait for the document to be
		// replaced instead of for the address to change.
		$this->waitUntilJs('window.__postPending === undefined && document.readyState === "complete"');
	}

	public function clickId($id)
	{
		$this->findById($id)->click();
		$this->assertNoErrors();
	}

	// Click an element and accept any confirm() dialog it opens.
	public function clickIdAndAcceptAlert($id)
	{
		$this->findById($id)->click();
		$this->driver->switchTo()->alert()->accept();
		$this->assertNoErrors();
	}

	// Presses Save of the board editor and answers its question what was changed and why.
	public function saveFromEditor(string $description = ''): void
	{
		$this->clickId('saveSGFButton');
		if ($description !== '')
			$this->findById('sgfDescriptionField')->sendKeys($description);
		$this->clickId('sgfDescriptionSave');
	}

	public function clickCssSelect($selector)
	{
		$this->find($selector)->click();
		$this->assertNoErrors();
	}

	//Find an element with any lookup strategy, waiting until it shows up.
	//Prefer find()/findById() where they fit. This is the escape hatch for the
	//strategies they do not cover (name, xpath, tag name).
	public function findBy(WebDriverBy $by, int $timeout = 10)
	{
		return $this->waitUntil(function ($driver) use ($by) {
			return $driver->findElement($by);
		}, $timeout, 'element ' . $by->getMechanism() . ': ' . $by->getValue());
	}

	//All elements matching a lookup strategy, as they are right now.
	//Unlike findBy(), this does NOT wait: use it for counting and for asserting that something is absent.
	public function getAllBy(WebDriverBy $by)
	{
		return $this->driver->findElements($by);
	}

	//Find an element by CSS selector, waiting until it shows up.
	//Selenium only waits for readyState when navigating, which does not cover
	//elements a script adds or replaces afterwards, so a lookup that does not
	//wait races the page and makes the test flaky.
	public function find(string $selector, int $timeout = 10)
	{
		return $this->findBy(WebDriverBy::cssSelector($selector), $timeout);
	}

	//Find an element by id, waiting until it shows up.
	public function findById(string $id, int $timeout = 10)
	{
		return $this->findBy(WebDriverBy::id($id), $timeout);
	}

	//All elements matching a CSS selector, as they are right now. Does NOT wait, see getAllBy().
	public function getElements(string $selector)
	{
		return $this->getAllBy(WebDriverBy::cssSelector($selector));
	}

	public function hover($element)
	{
		$this->driver->executeScript("arguments[0].scrollIntoView({block: 'center'});", [$element]);
		try
		{
			new WebDriverActions($this->driver)->moveToElement($element)->perform();
		}
		catch (\Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException $e)
		{
			// Fallback for fixed-position elements that scrollIntoView can't reach
			$this->driver->executeScript(
				"arguments[0].dispatchEvent(new MouseEvent('mouseover', {bubbles: true, cancelable: true}));",
				[$element]
			);
		}
	}

	//Whether an element with the given id is on the page right now.
	//Unlike findById(), this does NOT wait, so it is how a test asserts that something is absent.
	public function idExists(string $id): bool
	{
		return !empty($this->driver->findElements(WebDriverBy::id($id)));
	}

	//Run a WebDriverWait condition, dumping the page source to tmp/ when it times out.
	//Transient driver errors keep the wait going instead of ending it: while a page is
	//navigating or a script is re-rendering the DOM, document.body can be missing, the
	//execution context can be destroyed and an element reference can go stale. Those all
	//mean "not ready yet". WebDriverWait on its own tolerates only NoSuchElementException,
	//so everything else is handled here, and the last error is added to the dump context
	//to keep a real failure diagnosable. A dead session is not transient and still throws.
	//$condition: Condition passed to WebDriverWait::until()
	//$timeout: Timeout in seconds
	//$interval: Polling interval in milliseconds
	//$context: Human readable description of what is being waited for
	private function waitAndDumpOnTimeout(callable $condition, int $timeout, int $interval, string $context): mixed
	{
		$lastTransientError = null;

		try
		{
			return new WebDriverWait($this->driver, $timeout, $interval)->until(
				function ($driver) use ($condition, &$lastTransientError) {
					try
					{
						return $condition($driver);
					}
					catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e)
					{
						throw $e; // the browser is gone, waiting cannot bring it back
					}
					catch (\Facebook\WebDriver\Exception\WebDriverException $e)
					{
						$lastTransientError = get_class($e) . ': ' . $e->getMessage();
						return null;
					}
				}
			);
		}
		catch (TimeoutException $e)
		{
			$this->dumpPageSourceOnTimeout(
				$context . ($lastTransientError === null ? '' : ' (last transient error: ' . $lastTransientError . ')')
			);
			throw $e;
		}
	}

	private function dumpPageSourceOnTimeout(string $context): void
	{
		self::$timeoutDumpCounter++;
		$file = ROOT . '/tmp/browser-timeout-' . date('Ymd-His') . '-' . self::$timeoutDumpCounter . '.html';
		$parts = ["WAIT: " . $context];
		$parts[] = "URL: " . $this->driverDump(fn() => $this->driver->getCurrentURL());
		$parts[] = $this->driverDump(fn() => $this->driver->getPageSource());
		try
		{
			file_put_contents($file, implode("\n\n", $parts));
		}
		catch (Throwable)
		{
			// Ignore file write errors, we don't want to mask the original timeout exception
		}
	}

	//Single driver call for the timeout dump: the value, or the reason it failed.
	private function driverDump(callable $call): string
	{
		try
		{
			return (string) $call();
		}
		catch (Throwable $e)
		{
			return '<unavailable: ' . get_class($e) . ': ' . $e->getMessage() . '>';
		}
	}

	public function waitUntilCssSelectorExistsWithText(string $selector, $text, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($selector, $text) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) > 0 && $elements[0]->getText() === $text;
			},
			$timeout,
			500,
			'css selector exists with text: ' . $selector . ' = ' . $text
		);
	}

	public function waitUntilCssSelectorDisplayed(string $selector, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($selector) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) > 0 && $elements[0]->isDisplayed();
			},
			$timeout,
			500,
			'css selector displayed: ' . $selector
		);
	}

	public function waitUntilCssSelectorDoesntExist(string $selector, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($selector) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) == 0;
			},
			$timeout,
			500,
			'css selector gone: ' . $selector
		);
	}

	//Wait until the selector matches nothing that is displayed. Covers both ways an
	//element can stop being visible: it was hidden, or it was removed. Use
	//waitUntilCssSelectorDoesntExist() when the page must really drop it from the DOM.
	public function waitUntilCssSelectorNotDisplayed(string $selector, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($selector) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) == 0 || !$elements[0]->isDisplayed();
			},
			$timeout,
			500,
			'css selector not displayed: ' . $selector
		);
	}

	//Wait until ANY of the given selectors exist (OR logic).
	//Useful for waiting for React components that can render either content or empty state.
	//$timeout: Timeout in seconds
	/** @param string[] $selectors CSS selectors to wait for (at least one must exist) */
	public function waitUntilAnyCssSelectorExists(array $selectors, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($selectors) {
				foreach ($selectors as $selector)
				{
					$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
					if (count($elements) > 0)
						return true;
				}
				return false;
			},
			$timeout,
			500,
			'any css selector exists: ' . implode(', ', $selectors)
		);
	}

	//Wait until a JavaScript expression evaluates truthy.
	//Great for besogo/board state checks that aren't exposed via the DOM.
	//$expression: A JS expression (no surrounding `return`).
	//$timeout: Timeout in seconds
	public function waitUntilJs(string $expression, int $timeout = 10): void
	{
		$this->waitAndDumpOnTimeout(
			function () use ($expression) {
				return $this->driver->executeScript('return (' . $expression . ');');
			},
			$timeout,
			200,
			'js: ' . $expression
		);
	}

	//Start recording outgoing requests (fetch and XMLHttpRequest) on the current page.
	//Call it right before the action and assert with requestsTo(); anything recorded
	//earlier is dropped, so the recording covers exactly the action under test.
	//The recorder is installed into the page, so it has to be called after the page loaded, and a navigation clears it.
	public function startRecordingRequests(): void
	{
		$this->driver->executeScript(<<<'JS'
			window.__testRequests = [];
			if (!window.__testRequestsInstalled) {
				window.__testRequestsInstalled = true;
				window.__testRecordRequest = function (url) { window.__testRequests.push(String(url)); };
				var xhrOpen = XMLHttpRequest.prototype.open;
				XMLHttpRequest.prototype.open = function (method, url) {
					window.__testRecordRequest(url);
					return xhrOpen.apply(this, arguments);
				};
				var originalFetch = window.fetch;
				window.fetch = function (input, init) {
					window.__testRecordRequest(typeof input === 'string' ? input : input.url);
					return originalFetch.apply(window, arguments);
				};
			}
			JS);
	}

	//The recorded request URLs containing the given fragment, in the order they were sent.
	//An empty result means the action sent nothing to that endpoint.
	public function requestsTo(string $fragment): array
	{
		$requests = $this->driver->executeScript('return window.__testRequests || [];');

		return array_values(array_filter($requests, function ($url) use ($fragment) {
			return str_contains($url, $fragment);
		}));
	}

	private static function recover(): void
	{
		if (!self::$browser)
			return;
		try
		{
			self::$browser->driver->getCurrentURL();
		}
		catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e)
		{
			self::$browser = null;
		}
		catch (\Facebook\WebDriver\Exception\Internal\WebDriverCurlException $e)
		{
			self::$browser = null;
		}
	}

	public static function instance()
	{
		self::recover();
		if (self::$browser == null)
			self::$browser = new Browser();

		// Dismiss any lingering alerts from previous tests
		try
		{
			self::$browser->driver->switchTo()->alert()->dismiss();
		}
		catch (\Facebook\WebDriver\Exception\NoSuchAlertException $e)
		{
			// No alert present, that's fine
		}
		self::$browser->driver->manage()->deleteAllCookies();
		self::$browser->driver->get(Util::getMyAddress() . '/empty.php');
		self::$browser->driver->executeScript('window.localStorage.clear(); window.sessionStorage.clear();');
		return self::$browser;
	}

	public function playWithResult(string $result): void
	{
		$this->waitAndDumpOnTimeout(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		}, 10, 200, 'displayResult function');
		$this->driver->executeScript("displayResult('" . $result . "')");
		$this->waitForSubmitResult();
		$this->assertNoErrors();
		// Achievement popups are blocking; dismiss any that a solve unlocked so
		// later interactions (e.g. clicking the next button) are not covered.
		$this->dismissAchievementPopups();
	}

	public function dismissAchievementPopups(): void
	{
		$this->driver->executeScript(
			"document.querySelectorAll('.toast--achievement').forEach(function (el) { el.style.display = 'none'; });"
		);
	}

	public function waitForSubmitResult(): void
	{
		$this->waitAndDumpOnTimeout(function ($driver) {
			return $driver->executeScript(
				'if (!window._submitResultPromise) return true;'
				. 'return window._submitResultPromise.then(function() { return true; }, function() { return true; });'
			);
		}, 10, 200, 'submit result promise');
	}

	public function setCookie($name, $value)
	{
		$this->driver->manage()->addCookie(['name' => $name, 'value' => $value]);
	}

	public function getTableCell($selector, $row, $column)
	{
		$table = $this->find($selector);
		return $table->findElements(WebDriverBy::tagName("tr"))[$row]->findElements(WebDriverBy::tagName("td"))[$column];
	}

	public function checkTable($selector, \Cake\TestSuite\TestCase $test, $data)
	{
		$table = $this->find($selector);
		$tableRows = $table->findElements(WebDriverBy::tagName("tr"));
		foreach ($data as $rowIndex => $row)
		{
			$tableCells = $tableRows[$rowIndex]->findElements(WebDriverBy::cssSelector("td, th"));
			foreach ($row as $cellIndex => $cellValue)
				$test->assertSame($cellValue, $tableCells[$cellIndex]->getText());
		}
	}

	//Wait until the board is drawn, and return its size in intersections.
	//Returns board size, 19 for a 19x19 board
	public function waitForBoard(): int
	{
		return (int) $this->waitAndDumpOnTimeout(function ($driver) {
			$size = $driver->executeScript('return typeof besogo !== "undefined" && besogo.scaleParameters ? besogo.scaleParameters.boardCoordSize : null;');
			if (!$size)
				return false;
			$rects = $driver->findElements(WebDriverBy::cssSelector('rect'));
			return count($rects) >= $size * $size + 1 ? $size : false;
		}, 10, 200, 'board rendered');
	}

	public function clickBoard($x, $y)
	{
		$boardSize = $this->waitForBoard();
		$clickableRects = $this->getElements('rect');
		$corner = $this->driver->executeScript('return window.besogo.boardParameters["corner"];');
		if ($corner == 'top-right' || $corner == 'bottom-right')
			$x = $boardSize - $x + 1;
		if ($corner == 'bottom-left' || $corner == 'bottom-right')
			$y = $boardSize - $y + 1;
		if (count($clickableRects) < $boardSize * $boardSize + 1)
			throw new Exception("Unexpected board coords count: " . count($clickableRects));
		$clickableRects[1 + $boardSize * ($x - 1) + ($y - 1)]->click();
		$this->assertNoErrors();
	}

	public function logoff()
	{
		$this->setCookie('hackedLoggedInUserID', '');
		Auth::logout();
	}

	//Check if page title contains the expected text (with wait)
	//$expectedTitle: The text expected in the page title
	//$timeout: Maximum wait time in seconds (default 5)
	//Returns true if title contains expected text
	public function titleContains(string $expectedTitle, int $timeout = 10): bool
	{
		return $this->waitUntil(function ($driver) use ($expectedTitle) {
			return str_contains($driver->getTitle(), $expectedTitle);
		}, $timeout, 'title contains: ' . $expectedTitle);
	}

	//The text of the page, waiting for the document to have a body first.
	//A navigation can commit before the new document is parsed, and a plain read of
	//document.body at that moment throws instead of returning the page.
	public function pageText(): string
	{
		$this->waitUntilJs('document.body !== null');

		return (string) $this->driver->executeScript('return document.body.textContent;');
	}

	//Wait until a condition on the PHP side becomes true, e.g. a row that only the
	//server round trip creates. For conditions about the page use the DOM and JS
	//helpers instead. Dumps the page when it gives up, like every other wait.
	//$condition: Function that returns truthy value when condition met
	//$timeout: Maximum wait time in seconds
	//$context: Human readable description, shown in the timeout dump
	public function waitUntil(callable $condition, int $timeout = 10, string $context = 'condition')
	{
		return $this->waitAndDumpOnTimeout(function ($driver) use ($condition) {
			try
			{
				$result = $condition($driver);
				return $result ?: null;
			}
			catch (\Exception $e)
			{
				return null;
			}
		}, $timeout, 500, $context);
	}

	//Wait until an element reference goes stale, i.e. the page replaced or removed it.
	//That is how a test knows a click navigated or a script re-rendered the element.
	public function waitUntilStale($element, int $timeout = 10): void
	{
		$this->waitUntil(function () use ($element) {
			try
			{
				$element->isDisplayed();
				return false;
			}
			catch (\Facebook\WebDriver\Exception\StaleElementReferenceException $e)
			{
				return true;
			}
			catch (\Facebook\WebDriver\Exception\NoSuchElementException $e)
			{
				return true;
			}
		}, $timeout, 'element went stale');
	}

	//Expand the comments section and wait for React to render content.
	//Clicks the COMMENTS tab once it is there; clicking the already active tab is harmless.
	public function expandComments()
	{
		$this->find('.tsumego-comments__tab[data-filter="open"]', 5)->click();

		// Wait for actual content (not skeletons) - either comments/issues, form, or login prompt
		$this->waitUntilAnyCssSelectorExists([
			'.tsumego-comment:not(.skeleton-wrapper)',   // Actual comment
			'.tsumego-issue:not(.skeleton-wrapper)',     // Actual issue
			'.tsumego-comments__form',                   // Comment form (logged-in, empty state)
			'.tsumego-comments__login-prompt',           // Login prompt (logged-out)
		]);
	}

	public $driver;
	private static ?Browser $browser = null;
}
