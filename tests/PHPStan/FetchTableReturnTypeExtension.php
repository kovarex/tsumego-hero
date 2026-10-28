<?php

namespace App\Test\PHPStan;

use Cake\ORM\Locator\LocatorAwareTrait;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Type;

//$this->fetchTable('X') gives App\Model\Table\XTable in every class using LocatorAwareTrait. A
//dynamic return type extension can't be registered for a trait, so this resolves the expression.
class FetchTableReturnTypeExtension implements ExpressionTypeResolverExtension
{
	public function __construct(private CakeTables $cakeTables) {}

	public function getType(Expr $expr, Scope $scope): ?Type
	{
		if (!$expr instanceof MethodCall || !$expr->name instanceof Identifier || $expr->name->toString() !== 'fetchTable')
			return null;
		$arguments = $expr->getArgs();
		if (!isset($arguments[0]))
			return null;
		foreach ($scope->getType($expr->var)->getObjectClassReflections() as $classReflection)
			if ($classReflection->hasTraitUse(LocatorAwareTrait::class))
				return $this->cakeTables->tableType($scope->getType($arguments[0]->value));
		return null;
	}
}
