<?php

namespace App\Database;

use App\Utility\RequestTiming;
use Cake\Database\Driver\Mysql;
use PDO;

//The Mysql driver of Cake 5 with its PDO in reach of Util::query() and Util::execute(), which run
//the site's own SQL on the connection of the ORM, inside its transactions. The connection, the
//statements (TimedStatement) and the commits count into the time of the request (RequestTiming).
class MysqlDriver extends Mysql
{
	public function pdo(): PDO
	{
		return $this->getPdo();
	}

	public function connect(): void
	{
		if ($this->pdo !== null)
			return;
		$start = hrtime(true);
		parent::connect();
		RequestTiming::connected(hrtime(true) - $start);
		$this->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [TimedStatement::class]);
	}

	//A commit waits for the database to write the transaction down
	public function commitTransaction(): bool
	{
		$start = hrtime(true);
		try
		{
			return parent::commitTransaction();
		}
		finally
		{
			RequestTiming::query('COMMIT', hrtime(true) - $start);
		}
	}
}
