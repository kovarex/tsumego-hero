<?php

namespace App\Middleware;

use App\Utility\RequestTiming;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

//The first middleware, so what it measures (RequestTiming) covers all the others, the error pages among them
class RequestTimingMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		RequestTiming::begin();
		return RequestTiming::finish($handler->handle($request), $request->getMethod());
	}
}
