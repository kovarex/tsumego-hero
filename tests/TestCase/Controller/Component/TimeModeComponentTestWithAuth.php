<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');

class TimeModeComponentTestWithAuth extends TestCaseWithAuth {
	public function testStartTimeMode() {
		$this->login('kovarex');
		Auth::init();
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::getUser()['activeRank'] = '';
		Auth::saveUser();


		$tsumego = ClassRegistry::init('Tsumego')->find('first');

		$this->assertTrue(Auth::isInLevelMode());
		$this->testAction('tsumegos/play/' . $tsumego['Tsumego']['id'] . '?rank=15k&startTimeMode=' . TimeModeUtil::$SLOW_SPEED);
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertNotEmpty(Auth::getUser()['activeRank']);
	}
}
