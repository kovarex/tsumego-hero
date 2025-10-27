<?php

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
		if ($timeMode == self::$TIME_MODE_SLOW) {
			return 15;
		}
		if ($timeMode == self::$TIME_MODE_FAST) {
			return 16;
		}
		if ($timeMode == self::$TIME_MODE_BLITZ) {
			return 16;
		}
		die("Unknown time mode ");
	}

	public static $TIME_MODE_SLOW = 1;
	public static $TIME_MODE_FAST = 2;
	public static $TIME_MODE_BLITZ = 3;
};
