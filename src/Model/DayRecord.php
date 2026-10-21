<?php

class DayRecord extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'day_record';
		parent::__construct($id, $table, $ds);
	}
}
