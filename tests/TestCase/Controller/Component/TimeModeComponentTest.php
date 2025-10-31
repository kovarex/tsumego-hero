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
				$previousRank = Rating::getRankMinimalRatingFromReadableRating($previousRank['TimeModeRank']['name']);
				$currentRank = Rating::getRankMinimalRatingFromReadableRating($timeModeRank['TimeModeRank']['name']);
				$this->assertTrue($previousRank < $currentRank);
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
		$timeModeRank = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '5k']])['TimeModeRank'];
		$this->testAction('tsumegos/play/' . $tsumego['Tsumego']['id'] .
			'?startTimeMode&categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED .
			'&rankID=' . $timeModeRank['id']);
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertNotEmpty(Auth::getUser()['activeRank']);
	}
}
