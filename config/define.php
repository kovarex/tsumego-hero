<?php

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

if (!defined('APP_DIR')) {
    define('APP_DIR', 'src');
}

if (!defined('WEBROOT_DIR')) {
    define('WEBROOT_DIR', 'webroot');
}

if (!defined('WWW_ROOT')) {
    define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
}

if (!defined('VENDORS')) {
    define('VENDORS', ROOT . DS . 'vendor' . DS);
}
