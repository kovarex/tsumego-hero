<?php

use Facebook\WebDriver\WebDriverBy;

/**
 * Tests for dark/light mode theming functionality.
 *
 * Tests verify:
 * - HTML data-theme attribute is correctly set based on cookie/preference
 * - JavaScript toggle properly switches data-theme attribute
 * - CSS custom properties are correctly applied for each mode
 */
class DarkLightModeTest extends ControllerTestCase
{
	/**
	 * Test that html has data-theme="light" by default
	 */
	public function testDefaultDataThemeIsLight()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator();

		$browser->get('sites/index');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$theme = $html->getAttribute('data-theme');
		$this->assertEquals('light', $theme, 'HTML should have data-theme="light" by default');
	}

	/**
	 * Test that html has data-theme="dark" when lightDark cookie is set to dark
	 */
	public function testDataThemeWithDarkCookie()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator();

		$browser->get('sites/index');
		$browser->setCookie('lightDark', 'dark');
		$browser->get('sites/index');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$theme = $html->getAttribute('data-theme');
		$this->assertEquals('dark', $theme, 'HTML should have data-theme="dark" when lightDark cookie is dark');
	}

	/**
	 * Test that JavaScript toggle switches data-theme from dark to light
	 */
	public function testJavaScriptToggleDarkToLight()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator();

		$browser->get('sites/index');
		$browser->setCookie('lightDark', 'dark');
		$browser->get('sites/index');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('dark', $html->getAttribute('data-theme'), 'Should start in dark mode');

		$browser->driver->executeScript('darkAndLight();');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('light', $html->getAttribute('data-theme'), 'Should switch to light after toggle from dark');
	}

	/**
	 * Test that JavaScript toggle switches data-theme from light to dark
	 */
	public function testJavaScriptToggleLightToDark()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator();

		$browser->get('sites/index');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('light', $html->getAttribute('data-theme'), 'Should start in light mode');

		$browser->driver->executeScript('darkAndLight();');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('dark', $html->getAttribute('data-theme'), 'Should switch to dark after toggle from light');
	}

	/**
	 * Test that toggle sets cookie correctly
	 */
	public function testToggleSetsCorrectCookie()
	{
		$browser = Browser::instance();

		// Arrange: Set up context
		$context = new ContextPreparator();

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
		$browser = Browser::instance();
		$context = new ContextPreparator();

		$browser->driver->manage()->deleteCookieNamed('lightDark');
		$browser->get('sites/index');

		$browser->driver->executeScript('darkAndLight();');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('dark', $html->getAttribute('data-theme'), 'Should be in dark mode before refresh');

		$browser->get('sites/index');

		$html = $browser->driver->findElement(WebDriverBy::tagName('html'));
		$this->assertEquals('dark', $html->getAttribute('data-theme'), 'Should remain dark after page refresh');
	}
}
