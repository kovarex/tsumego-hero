<?php

class DayRecord extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'day_record';
		parent::__construct($id, $table, $ds);
	}
}
