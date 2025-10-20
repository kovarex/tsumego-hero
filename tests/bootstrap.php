<?php
/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', dirname(__DIR__));
define('VENDORS', ROOT . DS . 'vendor' . DS);

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';
