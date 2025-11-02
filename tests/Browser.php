<?php

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class Browser {
	public function __construct() {
		$serverUrl = 'http://localhost:4444';
		$desiredCapabilities = DesiredCapabilities::firefox();

		// Disable accepting SSL certificates
		$desiredCapabilities->setCapability('acceptSslCerts', false);
		$desiredCapabilities->setCapability('acceptInsecureCerts', true);

		// Add arguments via FirefoxOptions to start headless firefox
		$firefoxOptions = new FirefoxOptions();
		$firefoxOptions->addArguments(['-headless']);
		$desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

		$this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
		// appranetly we need to visit "some" page to be able to set cookies
		$this->driver->get('https://test.tsumego.ddev.site/empty.php');

		// setting xdebug cookies, so I can debug the code invoked by requests of this driver
		$this->driver->manage()->addCookie(['name' => "XDEBUG_MODE", 'value' => "debug"]);
		$this->driver->manage()->addCookie(['name' => "XDEBUG_SESSION", 'value' => "2"]);
	}
	public function __destruct() {
		$this->driver->quit();
	}

	public function get(string $url): void {
		$this->driver->get('https://test.tsumego.ddev.site/' . $url);
	}

	public $driver;
}
