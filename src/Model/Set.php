<?php

use App\Utility\Util;

class Set extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'set';
		parent::__construct($id, $table, $ds);
	}

	public $hasMany = ['SetConnection'];

	public static function getProblemCount(int $setID): int
	{
		return Util::query("SELECT COUNT(*) AS total FROM set_connection WHERE set_connection.set_id = ?", [$setID])[0]["total"];
	}
}
