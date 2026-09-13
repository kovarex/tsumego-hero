<?php

/**
 * Verifies which PHP types the application's PDO connection returns for each column type
 * present in the database schema. Companion to scripts/generate-phpstan-row-types.php -
 * documents the runtime types the generated row aliases are based on.
 *
 * Usage (inside ddev): ddev exec php scripts/check-pdo-column-types.php
 *
 * The connection options below mirror the Mysql driver in vendor/pieceofcake2/cakephp
 * (config/database.php defines no additional 'flags'), so this is exactly the runtime
 * path of Util::query() and model finds.
 *
 * Expected result on PHP 8.1+ with mysqlnd (verified on PHP 8.4 + MariaDB 11.4):
 *   - integer columns (int/bigint/mediumint/smallint/tinyint) -> integer
 *   - decimal -> string (exact text representation, e.g. '94.00')
 *   - float/double -> double
 *   - date/datetime/timestamp and all text types -> string
 * The type aliases in src/Utility/RowTypes.php use these exact types: `int` for integer
 * columns, `float` for float/double, `string` for decimal and date/text types.
 */
$pdo = new PDO(
	'mysql:host=db;dbname=db;charset=utf8',
	'db',
	'db',
	[
		PDO::ATTR_PERSISTENT => false,
		PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]
);
echo 'EMULATE_PREPARES=' . var_export($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES), true) . "\n";
echo 'STRINGIFY_FETCHES=' . var_export($pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES), true) . "\n";

$checks = [
	'int (achievement.id)' => 'SELECT id FROM achievement LIMIT 1',
	'tinyint (user.isAdmin)' => 'SELECT isAdmin FROM user LIMIT 1',
	'bigint (phinxlog.version)' => 'SELECT version FROM phinxlog LIMIT 1',
	'decimal (time_mode_attempt.points)' => 'SELECT points FROM time_mode_attempt LIMIT 1',
	'double (achievement_condition.value)' => 'SELECT value FROM achievement_condition LIMIT 1',
	'float (`set`.multiplier)' => 'SELECT multiplier FROM `set` LIMIT 1',
	'date (day_record.date)' => 'SELECT date FROM day_record LIMIT 1',
	'datetime (achievement.created)' => 'SELECT created FROM achievement LIMIT 1',
	'timestamp (activate.created)' => 'SELECT created FROM activate LIMIT 1',
	'varchar (achievement.additionalDescription)' => 'SELECT additionalDescription FROM achievement LIMIT 1',
	'text (admin_activity.new_value)' => 'SELECT new_value FROM admin_activity LIMIT 1',
];

foreach ($checks as $label => $sql)
{
	try
	{
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row === false)
		{
			echo str_pad($label, 52) . " -> (no rows)\n";
			continue;
		}
		$key = array_key_first($row);
		echo str_pad($label, 52) . ' -> ' . gettype($row[$key]) . ' : ' . var_export($row[$key], true) . "\n";
	}
	catch (PDOException $e)
	{
		echo str_pad($label, 52) . ' -> ERROR: ' . $e->getMessage() . "\n";
	}
}
