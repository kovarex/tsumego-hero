<?php

class PurgeList extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'purge_list';
		parent::__construct($id, $table, $ds);
	}
}
