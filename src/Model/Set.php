<?php

class Set extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'set';
		parent::__construct($id, $table, $ds);
	}

	public $hasMany = ['SetConnection'];

	public static function getProblemCount($setID): int
	{
		return Util::query("SELECT COUNT(*) AS total FROM set_connection WHERE set_connection.set_id = ?", [$setID])[0]["total"];
	}
}
