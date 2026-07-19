<?php

use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class BesogoTest extends ControllerTestCase
{
	use RetryTrait;
	public function testBesogoTests()
	{
		$browser = Browser::instance();
		$browser->get("/besogo/tests.html");
		$pageSource = $browser->driver->getPageSource();
		$this->assertTrue(substr_count($pageSource, "passed") > 10);
		$this->assertTrue(substr_count($pageSource, "&nbsp;failed&nbsp;") == 0);
	}
}
