<?php

class Tag extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag';
		parent::__construct($id, $table, $ds);
	}
}
