<?php

class Tsumego extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'tsumego';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];

	/**
	 * Count problems that belong to at least one public set and are not deleted.
	 */
	public static function countPublicProblems(): int
	{
		return Util::query(
			"SELECT COUNT(DISTINCT tsumego.id) AS total "
			. "FROM tsumego "
			. "WHERE EXISTS (SELECT 1 FROM set_connection sc "
			. "JOIN `set` ON `set`.id = sc.set_id AND `set`.public = 1 "
			. "WHERE sc.tsumego_id = tsumego.id) "
			. "AND tsumego.deleted IS NULL"
		)[0]["total"];
	}
}
