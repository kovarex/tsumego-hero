<?php

require_once(__DIR__ . '/../TsumegoControllerTestCase.php');
App::uses('Auth', 'Utility');

class TimeModeComponentTest extends TsumegoControllerTestCase {
	public function testStartTimeMode() {
		$this->login('kovarex');
		Auth::init();
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::getUser()['activeRank'] = '';
		Auth::saveUser();


		$tsumego = ClassRegistry::init('Tsumego')->find('first');

		$this->assertTrue(Auth::isInLevelMode());
		$this->testAction('tsumegos/play/' . $tsumego['Tsumego']['id'] . '?rank=15k&modelink=3');
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertNotEmpty(Auth::getUser()['activeRank']);
	}
}
