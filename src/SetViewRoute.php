<?php

//The short address of a collection: /sets/<id>.
//A bare /sets/<action> address looks exactly the same, and the default routes that answer those
//are loaded after this one. An id that names an action of the controller is therefore left to that
//action, so /sets/delete and /sets/index keep working, and so does every action added later.
//The address is still generated: this is what a collection is linked by, unlike ParseOnlyRoute.
class SetViewRoute extends CakeRoute
{
	//The lowercase action names of each controller, read once
	/** @var array<string, array<string>> */
	protected static $actions = [];

	//$url: URL to match
	/** @return array<string, mixed>|false */
	public function parse($url)
	{
		$params = parent::parse($url);
		if (!$params || !isset($params['id']))
			return $params;
		if (in_array(strtolower((string) $params['id']), self::actions($params['controller']), true))
			return false;
		return $params;
	}

	/**
	 * @param string $controller
	 * @return array<string>
	 */
	protected static function actions($controller)
	{
		$controller = strtolower($controller);
		if (isset(self::$actions[$controller]))
			return self::$actions[$controller];

		self::$actions[$controller] = [];
		$class = Inflector::camelize($controller) . 'Controller';
		if (!class_exists($class))
			return self::$actions[$controller];

		$reflection = new ReflectionClass($class);
		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
			if ($method->getDeclaringClass()->getName() === $reflection->getName())
				self::$actions[$controller][] = strtolower($method->getName());
		return self::$actions[$controller];
	}
}
