<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;

App::uses('Util', 'Utility');

class Browser
{
	private array $ignoredJsErrorPatterns = [];

	public function __construct()
	{
		$serverUrl = Util::isInGithubCI() ? 'http://localhost:32768' : 'http://selenium-firefox:4444';

		$firefoxOptions = new FirefoxOptions();
		$firefoxOptions->addArguments(['--headless']);

		$serverUrl = Util::isInGithubCI() ? 'http://localhost:32768' : 'http://selenium-firefox:4444';

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

		// Build capabilities
		$desiredCapabilities = DesiredCapabilities::firefox();

		$desiredCapabilities->setCapability('acceptInsecureCerts', true);
		$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

		try
		{
			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);

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

	public function __destruct()
	{
		$this->driver->quit();
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
		if ($url != 'empty.php' && CakeSession::check("loggedInUserID"))
		{
			$this->driver->manage()->addCookie([
				'name' => "hackedLoggedInUserID",
				'value' => (string) CakeSession::read("loggedInUserID")
			]);
			if (!empty($_COOKIE['disable-achievements']))
				$this->driver->manage()->addCookie([
					'name' => "disable-achievements",
					'value' => "true"
				]);
		}

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
		$browser->driver->manage()->deleteAllCookies();
		$browser->clearIgnoredJsErrorPatterns(); // Reset ignored patterns for each test
		return $browser;
	}

	public $driver;
}
