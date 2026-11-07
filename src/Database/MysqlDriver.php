<?php

namespace App\Database;

use Cake\Database\Driver\Mysql;
use PDO;

//The Mysql driver of Cake 5 with its PDO in reach of Util::query() and Util::execute(), which run
//the site's own SQL on the connection of the ORM, inside its transactions. Its statements time
//themselves (TimedStatement).
class MysqlDriver extends Mysql
{
	public function connect(): void
	{
		if ($this->pdo !== null)
			return;
		parent::connect();
		$this->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [TimedStatement::class]);
	}

	public function pdo(): PDO
	{
		return $this->getPdo();
	}
}
