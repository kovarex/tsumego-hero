<?php

class TsumegoAttempt extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tsumego_attempt';
		parent::__construct($id, $table, $ds);
	}
}
