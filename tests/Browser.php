<?php

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Interactions\WebDriverActions;

App::uses('Util', 'Utility');

class Browser
{
	private array $ignoredJsErrorPatterns = [];

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

			$desiredCapabilities = DesiredCapabilities::firefox();
			$desiredCapabilities->setCapability('acceptInsecureCerts', true);
			$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);
		}

		try
		{
			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);

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
			self::$browser->driver->quit();
			self::$browser = null;
		}
	}

	public function assertNoErrors(): void
	{
		$this->assertNoException();
		$this->assertNoJsErrors();
	}

	public function assertNoException(): void
	{
		if (str_contains($this->driver->getPageSource(), "<h2>Exception</h2>"))
			throw new Exception($this->driver->getPageSource());
	}

	public function assertNoJsErrors(): void
	{
		$errors = $this->driver->executeScript("return window.__jsErrors || [];");
		$console = $this->driver->executeScript("return window.__consoleErrors || [];");

		// on some special pages, like editor, we don't have the included special error reporting
		if (is_null($errors))
			return;

		// Filter out ignored error patterns
		$errors = array_filter($errors, fn($e) => !$this->isIgnoredError($e));
		$console = array_filter($console, fn($c) => !$this->isIgnoredConsoleError($c));

		if (!empty($errors) || !empty($console))
		{
			$msg = "JavaScript errors detected:\n";

			foreach ($errors as $e)
			{
				$msg .= "[JS ERROR] ";
				if (!empty($e['raw']))
					$msg .= $e['raw'] . "\n\n";
				else
					$msg .= sprintf(
						"%s (%s:%d:%d)\n%s\n\n",
						$e['message'], $e['source'], $e['line'], $e['column'], $e['stack']
					);
			}

			foreach ($console as $c)
				$msg .= "[console.error] " . implode(" ", $c['args']) . "\n\n";

			throw new Exception($msg);
		}
	}

	public function get(string $url): void
	{
		if ($url != 'empty.php' && Auth::isLoggedIn())
		{
			$this->driver->manage()->addCookie(['name' => "hackedLoggedInUserID", 'value' => (string) Auth::getUserID()]);
			if (!empty($_COOKIE['disable-achievements']))
				$this->driver->manage()->addCookie(['name' => "disable-achievements", 'value' => "true"]);
		}

		// Strip leading slash from $url to avoid double slashes when concatenating
		$url = ltrim($url, '/');
		$this->driver->get(Util::getMyAddress() . '/' . $url);
		$this->assertNoErrors();
	}

	public function clickId($name)
	{
		$this->driver->findElement(WebDriverBy::id($name))->click();
		$this->assertNoErrors();
	}

	public function clickCssSelect($name)
	{
		$this->driver->findElement(WebDriverBy::cssSelector($name))->click();
		$this->assertNoErrors();
	}

	public function getCssSelect($name)
	{
		return $this->driver->findElements(WebDriverBy::cssSelector($name));
	}

	public function find($name)
	{
		return $this->driver->findElement(WebDriverBy::cssSelector($name));
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

	public function idExists(string $id): bool
	{
		return !empty($this->driver-> findElements(WebDriverBy::id($id)));
	}

	public function waitUntilIDExists($id)
	{
		new WebDriverWait($this->driver, 5, 500)->until(function () { return $this->idExists('commentBox'); });
	}

	public function waitUntilCssSelectorExists(string $selector, int $timeout = 5): void
	{
		new WebDriverWait($this->driver, $timeout, 500)->until(
			function () use ($selector) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) > 0;
			}
		);
	}

	public function waitUntilCssSelectorExistsWithText(string $selector, $text, int $timeout = 5): void
	{
		new WebDriverWait($this->driver, $timeout, 500)->until(
			function () use ($selector, $text) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) > 0 && $elements[0]->getText() === $text;
			}
		);
	}

	public function waitUntilCssSelectorDoesntExist(string $selector, int $timeout = 5): void
	{
		new WebDriverWait($this->driver, $timeout, 500)->until(
			function () use ($selector) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) == 0;
			}
		);
	}

	/**
	 * Wait until ANY of the given selectors exist (OR logic).
	 * Useful for waiting for React components that can render either content or empty state.
	 *
	 * @param string[] $selectors CSS selectors to wait for (at least one must exist)
	 * @param int $timeout Timeout in seconds
	 */
	public function waitUntilAnyCssSelectorExists(array $selectors, int $timeout = 5): void
	{
		new WebDriverWait($this->driver, $timeout, 500)->until(
			function () use ($selectors) {
				foreach ($selectors as $selector)
				{
					$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
					if (count($elements) > 0)
						return true;
				}
				return false;
			}
		);
	}

	/**
	 * Perform a drag-and-drop operation using WebDriverActions.
	 *
	 * @param \Facebook\WebDriver\WebDriverElement $source The element to drag
	 * @param \Facebook\WebDriver\WebDriverElement $target The element to drop onto
	 */
	public function dragAndDrop($source, $target): void
	{
		$actions = new WebDriverActions($this->driver);
		$actions->dragAndDrop($source, $target)->perform();
	}

	/**
	 * Drag element to target using click-hold-move-release pattern.
	 * More reliable for some JS frameworks (like SortableJS) than native dragAndDrop.
	 * Automatically scrolls elements into view before dragging.
	 *
	 * @param \Facebook\WebDriver\WebDriverElement $source The element to drag
	 * @param \Facebook\WebDriver\WebDriverElement $target The element to drop onto
	 * @param int $holdMs Milliseconds to hold before moving (used for usleep between actions)
	 */
	public function dragAndDropWithHold($source, $target, int $holdMs = 100): void
	{
		// Scroll source element into viewport center
		$this->driver->executeScript("arguments[0].scrollIntoView({block: 'center'});", [$source]);
		usleep(100000);

		$actions = new WebDriverActions($this->driver);
		$actions->clickAndHold($source)->perform();

		usleep($holdMs * 1000);

		// Scroll target into view
		$this->driver->executeScript("arguments[0].scrollIntoView({block: 'center'});", [$target]);
		usleep(100000);

		$actions = new WebDriverActions($this->driver);
		$actions->moveToElement($target)->perform();

		usleep($holdMs * 1000);

		$actions = new WebDriverActions($this->driver);
		$actions->release()->perform();
	}

	/**
	 * Press the Escape key to cancel drag operations.
	 * Uses JavaScript to dispatch keydown event since WebDriverActions.sendKeys needs an element.
	 */
	public function pressEscape(): void
	{
		$this->driver->executeScript("
			var event = new KeyboardEvent('keydown', {
				key: 'Escape',
				code: 'Escape',
				keyCode: 27,
				which: 27,
				bubbles: true,
				cancelable: true
			});
			document.dispatchEvent(event);
		");
		$this->assertNoErrors();
	}

	public function ignoreJsErrorPattern(string $pattern): void
	{
		$this->ignoredJsErrorPatterns[] = $pattern;
	}

	public function clearIgnoredJsErrorPatterns(): void
	{
		$this->ignoredJsErrorPatterns = [];
	}

	private function isIgnoredError(array $error): bool
	{
		$message = $error['message'] ?? $error['raw'] ?? '';
		foreach ($this->ignoredJsErrorPatterns as $pattern)
			if (str_contains($message, $pattern))
				return true;
		return false;
	}

	private function isIgnoredConsoleError(array $console): bool
	{
		$message = implode(" ", $console['args'] ?? []);
		foreach ($this->ignoredJsErrorPatterns as $pattern)
			if (str_contains($message, $pattern))
				return true;
		return false;
	}

	public static function instance()
	{
		if (self::$browser == null)
			self::$browser = new Browser();

		// Recover from crashed/disconnected browser session
		try
		{
			self::$browser->driver->getCurrentURL();
		}
		catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e)
		{
			self::$browser = new Browser();
		}
		catch (\Facebook\WebDriver\Exception\Internal\WebDriverCurlException $e)
		{
			self::$browser = new Browser();
		}

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
		self::$browser->clearIgnoredJsErrorPatterns(); // Reset ignored patterns for each test
		self::$browser->driver->get(Util::getMyAddress() . '/empty.php');
		self::$browser->driver->executeScript('window.localStorage.clear(); window.sessionStorage.clear();');
		return self::$browser;
	}

	public function playWithResult(string $result): void
	{
		$wait = new WebDriverWait($this->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		});
		$this->driver->executeScript("displayResult('" . $result . "')");
		$this->assertNoErrors();
	}

	public function getAlertText()
	{
		return $this->driver->switchTo()->alert()->getText();
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

	public function checkTable($selector, CakeTestCase $test, $data)
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

	public function waitForBoard(): int
	{
		$wait = new WebDriverWait($this->driver, 10, 200);
		return $wait->until(function ($driver) {
			$size = $driver->executeScript('return typeof besogo !== "undefined" && besogo.scaleParameters ? besogo.scaleParameters.boardCoordSize : null;');
			if (!$size)
				return false;
			$rects = $driver->findElements(WebDriverBy::cssSelector('rect'));
			return count($rects) >= $size * $size + 1 ? $size : false;
		});
	}

	public function clickBoard($x, $y)
	{
		$boardSize = $this->waitForBoard();
		$clickableRects = $this->getCssSelect('rect');
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

	/**
	 * Click an element and expect a JavaScript alert to appear.
	 * Handles both sync alerts (thrown during click, as in Firefox) and
	 * async alerts (appearing after AJAX response, as in Chrome).
	 * Returns the alert text.
	 */
	public function clickIdExpectingAlert(string $id): string
	{
		// Override window.alert to capture the message without showing a real dialog.
		// This works identically in Chrome and Firefox — no browser-specific alert handling needed.
		$this->driver->executeScript("
			window.__capturedAlertText = null;
			window.__savedAlert = window.__savedAlert || window.alert;
			window.alert = function(msg) { window.__capturedAlertText = msg; };
		");

		// Click the element directly (skip assertNoErrors since alert is expected)
		$this->driver->findElement(WebDriverBy::id($id))->click();

		// Wait for the AJAX response to trigger the captured alert
		$wait = new WebDriverWait($this->driver, 5, 200);
		$alertText = $wait->until(function () {
			return $this->driver->executeScript("return window.__capturedAlertText;");
		});

		// Restore original alert function
		$this->driver->executeScript("
			if (window.__savedAlert) { window.alert = window.__savedAlert; delete window.__savedAlert; }
		");

		return $alertText;
	}

	public function getWithPostData($url, $postData, int $timeout = 10)
	{
		$markerId = 'selenium-navigation-marker-' . uniqid();
		$this->driver->executeScript("
			var marker = document.createElement('div');
			marker.id = '" . $markerId . "';
			marker.style.display = 'none';
			document.body.appendChild(marker);
		");

		$this->driver->executeScript("
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = '" . $url . "';

			var data = " . json_encode($postData) . ";
			for (var key in data)
			{
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = key;
				input.value = data[key];
				form.appendChild(input);
			}
			document.body.appendChild(form);
			form.submit();");

		$wait = new WebDriverWait($this->driver, $timeout, 100);
		$wait->until(function () use ($markerId) {
			$markerExists = $this->driver->executeScript(
				"return document.getElementById('" . $markerId . "') !== null;"
			);
			if ($markerExists)
				return false;
			$readyState = $this->driver->executeScript('return document.readyState');
			return $readyState === 'complete';
		});
	}

	public function logoff()
	{
		$this->setCookie('hackedLoggedInUserID', '');
		Auth::logout();
	}

	/**
	 * Check if page title contains the expected text (with wait)
	 * @param string $expectedTitle The text expected in the page title
	 * @param int $timeout Maximum wait time in seconds (default 5)
	 * @return bool True if title contains expected text
	 */
	public function titleContains(string $expectedTitle, int $timeout = 5): bool
	{
		return $this->waitUntil(function ($driver) use ($expectedTitle) {
			return str_contains($driver->getTitle(), $expectedTitle);
		}, $timeout);
	}

	/**
	 * Find element by ID (with implicit wait)
	 * @param string $id The element ID
	 * @param int $timeout Maximum wait time in seconds (default 5)
	 * @return \Facebook\WebDriver\Remote\RemoteWebElement
	 */
	public function byId(string $id, int $timeout = 5)
	{
		return $this->waitUntil(function ($driver) use ($id) {
			return $driver->findElement(WebDriverBy::id($id));
		}, $timeout);
	}

	/**
	 * Find element by CSS selector (with implicit wait)
	 * @param string $selector The CSS selector
	 * @param int $timeout Maximum wait time in seconds (default 5)
	 * @return \Facebook\WebDriver\Remote\RemoteWebElement
	 */
	public function byCssSelector(string $selector, int $timeout = 5)
	{
		return $this->waitUntil(function ($driver) use ($selector) {
			return $driver->findElement(WebDriverBy::cssSelector($selector));
		}, $timeout);
	}

	/**
	 * Wait until a condition is met or timeout
	 * @param callable $condition Function that returns truthy value when condition met
	 * @param int $timeout Maximum wait time in seconds
	 * @return mixed The result of the condition function
	 */
	private function waitUntil(callable $condition, int $timeout = 5)
	{
		$wait = new WebDriverWait($this->driver, $timeout, 500);
		return $wait->until(function ($driver) use ($condition) {
			try
			{
				$result = $condition($driver);
				return $result ?: null;
			}
			catch (\Exception $e)
			{
				return null;
			}
		});
	}

	/**
	 * Helper to expand comments section and wait for React to render content.
	 * Clicks the COMMENTS tab to ensure it's visible, then waits for content.
	 */
	public function expandComments()
	{
		// Wait for React to mount (tabs appear first)
		$this->waitUntilCssSelectorExists('.tsumego-comments__tab[data-filter="open"]', 5);

		// Click COMMENTS tab to ensure content is visible (clicking active tab is harmless)
		$this->driver->findElement(WebDriverBy::cssSelector('.tsumego-comments__tab[data-filter="open"]'))->click();

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
