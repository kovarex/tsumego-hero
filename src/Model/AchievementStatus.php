<?php

class AchievementStatus extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'achievement_status';
		parent::__construct($id, $table, $ds);
	}
}
