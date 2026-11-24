<?php

namespace App\Database;

use App\Utility\RequestTiming;
use PDOStatement;

//A statement of the site's database connection, which adds the time it ran for to the request's (RequestTiming).
//MysqlDriver gives its PDO this class, so the queries of the ORM and of Util::query() are timed alike. The
//results are buffered, so execute() is the whole round trip to the database.
class TimedStatement extends PDOStatement
{
	public function execute(?array $params = null): bool
	{
		$start = hrtime(true);
		try
		{
			return parent::execute($params);
		}
		finally
		{
			RequestTiming::query($this->queryString, hrtime(true) - $start);
		}
	}
}
