<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

App::uses('Util', 'Utility');

class Browser {
	public function __construct() {

		$serverUrl = Util::isInGithubCI() ? 'http://localhost:32768' : 'http://selenium-firefox:4444';
		$desiredCapabilities = DesiredCapabilities::firefox();

		// Disable accepting SSL certificates
		$desiredCapabilities->setCapability('acceptSslCerts', false);
		$desiredCapabilities->setCapability('acceptInsecureCerts', true);

		// Add arguments via FirefoxOptions to start headless firefox
		$firefoxOptions = new FirefoxOptions();
		$firefoxOptions->addArguments(['-headless']);
		$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

		try {
			$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
			// we apparently need to visit "some" page to be able to set cookies
			$this->get('empty.php');

			// setting xdebug cookies, so I can debug the code invoked by requests of this driver
			$this->driver->manage()->addCookie(['name' => "XDEBUG_MODE", 'value' => "debug"]);
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

	public function get(string $url): void {
		if ($url != 'empty.php' && CakeSession::check("loggedInUserID")) {
			$this->driver->manage()->addCookie(['name' => "hackedLoggedInUserID", 'value' => strval(CakeSession::read("loggedInUserID"))]);
			if ($_COOKIE['disable-achievements']) {
				$this->driver->manage()->addCookie(['name' => "disable-achievements", 'value' => "true"]);
			}
		}
		// This is what I would expect to be the proper way, but it hangs session start on the client
		// $browser->driver->manage()->addCookie(['name' => "myApp", 'value' => session_id()]);
		$this->driver->get(self::getAddress() . '/' . $url);
	}

	public static function getAddress() {
		if ($url = @$_SERVER['DDEV_PRIMARY_URL']) {
			return str_replace('https://', 'https://test.', $url);
		}
		if (Util::isInGithubCI()) {
			return $_SERVER['TEST_APP_URL'];
		}
		return "https://test.tsumego.ddev.site:33003";
	}

	public $driver;
}
