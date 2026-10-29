<?php

namespace App;

use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

//The application of Cake 5: what the Dispatcher and its filters of Cake 2 did.
class Application extends BaseApplication
{
	public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
	{
		return $middlewareQueue
			->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
			->add(new AssetMiddleware(['cacheTime' => Configure::read('Asset.cacheTime')]))
			->add(new RoutingMiddleware($this))
			->add(new BodyParserMiddleware())
			//The forms and the React code post data[Model][field], which Cake 2 read as Model.field
			->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
			{
				$body = $request->getParsedBody();
				if (is_array($body) && isset($body['data']) && is_array($body['data']))
				{
					$data = $body['data'];
					unset($body['data']);
					$request = $request->withParsedBody(array_replace_recursive($body, $data));
				}
				return $handler->handle($request);
			});
	}
}
