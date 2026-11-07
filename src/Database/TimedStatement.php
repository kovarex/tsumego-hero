<?php

namespace App\Database;

use PDOStatement;

//The statements of the connection count themselves and their time, for the Server-Timing header of
//the request (App\Middleware\ServerTimingMiddleware). A buffered statement has its rows by the end of
//execute(), so its time is the whole of the database's part.
class TimedStatement extends PDOStatement
{
	public static int $count = 0;
	public static float $seconds = 0.0;

	//PDO creates the statements itself
	protected function __construct() {}

	public function execute(?array $params = null): bool
	{
		$start = hrtime(true);
		try
		{
			return parent::execute($params);
		}
		finally
		{
			self::$count++;
			self::$seconds += (hrtime(true) - $start) / 1e9;
		}
	}

	public static function reset(): void
	{
		self::$count = 0;
		self::$seconds = 0.0;
	}
}
