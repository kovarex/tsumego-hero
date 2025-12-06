<?php

/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @package app.Config
 * @since CakePHP(tm) v 0.2.9
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */


require_once(__DIR__ . "/../src/UrlRoute.php");

// AssetCompress routes for dynamically serving built assets
Router::connect('/cache_css/:name', [
	'plugin' => 'AssetCompress',
	'controller' => 'AssetCompress',
	'action' => 'get'
], ['name' => '[a-z0-9\-\.]+']);

Router::connect('/cache_js/:name', [
	'plugin' => 'AssetCompress',
	'controller' => 'AssetCompress',
	'action' => 'get'
], ['name' => '[a-z0-9\-\.]+']);

/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'display', and we pass a param to select the view file
 * to use (in this case, /app/View/Pages/home.ctp)...
 */
Router::connect('/', ['controller' => 'sites', 'action' => 'index'], ['routeClass' => 'UrlRoute']);
/**
 * ...and connect the rest of 'Pages' controller's URLs.
 */
Router::connect('/pages/*', ['controller' => 'pages', 'action' => 'display']);

/**
 * TsumegoComments routes - for managing comments on tsumego problems
 */
Router::connect(
	'/tsumego-comments/add',
	['controller' => 'TsumegoComments', 'action' => 'add']
);
Router::connect(
	'/tsumego-comments/delete/:id',
	['controller' => 'TsumegoComments', 'action' => 'delete'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
// Backward compatibility with old singular URL
Router::connect(
	'/tsumegoComment/add',
	['controller' => 'TsumegoComments', 'action' => 'add']
);

/**
 * TsumegoIssues routes - for managing issues on tsumego problems
 */
Router::connect(
	'/tsumego-issues',
	['controller' => 'TsumegoIssues', 'action' => 'index']
);
Router::connect(
	'/tsumego-issues/create',
	['controller' => 'TsumegoIssues', 'action' => 'create']
);
Router::connect(
	'/tsumego-issues/close/:id',
	['controller' => 'TsumegoIssues', 'action' => 'close'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/tsumego-issues/reopen/:id',
	['controller' => 'TsumegoIssues', 'action' => 'reopen'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/tsumego-issues/move-comment/:id',
	['controller' => 'TsumegoIssues', 'action' => 'moveComment'],
	['pass' => ['id'], 'id' => '[0-9]+']
);

//Router::connect('/*', ['routeClass' => 'UrlRoute']);

/**
 * Load all plugin routes. See the CakePlugin documentation on
 * how to customize the loading of plugin routes.
 */
CakePlugin::routes();

/**
 * Load the CakePHP default routes. Only remove this if you do not want to use
 * the built-in default routes.
 */
require CAKE . 'Config' . DS . 'routes.php';
