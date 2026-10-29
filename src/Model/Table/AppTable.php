<?php

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use DateTimeImmutable;

//The base of the tables.
//The names are singular like the database's: TsumegoAttemptTable is the table tsumego_attempt, its rows
//are TsumegoAttempt entities and fetchTable('TsumegoAttempt') gives it, all without configuration.
class AppTable extends Table
{
	//Cake wraps every save in its own transaction. Since MariaDB 11.6.2 (production runs 11.7) a
	//transaction that read a row fails its update with 1020 "Record has changed since last read" when
	//another request changed the row in between (forum#35, forum#102), although the save only writes
	//the fields it was given and nothing gets lost. So a save is no transaction of its own, and it
	//runs in the transaction of the connection, if one is open; explicit transactions keep the check.
	public function save(EntityInterface $entity, array $options = []): EntityInterface|false
	{
		return parent::save($entity, $options + ['atomic' => false]);
	}

	//Nor is a delete: it runs in the transaction of the connection, if one is open.
	public function delete(EntityInterface $entity, array $options = []): bool
	{
		return parent::delete($entity, $options + ['atomic' => false]);
	}

	//Fills created and modified the way Cake 2 does, with the time of PHP rather than of the database.
	public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
	{
		$now = new DateTimeImmutable();
		if ($entity->isNew() && !$entity->has('created') && $this->getSchema()->hasColumn('created'))
			$entity->set('created', $now);
		if (!$entity->isDirty('modified') && $this->getSchema()->hasColumn('modified'))
			$entity->set('modified', $now);
	}
}
