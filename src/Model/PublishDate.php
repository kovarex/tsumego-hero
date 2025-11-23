<?php

class PublishDate extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'publish_date';
		parent::__construct($id, $table, $ds);
	}
}
