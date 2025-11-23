<?php

class SetConnection extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'set_connection';
		parent::__construct($id, $table, $ds);
	}

	public $belongsTo = [
		'Tsumego',
		'Set',
	];
}
