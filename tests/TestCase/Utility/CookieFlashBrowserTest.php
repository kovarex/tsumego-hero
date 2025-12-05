<?php

App::uses('Browser', '.');
App::uses('CookieFlash', 'Utility');

use Facebook\WebDriver\WebDriverBy;

/**
 * Browser tests for CookieFlash functionality.
 *
 * Tests that flash messages render correctly in the browser and auto-clear.
 */
class CookieFlashBrowserTest extends CakeTestCase
{
	/**
	 * Test that flash message appears after failed login attempt
	 */
	public function testFlashMessageAppearsAfterFailedLogin()
	{
		$browser = Browser::instance();

		// Navigate to login page
		$browser->get('users/login');

		// Submit login form with invalid credentials
		$browser->driver->findElement(WebDriverBy::id('UserName'))->sendKeys('nonexistent_user');
		$browser->driver->findElement(WebDriverBy::id('password'))->sendKeys('wrong_password');
		$browser->driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'))->click();

		// Wait for page to load
		sleep(1);

		// Check for flash message in page source
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('alert', $pageSource, 'Flash message alert div should be present');
		$this->assertStringContainsString('Unknown user', $pageSource, 'Flash message should contain error text');
	}

	/**
	 * Test that flash message disappears after being displayed once
	 */
	public function testFlashMessageDisappearsAfterOnePageView()
	{
		$browser = Browser::instance();

		// Navigate to login page and submit invalid credentials
		$browser->get('users/login');
		$browser->driver->findElement(WebDriverBy::id('UserName'))->sendKeys('test');
		$browser->driver->findElement(WebDriverBy::id('password'))->sendKeys('test');
		$browser->driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'))->click();
		sleep(1);

		// Flash message should be present on first view
		$pageSource1 = $browser->driver->getPageSource();
		$this->assertStringContainsString('alert', $pageSource1, 'Flash message should appear after failed login');

		// Navigate to another page
		$browser->get('sets');
		sleep(1);

		// Flash message should NOT be present on subsequent page
		$pageSource2 = $browser->driver->getPageSource();
		$this->assertStringNotContainsString('Unknown user', $pageSource2, 'Flash message should not persist to next page');
	}

	/**
	 * Test CookieFlash::render() with different message types
	 */
	public function testFlashMessageTypesRenderWithCorrectCSS()
	{
		// This test requires controller support for different flash types
		// For now, we test that error type renders correctly via failed login

		$browser = Browser::instance();
		$browser->get('users/login');
		$browser->driver->findElement(WebDriverBy::id('UserName'))->sendKeys('test');
		$browser->driver->findElement(WebDriverBy::id('password'))->sendKeys('wrong');
		$browser->driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'))->click();
		sleep(1);

		$pageSource = $browser->driver->getPageSource();

		// Check for error-type CSS class
		$this->assertStringContainsString('alert-error', $pageSource, 'Error flash should have alert-error CSS class');
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void
	{
		parent::tearDown();
		// Clear any lingering flash cookies
		CookieFlash::clear();
	}
}
