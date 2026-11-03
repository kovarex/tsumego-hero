<?php

namespace App\Utility;

//The settings of the time mode. How a problem of a run ended is TimeModeAttemptStatusTable.
class TimeModeUtil
{
	public const int PROBLEM_COUNT = 10;
	public const float RATIO_OF_SOLVED_TO_SUCCEED = 0.79;
	public const float SECONDS_ADDED_PER_MOVE_PLAYED = 3;

	//The seconds of a problem in the Slow category, the seconds of its row in time_mode_category
	public const int CATEGORY_SLOW_SPEED_SECONDS = 240;

	public const int WAITING_FOR_RESULT_SECONDS = 15;

	public const float POINTS_RATIO_FOR_FINISHING = 0.4;
}
