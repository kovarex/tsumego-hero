<?php

class UserContribution extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'user_contribution';
		parent::__construct($id, $table, $ds);
	}
}
