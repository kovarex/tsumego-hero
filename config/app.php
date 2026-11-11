<?php

use App\Cache\Engine\OpcacheFileEngine;
use Cake\Cache\Engine\FileEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Log\Engine\FileLog;
use App\Log\LogLineFormatter;

//The configuration of the app, what core.php, database.php and the configuration part of
//bootstrap.php did on Cake 2. Each server overrides it in its config/core.local.php and
//config/database.php, which config/bootstrap.php reads after it. The secrets are only in those
//files and in config/jwt_secret.php, none of which are in git.
$debug = !empty($_SERVER['SERVER_NAME']) && str_contains($_SERVER['SERVER_NAME'], '.ddev.site');
$modelCacheDuration = $debug ? '+10 seconds' : '+999 days';
return [
	'debug' => $debug,
	'App' => [
		'namespace' => 'App',
		'encoding' => 'UTF-8',
		'defaultLocale' => 'en_US',
		'defaultTimezone' => 'UTC',
		'base' => false,
		'dir' => 'src',
		'webroot' => 'webroot',
		'wwwRoot' => WWW_ROOT,
		'fullBaseUrl' => false,
		'imageBaseUrl' => 'img/',
		'cssBaseUrl' => 'css/',
		'jsBaseUrl' => 'js/',
		'paths' => [
			'plugins' => [ROOT . DS . 'plugins' . DS],
			'templates' => [ROOT . DS . 'templates' . DS],
			'locales' => [RESOURCES . 'locales' . DS],
		],
	],
	'Asset' => [],
	'Cache' => [
		'default' => ['className' => FileEngine::class, 'path' => CACHE],
		'long' => ['className' => FileEngine::class, 'path' => CACHE, 'duration' => '+1 day', 'prefix' => 'long_'],
		'short' => ['className' => FileEngine::class, 'path' => CACHE, 'duration' => '+5 minutes', 'prefix' => 'short_'],
		'_cake_translations_' => [
			'className' => FileEngine::class,
			'prefix' => 'myapp_cake_translations_',
			'path' => CACHE . 'persistent' . DS,
			'serialize' => true,
			'duration' => $modelCacheDuration,
		],
		//the table schemas, read by every request: from the opcache instead of the disk
		'_cake_model_' => [
			'className' => OpcacheFileEngine::class,
			'prefix' => 'cake5_model_',
			'path' => CACHE . 'models' . DS,
			'duration' => $modelCacheDuration,
		],
	],
	'Error' => [
		'exceptionRenderer' => App\Error\AppExceptionRenderer::class,
		'errorLevel' => E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE & ~E_USER_DEPRECATED & ~E_USER_WARNING & ~E_USER_NOTICE,
		'skipLog' => [],
		'log' => false,
		'trace' => true,
		'ignoredDeprecationPaths' => [],
	],
	'Debugger' => ['editor' => 'phpstorm'],
	//mail() with a DKIM signature once the server's core.local.php sets EmailTransport.default.dkim
	//(see App\Mailer\Transport\DkimMailTransport), unsigned without it.
	'EmailTransport' => ['default' => ['className' => 'DkimMail']],
	//Quoted-printable keeps the body 7-bit, so no relay re-encodes it and breaks the DKIM body hash.
	'Email' => ['default' => ['transport' => 'default', 'from' => ['no-reply@tsumego.com' => 'Tsumego Hero'], 'transferEncoding' => 'quoted-printable']],
	'Datasources' => [
		'default' => [
			'className' => Connection::class,
			'driver' => App\Database\MysqlDriver::class,
			'persistent' => false,
			'host' => '127.0.0.1',
			'username' => 'root',
			'password' => 'root',
			'database' => 'test',
			'encoding' => 'utf8mb4',
			'timezone' => 'UTC',
			'cacheMetadata' => true,
			// the columns like time_mode_attempt.order and the tables like set are reserved words of SQL
			'quoteIdentifiers' => true,
		],
		'test' => [
			'className' => Connection::class,
			'driver' => App\Database\MysqlDriver::class,
			'persistent' => false,
			'host' => '127.0.0.1',
			'username' => 'root',
			'password' => 'root',
			'database' => 'test',
			'encoding' => 'utf8mb4',
			'timezone' => 'UTC',
			'cacheMetadata' => true,
			'quoteIdentifiers' => true,
		],
	],
	'Log' => [
		'debug' => ['className' => FileLog::class, 'formatter' => LogLineFormatter::class, 'path' => LOGS, 'file' => 'debug', 'levels' => ['notice', 'info', 'debug']],
		'error' => ['className' => FileLog::class, 'formatter' => LogLineFormatter::class, 'path' => LOGS, 'file' => 'error', 'levels' => ['warning', 'error', 'critical', 'alert', 'emergency']],
	],
	'Session' => ['defaults' => 'php'],
];
