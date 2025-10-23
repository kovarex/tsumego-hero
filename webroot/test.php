<?php

/**
 * Web Access Frontend for TestSuite
 *
 * CakePHP(tm) Tests <https://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://book.cakephp.org/2.0/en/development/testing.html
 * @package       app.webroot
 * @since         CakePHP(tm) v 1.2.0.4433
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Composer\InstalledVersions;

set_time_limit(0);
ini_set('display_errors', 1);

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

if (Configure::read('debug') < 1) {
	throw new NotFoundException(__d('cake_dev', 'Debug setting does not allow access to this URL.'));
}
CakeTestSuiteDispatcher::run();
