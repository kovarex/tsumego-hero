<?php
class RanksControllerTest extends ControllerTestCase {

	/**
	 * @return void
	 */
	public function overview(): void {
		$this->loadModel('Tsumego');
		$this->loadModel('User');
		$this->loadModel('RankOverview');
		$this->loadModel('RankSetting');
		$this->loadModel('Set');
		CakeSession::write('title', 'Time Mode - Select');
		CakeSession::write('page', 'time mode');
	}

	/**
	 * @param string|null $hash
	 * @return void
	 */
	public function result($hash = null): void {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('RankOverview');
		CakeSession::write('title', 'Time Mode - Result');
		CakeSession::write('page', 'time mode');
		$sess = CakeSession::read('loggedInUser.User.activeRank');
		CakeSession::write('loggedInUser.User.activeRank', 0);
		CakeSession::write('loggedInUser.User.mode', 1);
	}

	private function calculatePoints($time = null, $max = null) {
		$rx = 0;
		if ($max == 240) {
			$rx = 20 + round($time / 3);
		} elseif ($max == 60) {
			$rx = 40 + round($time);
		} elseif ($max == 30) {
			$rx = 40 + round($time * 2);
		}

		return $rx;
	}

}
