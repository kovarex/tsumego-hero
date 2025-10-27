<?php

App::uses('TimeModeUtil', 'Utility');
class TimeModeComponent extends Component {
	public function startTimeMode(int $timeMode): void {
		if (!Auth::isLoggedIn()) {
			return;
		}

		Auth::getUser()['mode'] = Constants::$TIME_MODE;
		Auth::getUser()['activeRank'] = Util::generateRandomString(self::sessionCodeLength($timeMode));
		Auth::saveUser();
	}

	private static function sessionCodeLength(int $timeMode): int {
		if ($timeMode == TimeModeUtil::$SLOW_SPEED) {
			return 15;
		}
		if ($timeMode == TimeModeUtil::$FAST_SPEED) {
			return 16;
		}
		if ($timeMode == TimeModeUtil::$BLITZ) {
			return 17;
		}
		die("Unknown time mode ");
	}
}
