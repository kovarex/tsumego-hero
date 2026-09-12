<?php

/**
 * Generates src/Utility/RowTypes.php with one row type alias for every application table.
 *
 * Usage (inside ddev): ddev exec php scripts/generate-phpstan-row-types.php
 *
 * Reads the current development database schema (information_schema) and emits
 * `{CamelCaseTableName}Row` type aliases usable in PHPDoc, e.g. in a class docblock:
 *   @phpstan-import-type TsumegoStatusRow from \App\Utility\RowTypes
 * and then:
 *   @param TsumegoStatusRow $row
 *
 * Both PHPStan and Intelephense understand @phpstan-type/@phpstan-import-type.
 *
 * Column type mapping: int types emit `int|string`, decimal/float/double emit `int|float|string`,
 * everything else `string`, plus `|null` for nullable columns. The unions are intentional sound
 * supersets - actual runtime types on PHP 8.4 + mysqlnd are int for integer columns, float for
 * float/double and string for decimal/text/date types (verify with
 * scripts/check-pdo-column-types.php). Every column is marked optional (`?:`) because queries
 * frequently select subsets of columns.
 *
 * Forum (phpbb_*) tables, phinxlog and cake_sessions are skipped - they are not
 * queried from the application code.
 */

$pdo = new PDO(
	sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'db', getenv('DB_NAME') ?: 'db'),
	getenv('DB_USER') ?: 'db',
	getenv('DB_PASS') ?: 'db',
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = "SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS d, IS_NULLABLE AS n
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME NOT LIKE 'phpbb\_%'
          AND TABLE_NAME NOT IN ('phinxlog', 'cake_sessions')
        ORDER BY TABLE_NAME, ORDINAL_POSITION";

$tables = [];
foreach ($pdo->query($sql) as $row)
	$tables[$row['t']][] = $row;

$intTypes = ['int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'year'];
$floatTypes = ['decimal', 'float', 'double'];

$aliases = [];
foreach ($tables as $table => $columns)
{
	$alias = '';
	foreach (explode('_', $table) as $part)
		$alias .= ucfirst($part);
	$alias .= 'Row';

	$shape = [];
	foreach ($columns as $column)
	{
		$type = 'string';
		if (in_array($column['d'], $intTypes, true))
			$type = 'int|string';
		elseif (in_array($column['d'], $floatTypes, true))
			$type = 'int|float|string';
		if ($column['n'] === 'YES')
			$type .= '|null';
		$shape[] = $column['c'] . '?: ' . $type;
	}
	$aliases[$alias] = implode(', ', $shape) . ', ...';
}

ksort($aliases);

$out = "<?php\n\n";
$out .= "namespace App\\Utility;\n\n";
$out .= "/**\n";
$out .= " * Row type aliases generated from the database schema. Do not edit manually.\n";
$out .= " * Regenerate after schema changes: ddev exec php scripts/generate-phpstan-row-types.php\n";
$out .= " *\n";
foreach ($aliases as $alias => $shape)
	$out .= " * @phpstan-type " . $alias . " array{" . $shape . "}\n";
$out .= " */\n";
$out .= "class RowTypes {}\n";

file_put_contents(__DIR__ . '/../src/Utility/RowTypes.php', $out);
echo 'Generated ' . count($aliases) . " row type aliases into src/Utility/RowTypes.php\n";
