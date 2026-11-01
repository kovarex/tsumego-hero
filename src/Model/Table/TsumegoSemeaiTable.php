<?php

namespace App\Model\Table;

use App\Utility\Util;

//The values of a semeai problem: a problem with a row here is one.
//The play page loads the row with its problem (TsumegoTable's association), and a list of problems
//asks with one indexed query for all of them, never one per problem.
class TsumegoSemeaiTable extends AppTable
{
	public function initialize(array $config): void
	{
		parent::initialize($config);
		$this->setPrimaryKey('tsumego_id');
	}

	//Whether the collection holds a semeai problem. Its question is answered with a click, so a
	//collection with one is left out of the set achievements (the time and accuracy kept on it, and
	//the accuracy and speed achievements). One query over the (set_id, ...) index of set_connection
	//and the primary key here.
	public static function inSet(int $setID): bool
	{
		return (bool) Util::query('SELECT EXISTS (SELECT 1 FROM set_connection sc
JOIN tsumego_semeai ON tsumego_semeai.tsumego_id = sc.tsumego_id
WHERE sc.set_id = ?) AS has_semeai', [$setID])[0]['has_semeai'];
	}
}
