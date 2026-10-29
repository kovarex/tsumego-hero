<?php

//The front controller of Cake 5.
if (PHP_SAPI === 'cli-server')
{
	$_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
	$url = parse_url(urldecode($_SERVER['REQUEST_URI']));
	$file = __DIR__ . $url['path'];
	if (strpos($url['path'], '..') === false && strpos($url['path'], '.') !== false && is_file($file))
		return false;
}
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Application;
use Cake\Http\Server;

$server = new Server(new Application(dirname(__DIR__) . '/config'));
$server->emit($server->run());
