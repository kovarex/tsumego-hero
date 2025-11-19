<?php

require_once(__DIR__ . '/../ContextPreparator.php');

class BesogoTest extends ControllerTestCase {
	public function testBesogoTests() {
		$browser = Browser::instance();
		$browser->get("/besogo/tests.html");
		$pageSource = $browser->driver->getPageSource();
		$this->assertTrue(substr_count($pageSource, "passed") > 10);
		$this->assertTrue(substr_count($pageSource, "&nbsp;failed&nbsp;") == 0);
	}
}
