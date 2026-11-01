<?php

namespace App\Model\Table;

//The values of a capturing race quiz (The Rules of Capturing Races): a problem with a row here is one.
//The play page loads the row with its problem (TsumegoTable's association), and a list of problems
//asks with one indexed query for all of them, never one per problem.
class TsumegoCapturingRaceTable extends AppTable
{
	public function initialize(array $config): void
	{
		parent::initialize($config);
		$this->setPrimaryKey('tsumego_id');
	}
}
