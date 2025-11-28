<?php

class TagConnection extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag_connection';
		parent::__construct($id, $table, $ds);
	}
}
