<?php

class ProgressDeletion extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'progress_deletion';
		parent::__construct($id, $table, $ds);
	}
}
