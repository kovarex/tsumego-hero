<?php

namespace App\Routing\Route;

use Cake\Routing\Route\Route;
use Cake\Utility\Inflector;
use ReflectionClass;
use ReflectionMethod;

//The short address of a collection: /sets/<id>.
//A bare /sets/<action> address looks exactly the same, and the default routes that answer those
//are loaded after this one. An id that names an action of the controller is therefore left to that
//action, so /sets/delete and /sets/index keep working, and so does every action added later.
//The address is still generated: this is what a collection is linked by, unlike ParseOnlyRoute.
class SetViewRoute extends Route
{
	//The lowercase action names of each controller, read once
	/** @var array<string, array<string>> */
	protected static array $actions = [];

	public function parse(string $url, string $method): ?array
	{
		$params = parent::parse($url, $method);
		if (!$params || !isset($params['id']))
			return $params;
		if (in_array(strtolower((string) $params['id']), self::actions($params['controller']), true))
			return null;
		return $params;
	}

	/** @return array<string> */
	protected static function actions(string $controller): array
	{
		$controller = strtolower($controller);
		if (isset(self::$actions[$controller]))
			return self::$actions[$controller];

		self::$actions[$controller] = [];
		$class = 'App\\Controller\\' . Inflector::camelize($controller) . 'Controller';
		if (!class_exists($class))
			return self::$actions[$controller];

		$reflection = new ReflectionClass($class);
		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
			if ($method->getDeclaringClass()->getName() === $reflection->getName())
				self::$actions[$controller][] = strtolower($method->getName());
		return self::$actions[$controller];
	}
}
