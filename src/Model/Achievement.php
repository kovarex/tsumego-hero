<?php

class Achievement extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'achievement';
		parent::__construct($id, $table, $ds);
	}
}
