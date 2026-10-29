<?php

//Checked by phpstan (phpstan.neon analyses tests/PHPStan): the types tests/PHPStan/extension.neon gives
//the Cake 5 tables. assertType() fails the analysis when a type changes, and the ignored line fails it
//when an unknown field of an entity stops being reported.

use App\Model\Entity\Reject;
use App\Model\Table\RejectTable;
use Cake\ORM\TableRegistry;

use function PHPStan\Testing\assertType;

class TableTypesFixture
{
	use Cake\ORM\Locator\LocatorAwareTrait;

	public function tables(): void
	{
		assertType(RejectTable::class, $this->fetchTable('Reject'));
		assertType(RejectTable::class, TableRegistry::getTableLocator()->get('Reject'));
		assertType('Cake\ORM\Table', $this->fetchTable('NoSuchTable'));
	}

	public function entities(RejectTable $table): void
	{
		assertType(Reject::class, $table->get(1));
		assertType(Reject::class, $table->newEntity([]));
		assertType(Reject::class, $table->saveOrFail($table->newEntity([])));
		assertType('App\Model\Entity\Reject|null', $table->find()->where(['id' => 1])->orderBy('id')->first());
		assertType(Reject::class, $table->find()->firstOrFail());
		assertType('array<int, App\Model\Entity\Reject>', $table->find()->toArray());
		foreach ($table->find()->all() as $reject)
			assertType(Reject::class, $reject);
		assertType('array<Cake\Datasource\EntityInterface>', $table->find('list')->toArray());
		assertType('array|Cake\Datasource\EntityInterface|null', $table->find()->disableHydration()->first());
	}

	public function unknownField(RejectTable $table): mixed
	{
		return $table->get(1)->no_such_field; // @phpstan-ignore property.notFound
	}
}
