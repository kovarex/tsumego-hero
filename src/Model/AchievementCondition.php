<?php

class AchievementCondition extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'achievement_condition';
		parent::__construct($id, $table, $ds);
	}
}
