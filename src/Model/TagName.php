<?php

class TagName extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag_name';
		parent::__construct($id, $table, $ds);
	}
}
