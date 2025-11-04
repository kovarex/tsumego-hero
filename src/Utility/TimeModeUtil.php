<?php

class TimeModeUtil {
	public static int $PROBLEM_COUNT = 10;
	public static int $SOLVES_TO_SOCCEED_SESSION = 8;

	public static int $CATEGORY_BLITZ = 1;
	public static int $CATEGORY_FAST_SPEED = 2;
	public static int $CATEGORY_SLOW_SPEED = 3;

	public static int $SESSION_STATUS_IN_PROGRESS = 1;
	public static int $SESSION_STATUS_FAILED = 2;
	public static int $SESSION_STATUS_SOLVED = 3;

	public static int $ATTEMPT_RESULT_QUEUED = 1;
	public static int $ATTEMPT_RESULT_SOLVED = 2;
	public static int $ATTEMPT_RESULT_FAILED = 3;
	public static int $ATTEMPT_STATUS_TIMEOUT = 4;
	public static int $ATTEMPT_STATUS_SKIPPED = 5;

	public static function attemptStatusName($statusID): string {
		switch ($statusID) {
			case self::$ATTEMPT_RESULT_QUEUED: return 'queued';
			case self::$ATTEMPT_RESULT_SOLVED: return 'solved';
			case self::$ATTEMPT_RESULT_FAILED: return 'failed';
			case self::$ATTEMPT_STATUS_TIMEOUT: return 'timeout';
			case self::$ATTEMPT_STATUS_SKIPPED: return 'skipped';
			default: return 'unknown';
		}
	}

	public static float $POINTS_RATIO_FOR_FINISHING = 0.4;
}
