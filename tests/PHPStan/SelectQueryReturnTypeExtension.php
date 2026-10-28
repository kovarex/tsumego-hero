<?php

namespace App\Test\PHPStan;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Query\SelectQuery;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

//A query of entities, as TableReturnTypeExtension types find(), gives them from first(),
//firstOrFail(), toArray() and all(); cakephp/orm 5.2 declares them as mixed. disableHydration() makes
//it a query of arrays, which stay untyped.
class SelectQueryReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return SelectQuery::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['first', 'firstOrFail', 'toArray', 'all', 'disableHydration'], true);
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		if ($methodReflection->getName() === 'disableHydration')
			return new ObjectType(SelectQuery::class);
		$entityType = $scope->getType($methodCall->var)->getTemplateType(SelectQuery::class, 'TSubject');
		if (!(new ObjectType(EntityInterface::class))->isSuperTypeOf($entityType)->yes() || $entityType->getObjectClassNames() === [EntityInterface::class])
			return null;
		return match ($methodReflection->getName())
		{
			'first' => TypeCombinator::addNull($entityType),
			'firstOrFail' => $entityType,
			'all' => new GenericObjectType(ResultSetInterface::class, [new IntegerType(), $entityType]),
			default => new ArrayType(new IntegerType(), $entityType),
		};
	}
}
