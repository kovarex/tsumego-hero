<?php

class Favorite extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'favorite';
		parent::__construct($id, $table, $ds);
	}
}
