<?php

class JavaScriptErrorTrackingTest extends CakeTestCase
{
	/* Verifies that JavaScript error tracking is enabled in test environment.
	 * This test ensures debug mode is enabled so window.__jsErrors is initialized.
	 * Without debug mode, ALL browser tests will be blind to JavaScript errors.
	 *
	 * This prevents regressions like config/core.php disabling debug for test subdomain. */
	public function testErrorTrackingIsEnabled()
	{
		$browser = Browser::instance();

		// Navigate to page WITHOUT assertNoErrors() - we'll check manually
		$browser->driver->get(Util::getMyAddress() . '/sites/index');
		usleep(500 * 1000);

		// CRITICAL: Verify window.__jsErrors exists (only initialized when debug mode enabled)
		$jsErrors = $browser->driver->executeScript('return window.__jsErrors;');
		$this->assertNotNull($jsErrors, 'window.__jsErrors must exist! If null, debug mode is disabled and JS error tracking is BROKEN.');
		$this->assertIsArray($jsErrors, 'window.__jsErrors must be an array');

		// Also verify window.__consoleErrors exists
		$consoleErrors = $browser->driver->executeScript('return window.__consoleErrors;');
		$this->assertNotNull($consoleErrors, 'window.__consoleErrors must exist! If null, debug mode is disabled and error tracking is BROKEN.');
		$this->assertIsArray($consoleErrors, 'window.__consoleErrors must be an array');

		// SUCCESS: Error tracking infrastructure is enabled!
		// Note: We don't test error capture here - that's tested implicitly by all other browser tests.
		// This test only verifies the infrastructure exists.
	}
}
