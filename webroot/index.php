<?php

/**
 * The Front Controller for handling every request
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       app.webroot
 * @since         CakePHP(tm) v 0.2.9
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Composer\InstalledVersions;

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

if (!is_file(dirname(__DIR__) . DS . 'config' . DS . 'define.php')) {
	trigger_error(
		'Configuration file "config' . DS . 'define.php" could not be loaded. '
		. 'Please ensure this file exists and is readable.',
		E_USER_ERROR,
	);
}
require_once dirname(__DIR__) . DS . 'config' . DS . 'define.php';

// For the built-in server
if (PHP_SAPI === 'cli-server') {
	if ($_SERVER['PHP_SELF'] !== '/' . basename(__FILE__) && file_exists(WWW_ROOT . $_SERVER['PHP_SELF'])) {
		return false;
	}
	$_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
}
$_SERVER['PHP_SELF'] = '/' . basename(__FILE__);

if (!is_dir(VENDORS)) {
	trigger_error(
		'Composer vendors directory not found at "' . VENDORS . '". '
		. 'Please run "composer install" in the project root directory to install dependencies.',
		E_USER_ERROR,
	);
}
if (!is_file(VENDORS . 'autoload.php')) {
	trigger_error(
		'Composer autoload file not found at "' . VENDORS . 'autoload.php". '
		. 'Please run "composer install" to generate the autoload file.',
		E_USER_ERROR,
	);
}
require_once VENDORS . 'autoload.php';

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'src' . DS . 'Cake' . DS . 'bootstrap.php';

// Fix for PHP 8.x: env('argv') returns array which violates return type string|bool|null
unset($_SERVER['argv'], $_SERVER['argc']);

$dispatcher = new Dispatcher();
$dispatcher->dispatch(
	new CakeRequest(),
	new CakeResponse(),
);
