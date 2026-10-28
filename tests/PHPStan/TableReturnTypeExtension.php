<?php

namespace App\Test\PHPStan;

use Cake\ORM\Table;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

//The methods of XTable that give rows give App\Model\Entity\X, and find() a query of them.
//Another finder than 'all', like find('list'), keeps the plain query: its rows may not be entities.
class TableReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	private const ENTITY_METHODS = ['get', 'newEntity', 'newEmptyEntity'];

	public function __construct(private CakeTables $cakeTables) {}

	public function getClass(): string
	{
		return Table::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), [...self::ENTITY_METHODS, 'find'], true);
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		$entityType = $this->cakeTables->entityType($scope->getType($methodCall->var));
		if ($entityType === null)
			return null;
		if (in_array($methodReflection->getName(), self::ENTITY_METHODS, true))
			return $entityType;
		$finder = $methodCall->getArgs()[0] ?? null;
		if ($finder && array_map(fn($name) => $name->getValue(), $scope->getType($finder->value)->getConstantStrings()) !== ['all'])
			return null;
		return $this->cakeTables->queryType($entityType);
	}
}
