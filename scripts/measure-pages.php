<?php

//Measures the main pages: the SQL statements and the server time of each, for a guest and for signed-in users.
//Run from the project root, against whatever database config/database.php points at:
//  php scripts/measure-pages.php [--runs=5] [--users=ID,ID] [--slowest] [--json] [/path ...]
//--slowest lists the 5 slowest statements of each page under it.
//Each render runs in its own PHP process, as a request does, with the opcache kept in a file cache between
//them, so the times are of warm requests without the compile cost of a cold command line. The first run of
//each page only warms the caches and is not counted. The table gives the median of the other runs.
//The renders do what a visit of that user does, including its writes (the visited status of a problem).

use App\Application;
use App\Utility\JwtAuth;
use App\Utility\Util;
use Cake\Http\Server;
use Cake\Http\ServerRequestFactory;

$root = getcwd();
if (!is_file($root . '/config/bootstrap.php'))
	exit("run it from the project root\n");

$options = getopt('', ['runs:', 'users:', 'json', 'slowest', 'render:', 'user:'], $restIndex);

if (isset($options['render']))
	exit(renderOnce($root, $options['render'], (int) ($options['user'] ?? 0)));

$runs = max(1, (int) ($options['runs'] ?? 5));
$paths = array_slice($argv, $restIndex);
require $root . '/vendor/autoload.php';
require_once $root . '/config/bootstrap.php';
$userIDs = isset($options['users']) ? array_map('intval', explode(',', $options['users'])) : defaultUsers();
if (empty($paths))
	$paths = defaultPaths($userIDs);

$opcacheDirectory = sys_get_temp_dir() . '/measure-pages-opcache';
if (!is_dir($opcacheDirectory))
	mkdir($opcacheDirectory);
$php = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=1 -d opcache.file_cache=' . escapeshellarg($opcacheDirectory)
	. ' -d opcache.file_cache_only=1 -d opcache.validate_timestamps=1 ' . escapeshellarg(__FILE__);

$rows = [];
foreach ($paths as $path)
	foreach (array_merge([0], $userIDs) as $userID)
	{
		$results = [];
		for ($run = 0; $run <= $runs; $run++)
		{
			$output = shell_exec($php . ' --render=' . escapeshellarg($path) . ' --user=' . $userID . ' 2>/dev/null');
			$result = json_decode((string) $output, true);
			if (!is_array($result))
			{
				$results = [['status' => 'failed', 'ms' => 0, 'statements' => 0, 'sqlMs' => 0, 'kilobytes' => 0]];
				break;
			}
			if ($run > 0)
				$results[] = $result;
		}
		$rows[] = [
			'path' => $path,
			'user' => $userID ?: 'guest',
			'status' => $results[0]['status'],
			'ms' => median(array_column($results, 'ms')),
			'statements' => median(array_column($results, 'statements')),
			'sqlMs' => median(array_column($results, 'sqlMs')),
			'kilobytes' => median(array_column($results, 'kilobytes')),
			'slowest' => end($results)['slowest'] ?? [],
		];
	}

if (isset($options['json']))
	exit(json_encode($rows, JSON_PRETTY_PRINT) . "\n");
printf("%-34s %-6s %6s %9s %11s %8s %8s\n", 'page', 'user', 'status', 'total ms', 'statements', 'SQL ms', 'HTML KB');
foreach ($rows as $row)
{
	printf("%-34s %-6s %6s %9.1f %11d %8.1f %8.1f\n", $row['path'], $row['user'], $row['status'], $row['ms'], $row['statements'], $row['sqlMs'], $row['kilobytes']);
	if (isset($options['slowest']))
		foreach ($row['slowest'] as $statement)
			echo '        ', $statement, "\n";
}
printf("median of %d warm runs each; SQL ms is the database server's time of the statements (of the first 100)\n", $runs);

function median(array $values): float
{
	sort($values);
	$count = count($values);
	return $count % 2 ? (float) $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
}

//The player with the most attempts and a player with few, as the heavy and the light case
function defaultUsers(): array
{
	$heavy = Util::query('SELECT user_id FROM tsumego_attempt GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1');
	$light = Util::query('SELECT id FROM user WHERE deleted IS NULL ORDER BY id LIMIT 1');
	return array_values(array_unique(array_map('intval', array_merge(array_column($heavy, 'user_id'), array_column($light, 'id')))));
}

//The main pages, on the largest public collection and a problem in it
function defaultPaths(array $userIDs): array
{
	$set = Util::query('SELECT sc.set_id, MIN(sc.id) AS set_connection_id FROM set_connection sc JOIN `set` s ON s.id = sc.set_id'
		. ' WHERE s.public = 1 AND s.deleted IS NULL GROUP BY sc.set_id ORDER BY COUNT(*) DESC LIMIT 1')[0];
	return [
		'/',
		'/' . $set['set_connection_id'],
		'/sets',
		'/sets/' . $set['set_id'],
		'/users/view/' . ($userIDs[0] ?? 1),
		'/users/highscore',
		'/users/time_mode',
		'/mistake-training',
	];
}

//One request as the site gets it, in this process; prints what it took as JSON
function renderOnce(string $root, string $path, int $userID): string
{
	$_SERVER = array_merge($_SERVER, [
		'REQUEST_METHOD' => 'GET',
		'REQUEST_URI' => $path,
		'HTTP_HOST' => 'tsumego.com',
		'SERVER_NAME' => 'tsumego.com',
		'HTTPS' => 'on',
		'HTTP_ACCEPT' => 'text/html',
	]);
	$start = microtime(true);
	require $root . '/vendor/autoload.php';
	$server = new Server(new Application($root . '/config'));
	//the configuration and the database connection, which Server::run() would load first (require_once)
	require_once $root . '/config/bootstrap.php';

	$pdo = Util::connection();
	$_COOKIE = [];
	if ($userID)
	{
		$passwordHash = $pdo->query('SELECT password_hash FROM user WHERE id = ' . $userID)->fetchColumn();
		$_COOKIE['auth_token'] = JwtAuth::createToken($userID, null, $passwordHash ?: null);
	}
	$request = ServerRequestFactory::fromGlobals();
	$questions = fn() => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1];
	$pdo->exec('SET profiling_history_size = 100');
	$pdo->exec('SET profiling = 1');
	$questionsBefore = $questions();
	$setupMs = (microtime(true) - $start) * 1000;

	$start = microtime(true);
	ob_start();
	$response = $server->run($request);
	$body = ob_get_clean() . $response->getBody();
	$ms = (microtime(true) - $start) * 1000 + $setupMs;

	//the counter counts its own statement too
	$statements = $questions() - $questionsBefore - 1;
	$pdo->exec('SET profiling = 0');
	$profiles = $pdo->query('SHOW PROFILES')->fetchAll(PDO::FETCH_ASSOC);
	$profiles = array_slice($profiles, 1);
	$sqlMs = array_sum(array_map(fn($profile) => (float) $profile['Duration'], $profiles)) * 1000;
	usort($profiles, fn($a, $b) => $b['Duration'] <=> $a['Duration']);
	return json_encode([
		'status' => $response->getStatusCode(),
		'ms' => round($ms, 1),
		'statements' => $statements,
		'sqlMs' => round($sqlMs, 2),
		'kilobytes' => round(strlen($body) / 1024, 1),
		'slowest' => array_map(fn($profile) => sprintf('%7.2f ms  %s', $profile['Duration'] * 1000, substr(preg_replace('/\s+/', ' ', $profile['Query']), 0, 200)), array_slice($profiles, 0, 5)),
	]);
}
