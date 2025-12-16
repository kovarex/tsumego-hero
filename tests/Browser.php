<?php

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Interactions\WebDriverActions;

App::uses('Util', 'Utility');

class Browser
{
	private array $ignoredJsErrorPatterns = [];

	public function __construct()
	{
		// Allow override via SELENIUM_URL environment variable (for CI flexibility)
		$serverUrl = getenv('SELENIUM_URL') ?: 'http://selenium-hub:4444';

		// Set HEADED=1 environment variable to watch tests visually
		$chromeOptions = new ChromeOptions();
		$chromeOptions->setExperimentalOption('w3c', true);

		$isHeaded = getenv('HEADED') === '1' || getenv('HEADED') === 'true';
		if (!$isHeaded)
			$chromeOptions->addArguments([
				'--headless=new',
				'--no-sandbox',
				'--disable-dev-shm-usage',
				'--disable-gpu'
			]);

		$desiredCapabilities = DesiredCapabilities::chrome();
		$desiredCapabilities->setCapability('acceptInsecureCerts', true);
		$desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

		try
		{
			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);

			$this->driver->manage()->timeouts()->pageLoadTimeout(30);

			$this->driver->get(Util::getMyAddress() . '/empty.php');

			// CRITICAL: Signal test mode ONCE in constructor (before any real navigation)
			// Set cookies WITHOUT explicit domain - let browser infer from current URL
			// This works for both DDEV (ddev-tsumego-web) and CI (host.docker.internal)
			$this->driver->manage()->addCookie(['name' => "PHPUNIT_TEST", 'value' => "1", 'path' => '/']);

			// Xdebug cookies
			$this->driver->manage()->addCookie(['name' => "XDEBUG_MODE", 'value' => 'debug', 'path' => '/']);
			$this->driver->manage()->addCookie(['name' => "XDEBUG_SESSION", 'value' => "2", 'path' => '/']);

			// Pass TEST_TOKEN to browser so it uses the same parallel test database
			$testToken = getenv('TEST_TOKEN');
			if ($testToken)
				$this->driver->manage()->addCookie(['name' => "TEST_TOKEN", 'value' => $testToken, 'path' => '/']);
		}
		catch (Exception $e)
		{
			if ($this->driver)
				$this->driver->quit();
			throw $e;
		}
	}

	public function __destruct()
	{
		$this->driver->quit();
	}

	/**
	 * Get current URL without test-specific query parameters
	 * Strips PHPUNIT_TEST and TEST_TOKEN parameters that are added for test mode
	 */
	public function getCurrentURL(): string
	{
		$url = $this->driver->getCurrentURL();
		// Remove test query parameters
		$url = preg_replace('/([?&])PHPUNIT_TEST=1(&|$)/', '$1', $url);
		$url = preg_replace('/([?&])TEST_TOKEN=\d+(&|$)/', '$1', $url);
		// Clean up trailing ? or & if they're the only thing left
		$url = rtrim($url, '?&');
		// Clean up double & to single &
		$url = preg_replace('/&{2,}/', '&', $url);
		return $url;
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

		// If executeScript failed due to alert, these will be null - treat as empty arrays
		$errors = $errors ?? [];
		$console = $console ?? [];

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

	/**
	 * Restore test mode cookies after deleteAllCookies() calls in tests
	 * deleteAllCookies removes auth AND test cookies, we need to put test cookies back
	 */
	public function restoreTestModeCookies(): void
	{
		$this->driver->manage()->addCookie(['name' => "PHPUNIT_TEST", 'value' => "1"]);
		// Always use test_1 for sequential tests (when TEST_TOKEN not set)
		$testToken = getenv('TEST_TOKEN') ?: '1';
		$this->driver->manage()->addCookie(['name' => "TEST_TOKEN", 'value' => $testToken]);
	}

	public function get(string $url): void
	{
		$fullUrl = Util::getMyAddress() . '/' . $url;

		// OPTIMIZATION: Pass test mode via query parameter to avoid cookie reload
		// Append PHPUNIT_TEST=1 and TEST_TOKEN to URL
		$separator = (strpos($url, '?') !== false) ? '&' : '?';
		$fullUrl .= $separator . 'PHPUNIT_TEST=1';
		// Always use test_1 for sequential tests (when TEST_TOKEN not set)
		$testToken = getenv('TEST_TOKEN') ?: '1';
		$fullUrl .= '&TEST_TOKEN=' . $testToken;

		// Set auth cookies if needed (these still require cookies unfortunately)
		$needsAuthCookies = ($url != 'empty.php' && Auth::isLoggedIn());
		if ($needsAuthCookies)
		{
			// Must navigate to empty.php FIRST to set cookies (can't set cookies without navigating to domain)
			// This avoids making a request to the real URL without auth cookies
			$this->driver->get(Util::getMyAddress() . '/empty.php');
			$this->driver->manage()->addCookie([
				'name' => "hackedLoggedInUserID",
				'value' => (string) Auth::getUserID()
			]);
			if (!empty($_COOKIE['disable-achievements']))
				$this->driver->manage()->addCookie([
					'name' => "disable-achievements",
					'value' => "true"
				]);

			// Re-set test mode cookies after navigation
			$this->driver->manage()->addCookie(['name' => "PHPUNIT_TEST", 'value' => "1", 'path' => '/']);
			// Always use test_1 for sequential tests (when TEST_TOKEN not set)
			$testToken = getenv('TEST_TOKEN') ?: '1';
			$this->driver->manage()->addCookie(['name' => "TEST_TOKEN", 'value' => $testToken, 'path' => '/']);
		}

		// Strip leading slash from $url to avoid double slashes when concatenating
		$url = ltrim($url, '/');
		// Navigate to the target URL (with auth cookies already set if needed)
		$this->driver->get(Util::getMyAddress() . '/' . $url);

		// Inject TEST_TOKEN into all forms so POST requests include it
		$testToken = getenv('TEST_TOKEN');
		if ($testToken)
			$this->driver->executeScript("
				document.querySelectorAll('form').forEach(function(form) {
					// Check if TEST_TOKEN hidden field already exists
					var existing = form.querySelector('input[name=\"TEST_TOKEN\"]');
					if (!existing) {
						var input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'TEST_TOKEN';
						input.value = '{$testToken}';
						form.appendChild(input);
					}

					// Also add PHPUNIT_TEST
					var existingTest = form.querySelector('input[name=\"PHPUNIT_TEST\"]');
					if (!existingTest) {
						var inputTest = document.createElement('input');
						inputTest.type = 'hidden';
						inputTest.name = 'PHPUNIT_TEST';
						inputTest.value = '1';
						form.appendChild(inputTest);
					}
				});
			");

		// Wait for page to be fully loaded (important in parallel testing)
		$this->driver->wait(5)->until(function ($driver) {
			return $driver->executeScript('return document.readyState') === 'complete';
		});
		$this->assertNoErrors();
	}

	public function clickId($name, $timeout = 10)
	{
		// Wait for element to be present and clickable
		$wait = new WebDriverWait($this->driver, $timeout, 500);
		$element = $wait->until(function () use ($name) {
			try
			{
				$el = $this->driver->findElement(WebDriverBy::id($name));
				// Check if element is displayed and enabled
				if ($el->isDisplayed() && $el->isEnabled())
					return $el;
			}
			catch (Exception $e)
			{
			}
			return null;
		});

		if (!$element)
			throw new Exception("Element with ID '$name' not found or not clickable after {$timeout}s");

		$element->click();
		$this->assertNoErrors();
	}

	public function clickCssSelect($name, $timeout = 10)
	{
		$wait = new WebDriverWait($this->driver, $timeout, 500);
		$element = $wait->until(function () use ($name) {
			try
			{
				$el = $this->driver->findElement(WebDriverBy::cssSelector($name));
				if ($el->isDisplayed() && $el->isEnabled())
					return $el;
			}
			catch (Exception $e)
			{
			}
			return null;
		});

		if (!$element)
			throw new Exception("Element with selector '$name' not found or not clickable after {$timeout}s");

		$element->click();
		$this->assertNoErrors();
	}

	public function getCssSelect($name)
	{
		return $this->driver->findElements(WebDriverBy::cssSelector($name));
	}

	// waits for the element to start existing and then returns it
	public function getCssSelectSafe($name, $expectedCount = null)
	{
		$this->waitUntilCssSelectorExists($name, $expectedCount);
		return $this->driver->findElements(WebDriverBy::cssSelector($name));
	}


	public function find($name)
	{
		return $this->driver->findElement(WebDriverBy::cssSelector($name));
	}

	public function hover($element)
	{
		new WebDriverActions($this->driver)->moveToElement($element)->perform();
	}

	public function idExists(string $id): bool
	{
		return !empty($this->driver-> findElements(WebDriverBy::id($id)));
	}

	public function waitUntilIDExists($id)
	{
		new WebDriverWait($this->driver, 5, 500)->until(function () { return $this->idExists('commentBox'); });
	}

	public function waitUntilCssSelectorExists(string $selector, ?int $expectedCount = null): void
	{
		new WebDriverWait($this->driver, 5, 500)->until(
			function () use ($selector, $expectedCount) {
				$elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
				return count($elements) > is_null($expectedCount) ? 0 : ($expectedCount - 1);
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
		static $browser = null;
		if ($browser == null)
			$browser = new Browser();
		// Dismiss any lingering alerts from previous tests
		try
		{
			$browser->driver->switchTo()->alert()->dismiss();
		}
		catch (\Facebook\WebDriver\Exception\NoSuchAlertException $e)
		{
			// No alert present, that's fine
		}
		$browser->driver->manage()->deleteAllCookies();
		$browser->restoreTestModeCookies(); // Restore PHPUNIT_TEST and TEST_TOKEN cookies
		$browser->clearIgnoredJsErrorPatterns(); // Reset ignored patterns for each test
		$browser->driver->get('about:blank'); // make sure any work is stopped
		$browser->driver->get(Util::getMyAddress() . '/empty.php');
		return $browser;
	}

	public function playWithResult(string $result): void
	{
		usleep(1000 * 100);
		$this->driver->executeScript("displayResult('" . $result . "')");
		usleep(1000 * 50);
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
			$tableCells = $tableRows[$rowIndex]->findElements(WebDriverBy::tagName("td"));
			foreach ($row as $cellIndex => $cellValue)
				$test->assertSame($cellValue, $tableCells[$cellIndex]->getText());
		}
	}

	public function clickBoard($x, $y)
	{
		$clickableRects = $this->getCssSelect('rect');
		$boardSize = 19;
		$corner = $this->driver->executeScript('return window.besogo.boardParameters["corner"];');
		if ($corner == 'top-right' || $corner == 'bottom-right')
			$x = $boardSize - $x + 1;
		if ($corner == 'bottom-left' || $corner == 'bottom-right')
			$y = $boardSize - $y + 1;
		if (count($clickableRects) < $boardSize * $boardSize + 1)
			throw new Exception("Unexpected board coords count: " . count($clickableRects));
		$clickableRects[1 + $boardSize * ($x - 1) + ($y - 1)]->click();
	}

	/**
	 * Wait for alert to appear (for AJAX calls that show async alerts)
	 * @param int $timeoutSeconds Maximum time to wait
	 * @return bool True if alert appeared, false if timeout
	 */
	public function waitForAlert(int $timeoutSeconds = 5): bool
	{
		try
		{
			$this->driver->wait($timeoutSeconds, 100)->until(function ($driver) {
				try
				{
					$driver->switchTo()->alert();
					return true;
				}
				catch (\Facebook\WebDriver\Exception\NoSuchAlertException $e)
				{
					return false;
				}
			});
			// Switch to alert again after wait completes to ensure context is correct
			$this->driver->switchTo()->alert();
			return true;
		}
		catch (\Facebook\WebDriver\Exception\TimeoutException $e)
		{
			return false;
		}
	}

	/**
	 * Click element and wait for alert (for testing error cases)
	 * @param string $id Element ID to click
	 * @param int $timeoutSeconds Maximum time to wait for alert
	 * @return string Alert text
	 * @throws Exception if alert doesn't appear
	 */
	public function clickIdAndExpectAlert(string $id, int $timeoutSeconds = 3): string
	{
		// NEW APPROACH: Override window.alert() to capture text without native alert dialog
		// This works around Chrome 120 CI bug where alert.accept() doesn't close the dialog
		$this->driver->executeScript("
			window.__lastAlertText = null;
			window.__originalAlert = window.alert;
			window.alert = function(text) {
				window.__lastAlertText = text;
				// Don't call original alert - just capture the text
			};
		");

		$this->clickId($id);

		// Wait for alert to be called
		$alertText = null;
		try
		{
			$this->driver->wait($timeoutSeconds, 100)->until(function ($driver) use (&$alertText) {
				$text = $driver->executeScript("return window.__lastAlertText;");
				if ($text !== null)
				{
					$alertText = $text;
					return true;
				}
				return false;
			});

			if ($alertText === null)
				throw new \Exception("Expected alert after clicking #$id was not shown within {$timeoutSeconds}s");

			// Clean up
			$this->driver->executeScript("
				window.alert = window.__originalAlert;
				window.__lastAlertText = null;
			");

			return $alertText;
		}
		catch (\Facebook\WebDriver\Exception\TimeoutException $e)
		{
			throw new \Exception("Expected alert after clicking #$id was not shown within {$timeoutSeconds}s");
		}
	}

	/**
	 * Delete authentication cookies to simulate logged-out state
	 * Preserves test mode cookies (PHPUNIT_TEST, TEST_TOKEN) for test isolation
	 */
	public function deleteAuthCookies(): void
	{
		// Delete JWT auth cookie
		try
		{
			$this->driver->manage()->deleteCookieNamed('jwt_token');
		}
		catch (Exception $e)
		{
			// Cookie might not exist
		}

		// Delete test environment hack cookie
		try
		{
			$this->driver->manage()->deleteCookieNamed('hackedLoggedInUserID');
		}
		catch (Exception $e)
		{
			// Cookie might not exist
		}

		// Delete legacy CAKEPHP session cookie if it exists
		try
		{
			$this->driver->manage()->deleteCookieNamed('CAKEPHP');
		}
		catch (Exception $e)
		{
			// Cookie might not exist
		}
	}

	public $driver;
}
