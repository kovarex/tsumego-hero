<?php

namespace App\Database;

use App\Utility\Util;
use Cake\Database\Driver\Mysql;
use PDO;

//The MySQL driver of the Cake 5 ORM, running on the PDO connection of Cake 2 instead of opening its own.
//While the models move to Cake 5 one by one, both ORMs share the connection: a transaction Cake 2 starts
//covers what Cake 5 saves, and the tests' database is used by both.
class SharedConnectionDriver extends Mysql
{
	public function connect(): void {}

	//The connection belongs to Cake 2, which closes it.
	public function disconnect(): void {}

	public function isConnected(): bool
	{
		return true;
	}

	protected function getPdo(): PDO
	{
		return Util::connection();
	}
}
