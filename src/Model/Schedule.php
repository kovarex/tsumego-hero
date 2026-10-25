<?php

class Schedule extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'schedule';
		parent::__construct($id, $table, $ds);
	}
	public $name = 'Schedule';
}
