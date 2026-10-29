<?php

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;

//The base of the tables.
//The names are singular like the database's: TsumegoAttemptTable is the table tsumego_attempt, its rows
//are TsumegoAttempt entities and fetchTable('TsumegoAttempt') gives it, all without configuration.
class AppTable extends Table
{
	//Fills created on a new row and modified on every save, on the tables that have them, unless the
	//save sets them itself.
	public function initialize(array $config): void
	{
		parent::initialize($config);
		$this->addBehavior('Timestamp');
	}

	//Cake wraps every save in its own transaction. Since MariaDB 11.6.2 (production runs 11.7) a
	//transaction that read a row fails its update with 1020 "Record has changed since last read" when
	//another request changed the row in between (forum#35, forum#102), although the save only writes
	//the fields it was given and nothing gets lost. So a save is no transaction of its own, and it
	//runs in the transaction Cake 2 started, if there is one; explicit transactions keep the check.
	public function save(EntityInterface $entity, array $options = []): EntityInterface|false
	{
		return parent::save($entity, $options + ['atomic' => false]);
	}

	//Nor is a delete: it runs in the transaction Cake 2 started, if there is one.
	public function delete(EntityInterface $entity, array $options = []): bool
	{
		return parent::delete($entity, $options + ['atomic' => false]);
	}
}
