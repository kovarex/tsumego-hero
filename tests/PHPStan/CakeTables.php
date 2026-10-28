<?php

namespace App\Test\PHPStan;

use Cake\ORM\Query\SelectQuery;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

//How our Cake 5 tables and entities are named: the table X is App\Model\Table\XTable, its rows
//are App\Model\Entity\X. Shared by the return type extensions.
class CakeTables
{
	public function __construct(private ReflectionProvider $reflectionProvider) {}

	//The table class of fetchTable('X'), null when there is none.
	public function tableType(Type $nameType): ?Type
	{
		$names = $nameType->getConstantStrings();
		if (count($names) !== 1)
			return null;
		$class = 'App\\Model\\Table\\' . $names[0]->getValue() . 'Table';
		return $this->reflectionProvider->hasClass($class) ? new ObjectType($class) : null;
	}

	//The entity class of the rows of a table type, null when it isn't one of ours.
	public function entityType(Type $tableType): ?Type
	{
		foreach ($tableType->getObjectClassNames() as $tableClass)
			if (preg_match('/^App\\\\Model\\\\Table\\\\(\w+)Table$/', $tableClass, $match))
			{
				$entityClass = 'App\\Model\\Entity\\' . $match[1];
				return $this->reflectionProvider->hasClass($entityClass) ? new ObjectType($entityClass) : null;
			}
		return null;
	}

	public function queryType(Type $entityType): Type
	{
		return new GenericObjectType(SelectQuery::class, [$entityType]);
	}
}
