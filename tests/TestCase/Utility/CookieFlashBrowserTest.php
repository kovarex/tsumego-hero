<?php

/**
 * Browser tests for CookieFlash functionality.
 *
 * Tests that flash messages render correctly in the browser and auto-clear.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
use App\Utility\CookieFlash;

class CookieFlashBrowserTest extends CakeTestCase
{
	//Test that flash message appears after failed login attempt
	public function testFlashMessageAppearsAfterFailedLogin()
	{
		$browser = Browser::instance();

		// Navigate to login page
		$browser->get('users/login');

		// Submit login form with invalid credentials
		$browser->findById('UserName')->sendKeys('nonexistent_user');
		$browser->findById('password')->sendKeys('wrong_password');
		$browser->find('#UserLoginForm button[type="submit"]')->click();

		// Wait for page to load
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Wrong username or password.")');

		// Check for flash message in page source
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('alert', $pageSource, 'Flash message alert div should be present');
		$this->assertStringContainsString('Wrong username or password.', $pageSource, 'Flash message should contain error text');
	}

	//Test that flash message disappears after being displayed once
	public function testFlashMessageDisappearsAfterOnePageView()
	{
		$browser = Browser::instance();

		// Navigate to login page and submit invalid credentials
		$browser->get('users/login');
		$browser->findById('UserName')->sendKeys('test');
		$browser->findById('password')->sendKeys('test');
		$browser->find('#UserLoginForm button[type="submit"]')->click();
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("alert")');

		// Flash message should be present on first view
		$pageSource1 = $browser->driver->getPageSource();
		$this->assertStringContainsString('alert', $pageSource1, 'Flash message should appear after failed login');

		// Navigate to another page
		$browser->get('sets');

		// Flash message should NOT be present on subsequent page
		$pageSource2 = $browser->driver->getPageSource();
		$this->assertStringNotContainsString('Wrong username or password.', $pageSource2, 'Flash message should not persist to next page');
	}

	//Test CookieFlash::render() with different message types
	public function testFlashMessageTypesRenderWithCorrectCSS()
	{
		// This test requires controller support for different flash types
		// For now, we test that error type renders correctly via failed login

		$browser = Browser::instance();
		$browser->get('users/login');
		$browser->findById('UserName')->sendKeys('test');
		$browser->findById('password')->sendKeys('wrong');
		$browser->find('#UserLoginForm button[type="submit"]')->click();
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("alert-error")');

		$pageSource = $browser->driver->getPageSource();

		// Check for error-type CSS class
		$this->assertStringContainsString('alert-error', $pageSource, 'Error flash should have alert-error CSS class');
	}

	//Clean up after tests
	public function tearDown(): void
	{
		parent::tearDown();
		// Clear any lingering flash cookies
		CookieFlash::clear();
	}
}
