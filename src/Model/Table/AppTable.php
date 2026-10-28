<?php

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use DateTimeImmutable;

//The base of the Cake 5 tables, doing what AppModel does for the Cake 2 models.
//The names are singular like the database's: TsumegoAttemptTable is the table tsumego_attempt, its rows
//are TsumegoAttempt entities and fetchTable('TsumegoAttempt') gives it, all without configuration.
class AppTable extends Table
{
	//A save is no transaction of its own, see AppModel::save().
	public function save(EntityInterface $entity, array $options = []): EntityInterface|false
	{
		return parent::save($entity, $options + ['atomic' => false]);
	}

	//Fills created and modified the way Cake 2 does, with the time of PHP rather than of the database.
	//updated too: Cake 2 writes back the updated it read (so the column's ON UPDATE doesn't move it)
	//and fills in the time only when the row has none.
	public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
	{
		$now = new DateTimeImmutable();
		if ($entity->isNew() && !$entity->has('created') && $this->getSchema()->hasColumn('created'))
			$entity->set('created', $now);
		if (!$entity->isDirty('modified') && $this->getSchema()->hasColumn('modified'))
			$entity->set('modified', $now);
		if ($this->getSchema()->hasColumn('updated'))
			if ($entity->has('updated'))
				$entity->setDirty('updated', true);
			else
				$entity->set('updated', $now);
	}
}
