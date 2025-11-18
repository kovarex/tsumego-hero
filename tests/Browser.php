<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

App::uses('Util', 'Utility');

class Browser {
	public function __construct() {

		$serverUrl = Util::isInGithubCI() ? 'http://localhost:32768' : 'http://selenium-firefox:4444';
		$desiredCapabilities = DesiredCapabilities::firefox();

		$desiredCapabilities->setCapability('acceptSslCerts', false);
		$desiredCapabilities->setCapability('acceptInsecureCerts', true);

		$firefoxOptions = new FirefoxOptions();
		$firefoxOptions->addArguments(['--headless']);
		$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

		try {
			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);

			// visit a dummy page
			$this->driver->get(Util::getMyAddress() . '/empty.php');

			// Xdebug cookies
			$this->driver->manage()->addCookie(['name' => "XDEBUG_MODE", 'value' => 'debug']);
			$this->driver->manage()->addCookie(['name' => "XDEBUG_SESSION", 'value' => "2"]);
		} catch (Exception $e) {
			if ($this->driver) {
				$this->driver->quit();
			}
			throw $e;
		}
	}

	public function __destruct() {
		$this->driver->quit();
	}

	// ADDED: Read errors and throw if any exist
	public function assertNoJsErrors(): void {
		$errors = $this->driver->executeScript("return window.__jsErrors || [];");
		$console = $this->driver->executeScript("return window.__consoleErrors || [];");

		if (!empty($errors) || !empty($console)) {
			$msg = "JavaScript errors detected:\n";

			foreach ($errors as $e) {
				$msg .= "[JS ERROR] ";
				if (!empty($e['raw'])) {
					$msg .= $e['raw'] . "\n\n";
				} else {
					$msg .= sprintf(
						"%s (%s:%d:%d)\n%s\n\n",
						$e['message'], $e['source'], $e['line'], $e['column'], $e['stack']
					);
				}
			}

			foreach ($console as $c) {
				$msg .= "[console.error] " . implode(" ", $c['args']) . "\n\n";
			}

			throw new Exception($msg);
		}
	}

	public function get(string $url): void {
		if ($url != 'empty.php' && CakeSession::check("loggedInUserID")) {
			$this->driver->manage()->addCookie([
				'name' => "hackedLoggedInUserID",
				'value' => (string) CakeSession::read("loggedInUserID")
			]);
			if (!empty($_COOKIE['disable-achievements'])) {
				$this->driver->manage()->addCookie([
					'name' => "disable-achievements",
					'value' => "true"
				]);
			}
		}

		$this->driver->get(Util::getMyAddress() . '/' . $url);

		// ADDED: check for JS errors
		$this->assertNoJsErrors();
	}

	public function clickId($name) {
		$this->driver->findElement(WebDriverBy::id($name))->click();

		// ADDED: detect JS errors caused by click
		$this->assertNoJsErrors();
	}

	public static function instance() {
		static $browser = null;
		if ($browser == null) {
			$browser = new Browser();
		}
		$browser->driver->manage()->deleteAllCookies();
		return $browser;
	}

	public $driver;
}
