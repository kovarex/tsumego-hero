<?php

class AchievementCondition extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'achievement_condition';
		parent::__construct($id, $table, $ds);
	}
}
