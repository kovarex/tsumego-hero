<?php

namespace App\Test\PHPStan;

use Cake\ORM\Locator\LocatorInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

//TableRegistry::getTableLocator()->get('X') gives App\Model\Table\XTable.
class TableLocatorReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function __construct(private CakeTables $cakeTables) {}

	public function getClass(): string
	{
		return LocatorInterface::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'get';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		$arguments = $methodCall->getArgs();
		return isset($arguments[0]) ? $this->cakeTables->tableType($scope->getType($arguments[0]->value)) : null;
	}
}
