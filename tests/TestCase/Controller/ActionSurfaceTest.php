<?php

/**
 * The methods a controller exposes.
 *
 * The dispatcher turns every public method into an address unless its name starts with an
 * underscore (Controller::_isPrivateAction), so a public method that asks for an object
 * cannot be an action at all: a request only ever carries strings.
 */
class ActionSurfaceTest extends CakeTestCase
{
	/**
	 * Callbacks the framework calls itself. They are public on purpose and are no actions.
	 */
	private const LIFECYCLE = ['beforeFilter', 'beforeRender', 'afterFilter', 'initialize', 'startupProcess', 'shutdownProcess'];

	/**
	 * A method reachable by address is given the pieces of that address, which are strings. One
	 * that needs an object anywhere in its signature can only end in a TypeError when it is
	 * reached, so it belongs behind a leading underscore or a non-public visibility.
	 */
	public function testNoActionAsksForAnObject(): void
	{
		$offenders = [];
		foreach (glob(APP . 'Controller' . DS . '*.php') as $file)
		{
			$name = basename($file, '.php');
			if (!class_exists($name))
				continue;

			$class = new ReflectionClass($name);
			foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
			{
				if ($method->getDeclaringClass()->getName() !== $name)
					continue;
				if (str_starts_with($method->getName(), '_') || in_array($method->getName(), self::LIFECYCLE, true))
					continue;

				foreach ($method->getParameters() as $parameter)
				{
					$type = $parameter->getType();
					if ($type instanceof ReflectionNamedType && !$type->isBuiltin())
						$offenders[] = $name . '::' . $method->getName() . '($' . $parameter->getName() . ')';
				}
			}
		}

		$this->assertSame([], $offenders, 'these are reachable by address yet take an object');
	}
}
