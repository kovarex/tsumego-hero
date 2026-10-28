<?php

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use DateTimeImmutable;

//The base of the Cake 5 tables, doing what AppModel does for the Cake 2 models.
class AppTable extends Table
{
	//A save is no transaction of its own, see AppModel::save().
	public function save(EntityInterface $entity, array $options = []): EntityInterface|false
	{
		return parent::save($entity, $options + ['atomic' => false]);
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
