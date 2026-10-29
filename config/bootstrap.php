<?php

use App\Utility\Auth;
use App\Utility\Util;

if (Util::isInGithubCI())
	Configure::write('App.fullBaseUrl', 'https://host.docker.internal:8443');

// Fix Chrome POST losing REQUEST_URI behind Apache rewrite
if (empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['REDIRECT_URL']))
{
	// Remove the internal `/webroot` prefix
	$_SERVER['REQUEST_URI'] = preg_replace('#^/webroot#', '', $_SERVER['REDIRECT_URL']);
}


/**
 * This file is loaded automatically by the app/webroot/index.php file after core.php
 *
 * This file should load/create any application wide configuration settings, such as
 * Caching, Logging, loading additional configuration files.
 *
 * You should also use this file to include any files that provide global functions/constants
 * that your application uses.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @package app.Config
 * @since CakePHP(tm) v 0.10.8.2117
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
require 'functions.php';

//The caches of the app run on Cake 5 (Cake\Cache\Cache), in the files and with the lifetimes they had on
//Cake 2. Cake 2 keeps its own caches, _cake_core_ and _cake_model_ (see core.php).
// Setup a 'default' cache configuration for use in the application.
Cake\Cache\Cache::setConfig('default', ['className' => Cake\Cache\Engine\FileEngine::class, 'path' => CACHE]);

// Long-lived cache for data that changes infrequently (e.g. sitemap)
Cake\Cache\Cache::setConfig('long', [
	'className' => Cake\Cache\Engine\FileEngine::class,
	'path' => CACHE,
	'duration' => '+1 day',
	'prefix' => 'long_',
]);

//The Cake 5 ORM, which the models move to one by one, on the connection of Cake 2 (see SharedConnectionDriver).
//Its schema descriptions are cached like those of Cake 2 (see _cake_model_ in core.php).
//Its Configure is its own, the namespace is where fetchTable() looks for the table classes.
Cake\Core\Configure::write('App.namespace', 'App');
Cake\Cache\Cache::setConfig('_cake_model_', [
	'className' => Cake\Cache\Engine\FileEngine::class,
	'prefix' => 'cake5_model_',
	'path' => CACHE . 'models' . DS,
	'duration' => Configure::read('debug') > 0 ? '+10 seconds' : '+999 days',
]);
if (Configure::read('Cache.disable'))
	Cake\Cache\Cache::disable();
Cake\Datasource\ConnectionManager::setConfig('default', [
	'className' => Cake\Database\Connection::class,
	'driver' => App\Database\SharedConnectionDriver::class,
	'cacheMetadata' => true,
	// the columns like time_mode_attempt.order and the tables like set are reserved words of SQL
	'quoteIdentifiers' => true,
]);

/**
 * Custom Inflector rules can be set to correctly pluralize or singularize table, model, controller names or whatever other
 * string is passed to the inflection functions
 *
 * Inflector::rules('singular', array('rules' => array(), 'irregular' => array(), 'uninflected' => array()));
 * Inflector::rules('plural', array('rules' => array(), 'irregular' => array(), 'uninflected' => array()));
 */

/**
 * To prefer app translation over plugin translation, you can set
 *
 * Configure::write('I18n.preferApp', true);
 */

/**
 * You can attach event listeners to the request lifecycle as Dispatcher Filter. By default CakePHP bundles two filters:
 *
 * - AssetDispatcher filter will serve your asset files (css, images, js, etc) from your themes and plugins
 * - CacheDispatcher filter will read the Cache.check configure variable and try to serve cached content generated from controllers
 *
 * Feel free to remove or add filters as you see fit for your application. A few examples:
 *
 * Configure::write('Dispatcher.filters', array(
 *		'MyCacheFilter', // will use MyCacheFilter class from the Routing/Filter package in your app.
 *		'MyCacheFilter' => array('prefix' => 'my_cache_'), // will use MyCacheFilter class from the Routing/Filter package in your app with settings array.
 *		'MyPlugin.MyFilter', // will use MyFilter class from the Routing/Filter package in MyPlugin plugin.
 *		array('callable' => $aFunction, 'on' => 'before', 'priority' => 9), // A valid PHP callback type to be called on beforeDispatch
 *		array('callable' => $anotherMethod, 'on' => 'after'), // A valid PHP callback type to be called on afterDispatch
 *
 * ));
 */
Configure::write('Dispatcher.filters', [
	'AssetDispatcher',
	'CacheDispatcher',
]);

/**
 * Configures default file logging options
 */
//The app logs through Cake 5 (Cake\Log\Log) into the same files as Cake 2, which logs its own errors
//through the CakeLog configuration below.
Cake\Log\Log::setConfig('debug', [
	'className' => Cake\Log\Engine\FileLog::class,
	'formatter' => App\Log\LogLineFormatter::class,
	'path' => LOGS,
	'levels' => ['notice', 'info', 'debug'],
	'file' => 'debug',
]);
Cake\Log\Log::setConfig('error', [
	'className' => Cake\Log\Engine\FileLog::class,
	'formatter' => App\Log\LogLineFormatter::class,
	'path' => LOGS,
	'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
	'file' => 'error',
]);
CakeLog::config('debug', [
	'engine' => 'File',
	'types' => ['notice', 'info', 'debug'],
	'file' => 'debug',
]);
CakeLog::config('error', [
	'engine' => 'File',
	'types' => ['warning', 'error', 'critical', 'alert', 'emergency'],
	'file' => 'error',
]);
