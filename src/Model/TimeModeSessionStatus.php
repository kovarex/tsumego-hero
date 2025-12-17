<?php

class TimeModeSessionStatus extends AppModel
{
	// Time mode session status IDs
	public const IN_PROGRESS = 1;
	public const FAILED = 2;
	public const SOLVED = 3;

	public $useTable = 'time_mode_session_status';
}
