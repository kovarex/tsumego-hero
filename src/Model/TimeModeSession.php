<?php

class TimeModeSession extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'time_mode_session';
		parent::__construct($id, $table, $ds);
	}
}
