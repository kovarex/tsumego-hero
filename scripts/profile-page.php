<?php
// One-off diagnostic, run from the project root on the server:
//   php profile-page.php /5058 USER_ID [USER_ID ...]
// Renders the page the way the site does for each user (without sending anything), and prints how long
// it took and every SQL statement of it with its time, the slowest first. It does what a visit of that
// user does, including the writes a visit makes (the visited status of the problem). Delete it after use.

use App\Application;
use App\Utility\JwtAuth;
use App\Utility\Util;
use Cake\Http\Server;
use Cake\Http\ServerRequestFactory;

if ($argc < 3)
	exit("usage: php profile-page.php /PATH USER_ID [USER_ID ...]\n");
$path = $argv[1];
$userIds = array_map('intval', array_slice($argv, 2));

$root = getcwd(); // the project root, where it is run from
require $root . '/vendor/autoload.php';

foreach ($userIds as $index => $userId)
{
	if ($index > 0)
	{
		// one user per process: the app keeps state for the request in static fields
		passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($path) . ' ' . $userId);
		continue;
	}
	$_SERVER = array_merge($_SERVER, [
		'REQUEST_METHOD' => 'GET',
		'REQUEST_URI' => $path,
		'HTTP_HOST' => 'tsumego.com',
		'SERVER_NAME' => 'tsumego.com',
		'HTTPS' => 'on',
		'HTTP_ACCEPT' => 'text/html',
	]);
	$server = new Server(new Application($root . '/config'));
	// the configuration and the database connection, which Server::run() would load first (require_once)
	require_once $root . '/config/bootstrap.php';

	$pdo = Util::connection();
	$user = $pdo->query('SELECT id, name, password_hash FROM user WHERE id = ' . $userId)->fetch(PDO::FETCH_ASSOC);
	if (!$user)
		exit("user $userId not found\n");
	$_COOKIE = ['auth_token' => JwtAuth::createToken($userId, null, $user['password_hash'])];
	$request = ServerRequestFactory::fromGlobals();

	$pdo->exec('SET profiling_history_size = 100');
	$pdo->exec('SET profiling = 1');
	$start = microtime(true);
	ob_start();
	$response = $server->run($request);
	ob_end_clean();
	$total = (microtime(true) - $start) * 1000;
	$pdo->exec('SET profiling = 0');
	$profiles = $pdo->query('SHOW PROFILES')->fetchAll(PDO::FETCH_ASSOC);

	$sqlTotal = array_sum(array_map(fn($p) => (float) $p['Duration'], $profiles)) * 1000;
	printf("== %s as user %d (%s): HTTP %d, %.1f ms in total, %d statements taking %.1f ms on the database server\n",
		$path, $userId, $user['name'], $response->getStatusCode(), $total, count($profiles), $sqlTotal);
	usort($profiles, fn($a, $b) => $b['Duration'] <=> $a['Duration']);
	foreach (array_slice($profiles, 0, 15) as $profile)
		printf("  %7.2f ms  %s\n", $profile['Duration'] * 1000, substr(preg_replace('/\s+/', ' ', $profile['Query']), 0, 160));
	if (count($profiles) >= 100)
		echo "  (only the first 100 statements are kept by the database)\n";
}
