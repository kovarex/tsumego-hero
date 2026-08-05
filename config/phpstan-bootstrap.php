<?php

/**
 * PHPStan bootstrap. Defines CakePHP constants that are normally set by
 * webroot/index.php. Does NOT load the full CakePHP bootstrap because
 * core.local.php triggers Cache::config() which requires the framework.
 */

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT')) {
	define('ROOT', dirname(__DIR__));
}
if (!defined('TMP')) {
	define('TMP', sys_get_temp_dir() . DS);
}
if (!defined('CACHE')) {
	define('CACHE', TMP . 'cache' . DS);
}
if (!defined('LOGS')) {
	define('LOGS', TMP . 'logs' . DS);
}

require_once __DIR__ . '/define.php';

// Minimal config from core.local.php (skip Cache::config which needs CakePHP)
if (!defined('CRON_SECRET')) {
	define('CRON_SECRET', 'example');
}
