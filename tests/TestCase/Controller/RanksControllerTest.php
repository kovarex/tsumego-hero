<?php

class RanksControllerTest extends ControllerTestCase {

	public function testSmokeOverview(): void {
    $result = $this->testAction('ranks/overview');
    $this->assertTrue(true);
	}
}
