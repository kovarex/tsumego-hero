<?php

//PHPStan bootstrap: the paths and constants of the app and the global functions of Cake 5
//(__(), h(), env() ...), without the app's bootstrap, which reads the server's core.local.php.

require __DIR__ . '/paths.php';
require CAKE . 'Core' . DS . 'functions_global.php';
require CAKE . 'I18n' . DS . 'functions_global.php';
require __DIR__ . '/define.php';

if (!defined('CRON_SECRET'))
	define('CRON_SECRET', 'example');
