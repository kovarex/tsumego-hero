<?php

use Cake\Http\ServerRequest;
use Cake\Routing\Router;

//Every address the site answers on keeps reaching the same action, and the code keeps making the
//same addresses. The expectations are the plain data of RouteTable.php; the adapter at the end of
//this class is all that speaks to the router, so when the routes move to Cake 5 only it changes and
//the table checks the new routes as it is.
class RoutesTest extends CakeTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadRoutes();
	}

	/** @return array<string, array{string, array{string, string, array<?string>}}> */
	public static function parseCases(): array
	{
		$cases = [];
		foreach (self::table()['parse'] as $request => $expected)
			$cases[$request] = [$request, $expected];
		return $cases;
	}

	/** @return array<string, array{?string, array<int|string, mixed>|string, string}> */
	public static function urlCases(): array
	{
		$cases = [];
		foreach (self::table()['url'] as [$context, $url, $expected])
			$cases[$expected . ' from ' . json_encode($url)] = [$context, $url, $expected];
		return $cases;
	}

	//The address reaches its controller and action with the passed parameters, and no named ones:
	//nothing on the site reads named parameters, and Cake 5 has none.
	/**
	 * @dataProvider parseCases
	 * @param array{string, string, array<?string>} $expected
	 */
	public function testAddressReachesItsAction(string $request, array $expected): void
	{
		[$method, $path] = str_contains($request, ' ') ? explode(' ', $request, 2) : ['GET', $request];
		[$controller, $action, $pass] = $expected;
		$this->assertSame(
			['controller' => $controller, 'action' => $action, 'pass' => $pass, 'named' => []],
			self::parse($method, $path));
	}

	/**
	 * @dataProvider urlCases
	 * @param array<int|string, mixed>|string $url
	 */
	public function testCodeMakesTheSameAddress(?string $context, array|string $url, string $expected): void
	{
		$this->assertSame($expected, self::url($url, $context));
	}

	//A route added to routes.php needs its address in the table
	public function testEveryRouteHasAnAddressInTheTable(): void
	{
		$this->assertSame([], self::routesReachedByNone(array_keys(self::table()['parse'])));
	}

	//An action added to a controller needs its address in the table
	public function testEveryActionHasAnAddressInTheTable(): void
	{
		$missing = [];
		foreach (self::actions() as $controller => $actions)
			foreach ($actions as $action)
				if (!isset(self::table()['parse']['/' . lcfirst($controller) . '/' . $action]))
					$missing[] = '/' . lcfirst($controller) . '/' . $action;
		$this->assertSame([], $missing);
	}

	/** @return array{parse: array<string, array{string, string, array<?string>}>, url: array<array{?string, array<int|string, mixed>|string, string}>} */
	private static function table(): array
	{
		return require __DIR__ . '/RouteTable.php';
	}

	//The adapter: the only part of the test that speaks to the router, here of Cake 5.

	//The controller, action, passed and named parameters the router gives the address. The controller
	//is the class name it dispatches to, without Controller.
	/** @return array{controller: string, action: string, pass: array<?string>, named: array<string, string>} */
	private static function parse(string $method, string $path): array
	{
		$params = Router::parseRequest(new ServerRequest(['url' => $path, 'environment' => ['REQUEST_METHOD' => $method]]));
		return [
			'controller' => $params['controller'],
			'action' => $params['action'],
			'pass' => $params['pass'],
			'named' => [],
		];
	}

	//$context: The controller of the request the address is made in, for an array without one
	/** @param array<int|string, mixed>|string $url */
	private static function url(array|string $url, ?string $context): string
	{
		$params = ['plugin' => null, 'controller' => $context, 'action' => 'index', 'pass' => []];
		Router::setRequest(new ServerRequest(['url' => '/' . ($context ?? ''), 'params' => $params]));
		return Router::url($url);
	}

	//The templates of the routes that none of the addresses reaches.
	/**
	 * @param array<string> $requests
	 * @return array<string>
	 */
	private static function routesReachedByNone(array $requests): array
	{
		$unreached = [];
		foreach (Router::getRouteCollection()->routes() as $route)
		{
			$reached = false;
			foreach ($requests as $request)
			{
				[$method, $path] = str_contains($request, ' ') ? explode(' ', $request, 2) : ['GET', $request];
				if ($route->parse($path, $method) !== null)
				{
					$reached = true;
					break;
				}
			}
			if (!$reached)
				$unreached[] = $route->template;
		}
		return $unreached;
	}

	//The actions of every controller, by the controller's name without Controller.
	/** @return array<string, array<string>> */
	private static function actions(): array
	{
		$actions = [];
		foreach (glob(APP . 'Controller' . DS . '*Controller.php') as $file)
		{
			$name = basename($file, '.php');
			$class = 'App\\Controller\\' . $name;
			if (in_array($name, ['AppController', 'ErrorController'], true) || !class_exists($class))
				continue;
			$controller = substr($name, 0, -strlen('Controller'));
			$actions[$controller] = [];
			foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
				if ($method->getDeclaringClass()->getName() === $class && !str_starts_with($method->getName(), '_')
					&& !in_array($method->getName(), ['beforeFilter', 'beforeRender', 'afterFilter', 'initialize'], true))
						$actions[$controller][] = $method->getName();
		}
		return $actions;
	}
}
