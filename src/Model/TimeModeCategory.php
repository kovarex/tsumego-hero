<?php

class TimeModeCategory extends AppModel
{
	// Time mode category IDs
	public const BLITZ = 1;
	public const FAST = 2;
	public const SLOW = 3;

	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'time_mode_category';
		parent::__construct($id, $table, $ds);
	}
}
