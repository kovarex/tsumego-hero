<?php

//The addresses the collections answer on: one per action, and a short one per collection.
class SetsRoutesTest extends CakeTestCase
{
	//An action is reached by its own address, not read as the name of a collection
	public function testEveryActionKeepsItsAddress(): void
	{
		$actions = $this->actionNames();
		$this->assertNotEmpty($actions);

		foreach ($actions as $action)
		{
			$params = Router::parse('/sets/' . $action);
			$this->assertSame($action, $params['action'],
				'/sets/' . $action . ' belongs to that action');
			$this->assertArrayNotHasKey('id', $params,
				'/sets/' . $action . ' carries no collection id');
		}
	}

	public function testCollectionIsReachedByItsShortAddress(): void
	{
		$params = Router::parse('/sets/4211');
		$this->assertSame('sets', $params['controller']);
		$this->assertSame('view', $params['action']);
		$this->assertSame('4211', $params['id']);
	}

	//Every public method the sets controller declares is an address
	/** @return array<string> */
	private function actionNames(): array
	{
		$controller = new ReflectionClass('SetsController');
		$actions = [];
		foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
			if ($method->getDeclaringClass()->getName() === $controller->getName())
				$actions[] = $method->getName();
		return $actions;
	}
}
