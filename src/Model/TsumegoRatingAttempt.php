<?php

class TsumegoRatingAttempt extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tsumego_rating_attempt';
		parent::__construct($id, $table, $ds);
	}
}
