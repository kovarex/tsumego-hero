<?php

namespace App\Middleware;

use App\Database\TimedStatement;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

//Every answer says what it took in its Server-Timing header: the SQL statements, their count and
//time, and the whole of the request from the start of PHP. The browser's developer tools show it,
//and the play page sends it with its timings (webroot/js/PlayTiming.js).
class ServerTimingMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		TimedStatement::reset();
		$response = $handler->handle($request);
		return $response->withHeader('Server-Timing', self::header());
	}

	public static function header(): string
	{
		$start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
		return sprintf('sql;dur=%.1f;desc="%d statements", app;dur=%.1f',
			TimedStatement::$seconds * 1000, TimedStatement::$count, (microtime(true) - $start) * 1000);
	}
}
