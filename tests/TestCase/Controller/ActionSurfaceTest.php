<?php

//The methods a controller exposes.
//The dispatcher turns every public method into an address unless its name starts with an
//underscore (Controller::_isPrivateAction), so the action surface is decided by naming alone.
//Every action states which HTTP verbs it accepts, and nothing reachable by address may ask for
//an object: a request only ever carries strings.
class ActionSurfaceTest extends CakeTestCase
{
	//Callbacks the framework calls itself. They are public on purpose and are no actions.
	private const LIFECYCLE = ['beforeFilter', 'beforeRender', 'afterFilter', 'initialize', 'startupProcess', 'shutdownProcess'];

	//The attributes AppController::_enforceHttpMethodAttribute() acts on.
	private const VERBS = [
		'App\Attribute\HttpGet',
		'App\Attribute\HttpPost',
		'App\Attribute\HttpPut',
		'App\Attribute\HttpPatch',
		'App\Attribute\HttpDelete',
	];

	//A request to an address only carries strings, and the verb attribute decides whether the
	//request may run an action at all.
	public function testEveryActionDeclaresItsHttpVerbs(): void
	{
		$offenders = [];
		foreach ($this->actions() as $action)
			if ($this->declaredVerbs($action) === [])
				$offenders[] = $this->name($action);

		$this->assertSame([], $offenders, 'these are reachable by address yet declare no HTTP verb');
	}

	//An attribute that is not imported resolves to the global namespace and the enforcement
	//skips it, which would silently leave the action open to every verb.
	public function testEveryVerbIsAnAttributeOfThisApp(): void
	{
		$offenders = [];
		foreach ($this->actions() as $action)
			foreach ($this->declaredVerbs($action) as $verb)
				if (!in_array($verb, self::VERBS, true))
					$offenders[] = $this->name($action) . ' uses ' . $verb;

		$this->assertSame([], $offenders, 'these verbs do not resolve to App\Attribute, so they are not imported');
	}

	//A verb on a method the dispatcher never reaches describes a rule nobody reads, and it
	//makes the next reader believe the method is an address.
	public function testOnlyActionsDeclareHttpVerbs(): void
	{
		$offenders = [];
		foreach ($this->controllerClasses() as $className)
			foreach ((new ReflectionClass($className))->getMethods() as $method)
				if (!$this->isAction($className, $method) && $this->declaredVerbs($method) !== [])
					$offenders[] = $this->name($method);

		$this->assertSame([], $offenders, 'these declare an HTTP verb yet are not reachable by address');
	}

	//A method reachable by address is given the pieces of that address, which are strings. One
	//that needs an object anywhere in its signature can only end in a TypeError when it is
	//reached, so it belongs behind a leading underscore or a non-public visibility.
	public function testNoActionAsksForAnObject(): void
	{
		$offenders = [];
		foreach ($this->actions() as $action)
			foreach ($action->getParameters() as $parameter)
				if ($this->isObject($parameter))
					$offenders[] = $this->name($action) . '($' . $parameter->getName() . ')';

		$this->assertSame([], $offenders, 'these are reachable by address yet take an object');
	}

	private function name(ReflectionMethod $method): string
	{
		return $method->getDeclaringClass()->getName() . '::' . $method->getName();
	}

	/** @return array<string> */
	private function controllerClasses(): array
	{
		$classes = [];
		foreach (glob(APP . 'Controller' . DS . '*.php') as $file)
		{
			$name = basename($file, '.php');
			if (!class_exists($name) || (new ReflectionClass($name))->isAbstract())
				continue;

			$classes[] = $name;
		}

		return $classes;
	}

	//Every method the dispatcher would answer to, worked out the way
	//Controller::_isPrivateAction() does it: public, not leading-underscored, and not already
	//part of the framework controller.
	/** @return array<ReflectionMethod> */
	private function actions(): array
	{
		$actions = [];
		foreach ($this->controllerClasses() as $className)
			foreach ((new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
				if ($this->isAction($className, $method))
					$actions[] = $method;

		return $actions;
	}

	//Whether the dispatcher would answer to the method, worked out the way
	//Controller::_isPrivateAction() does it: public, not leading-underscored, and not already part
	//of the framework controller.
	private function isAction(string $className, ReflectionMethod $method): bool
	{
		if (!$method->isPublic() || str_starts_with($method->getName(), '_'))
			return false;
		if (in_array($method->getName(), self::LIFECYCLE, true))
			return false;

		$added = array_diff(get_class_methods($className), get_class_methods('Controller'));
		return in_array($method->getName(), $added, true);
	}

	/** @return array<string> the verb attributes the action declares, by resolved class name */
	private function declaredVerbs(ReflectionMethod $method): array
	{
		$verbs = [];
		foreach ($method->getAttributes() as $attribute)
			if ($this->isVerb($attribute->getName()))
				$verbs[] = $attribute->getName();

		return $verbs;
	}

	//Whether a resolved attribute name is one of the verb attributes, imported or not.
	private function isVerb(string $attributeName): bool
	{
		return (bool) preg_match('/^(.*\\\\)?Http(Get|Post|Put|Patch|Delete)$/', $attributeName);
	}

	private function isObject(ReflectionParameter $parameter): bool
	{
		$type = $parameter->getType();
		return $type instanceof ReflectionNamedType && !$type->isBuiltin();
	}
}
