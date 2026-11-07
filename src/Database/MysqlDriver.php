<?php

namespace App\Database;

use Cake\Database\Driver\Mysql;
use PDO;

//The Mysql driver of Cake 5 with its PDO in reach of Util::query() and Util::execute(), which run
//the site's own SQL on the connection of the ORM, inside its transactions.
class MysqlDriver extends Mysql
{
	public function pdo(): PDO
	{
		return $this->getPdo();
	}
}
