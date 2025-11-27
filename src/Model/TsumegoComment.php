<?php

class TsumegoComment extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'tsumego_comment';
		parent::__construct($id, $table, $ds);
	}
}
