<?php

class Tag extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag';
		parent::__construct($id, $table, $ds);
	}
	public static int $POPULAR_COUNT = 10;
}
