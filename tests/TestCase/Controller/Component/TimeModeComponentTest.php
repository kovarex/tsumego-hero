<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');

class TimeModeComponentTest extends TestCaseWithAuth {
	public function testTimeModeRankContentsIntegrity() {
		// The ranks in the time_mode_rank table should be always ascending when ordered by id.
		// This fact is used to conveniently deduce the rating range of the current rank
		$allTimeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']) ?: [];
		$this->assertNotEmpty($allTimeModeRanks);

		$previousRank = null;
		foreach ($allTimeModeRanks as $timeModeRank) {
			if ($previousRank) {
				$previousRank = Rating::GetRankFromReadableRank($previousRank['name']);
				$currentRank = Rating::GetRankFromReadableRank($timeModeRank['name']);
				$this->assertLessThan($previousRank, $currentRank);
			}
			$previousRank = $timeModeRank;
		}
	}


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
