<?php

namespace App\Model\Table;

//How a time mode run stands: still played, failed or solved.
class TimeModeSessionStatusTable extends AppTable
{
	// the rows of time_mode_session_status
	public const IN_PROGRESS = 1;
	public const FAILED = 2;
	public const SOLVED = 3;
}
