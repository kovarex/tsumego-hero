<?php

class TimeModeControllerTest extends ControllerTestCase {
	public function testSmokeOverview(): void {
		$this->testAction('timeMode/overview');
		$this->assertTrue(true);
	}
}
