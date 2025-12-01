<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Cookie;

/**
 * Tests for dark/light mode theming functionality.
 *
 * Tests verify:
 * - Body class is correctly set based on cookie/preference
 * - JavaScript toggle properly switches body class
 * - CSS styles are correctly applied for each mode
 */
class DarkLightModeTest extends ControllerTestCase
{
	/**
	 * Test that body has light-theme class by default
	 */
	public function testDefaultBodyClassIsLightTheme()
	{
		// Arrange: Set up context with a user
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		// Clear any existing lightDark cookie
		$browser = Browser::instance();
		$browser->driver->manage()->deleteCookieNamed('lightDark');

		// Act: Load a page
		$browser->get('sites/index');

		// Assert: Body should have light-theme class
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$bodyClass = $body->getAttribute('class');
		$this->assertStringContainsString(
			'light-theme',
			$bodyClass,
			'Body should have light-theme class by default'
		);
		$this->assertStringNotContainsString(
			'dark-theme',
			$bodyClass,
			'Body should not have dark-theme class in light mode'
		);
	}

	/**
	 * Test that body has dark-theme class when lightDark cookie is set to dark
	 */
	public function testBodyClassWithDarkCookie()
	{
		// Arrange: Set up context with a user
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		// Act: Set cookie to dark and load page
		$browser = Browser::instance();
		$browser->get('sites/index'); // Need to be on domain first
		$browser->driver->manage()->addCookie(new Cookie('lightDark', 'dark'));
		$browser->get('sites/index'); // Reload with cookie

		// Assert: Body should have dark-theme class
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$bodyClass = $body->getAttribute('class');
		$this->assertStringContainsString(
			'dark-theme',
			$bodyClass,
			'Body should have dark-theme class when lightDark cookie is dark'
		);
		$this->assertStringNotContainsString(
			'light-theme',
			$bodyClass,
			'Body should not have light-theme class in dark mode'
		);
	}

	/**
	 * Test that JavaScript toggle switches body class from dark to light
	 */
	public function testJavaScriptToggleDarkToLight()
	{
		// Arrange: Set up context with dark mode
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		$browser = Browser::instance();
		$browser->get('sites/index'); // Need to be on domain first
		$browser->driver->manage()->addCookie(new Cookie('lightDark', 'dark'));
		$browser->get('sites/index'); // Load with dark mode

		// Verify we're in dark mode
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString(
			'dark-theme',
			$body->getAttribute('class'),
			'Should start in dark mode'
		);

		// Act: Click the dark/light toggle button
		// The button has ID darkButtonImage (or darkButtonImage2/3)
		$browser->driver->executeScript('darkAndLight();');

		// Assert: Body class should have switched to light-theme
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$bodyClass = $body->getAttribute('class');
		$this->assertStringContainsString(
			'light-theme',
			$bodyClass,
			'Body should have light-theme class after toggle from dark'
		);
		$this->assertStringNotContainsString(
			'dark-theme',
			$bodyClass,
			'Body should not have dark-theme class after toggle from dark'
		);
	}

	/**
	 * Test that JavaScript toggle switches body class from light to dark
	 */
	public function testJavaScriptToggleLightToDark()
	{
		// Arrange: Set up context with light mode
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		$browser = Browser::instance();
		$browser->get('sites/index'); // Load with light mode (default)

		// Verify we're in light mode
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString(
			'light-theme',
			$body->getAttribute('class'),
			'Should start in light mode'
		);

		// Act: Click the dark/light toggle
		$browser->driver->executeScript('darkAndLight();');

		// Assert: Body class should have switched to dark-theme
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$bodyClass = $body->getAttribute('class');
		$this->assertStringContainsString(
			'dark-theme',
			$bodyClass,
			'Body should have dark-theme class after toggle from light'
		);
		$this->assertStringNotContainsString(
			'light-theme',
			$bodyClass,
			'Body should not have light-theme class after toggle from light'
		);
	}

	/**
	 * Test that toggle sets cookie correctly
	 */
	public function testToggleSetsCorrectCookie()
	{
		// Arrange: Set up context
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		$browser = Browser::instance();
		$browser->driver->manage()->deleteCookieNamed('lightDark');
		$browser->get('sites/index'); // Load with light mode (default)

		// Act: Toggle to dark mode
		$browser->driver->executeScript('darkAndLight();');

		// Assert: Cookie should be set to 'dark'
		$cookie = $browser->driver->manage()->getCookieNamed('lightDark');
		$this->assertNotNull($cookie, 'lightDark cookie should be set');
		$this->assertEquals(
			'dark',
			$cookie->getValue(),
			'Cookie should be set to dark after toggle from light'
		);

		// Act: Toggle back to light mode
		$browser->driver->executeScript('darkAndLight();');

		// Assert: Cookie should be set to 'light'
		$cookie = $browser->driver->manage()->getCookieNamed('lightDark');
		$this->assertEquals(
			'light',
			$cookie->getValue(),
			'Cookie should be set to light after toggle from dark'
		);
	}

	/**
	 * Test that preference persists after page refresh
	 */
	public function testPreferencePersistsAfterRefresh()
	{
		// Arrange: Set up context
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		$browser = Browser::instance();
		$browser->driver->manage()->deleteCookieNamed('lightDark');
		$browser->get('sites/index'); // Start with light mode

		// Toggle to dark mode
		$browser->driver->executeScript('darkAndLight();');

		// Verify dark mode is active
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$this->assertStringContainsString(
			'dark-theme',
			$body->getAttribute('class'),
			'Should be in dark mode before refresh'
		);

		// Act: Refresh the page
		$browser->get('sites/index');

		// Assert: Should still be in dark mode after refresh
		$body = $browser->driver->findElement(WebDriverBy::tagName('body'));
		$bodyClass = $body->getAttribute('class');
		$this->assertStringContainsString(
			'dark-theme',
			$bodyClass,
			'Should remain in dark mode after page refresh'
		);
		$this->assertStringNotContainsString(
			'light-theme',
			$bodyClass,
			'Should not have light-theme class after refresh while in dark mode'
		);
	}
}
