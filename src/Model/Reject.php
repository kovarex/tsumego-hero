<?php

class Reject extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'reject';
		parent::__construct($id, $table, $ds);
	}
	public $name = 'Reject';

}
