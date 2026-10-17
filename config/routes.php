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

use App\Utility\Avatar;

// XML Sitemap for search engines
Router::connect('/sitemap.xml', [
	'controller' => 'Sitemaps',
	'action' => 'index'
]);

/**
 * Connect '/' (base path) to the sites index.
 */
Router::connect('/', ['controller' => 'sites', 'action' => 'index'], ['routeClass' => 'UrlRoute']);

/**
 * Public changelog / "what's new" page
 */
Router::connect('/changelog', ['controller' => 'changelog', 'action' => 'index']);

/**
 * TsumegoComments routes - for managing comments on tsumego problems
 */
Router::connect(
	'/tsumego-comments/index/:tsumegoId',
	['controller' => 'TsumegoComments', 'action' => 'index'],
	['pass' => ['tsumegoId'], 'tsumegoId' => '[0-9]+']
);
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
	'/tsumego-issues/api',
	['controller' => 'TsumegoIssues', 'action' => 'api']
);
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

/**
 * Mistake Training
 */
Router::connect(
	'/mistake-training/play/:setConnectionID',
	['controller' => 'MistakeTraining', 'action' => 'play'],
	['pass' => ['setConnectionID'], 'setConnectionID' => '[0-9]+']
);
Router::connect(
	'/mistake-training/overview',
	['controller' => 'MistakeTraining', 'action' => 'overview']
);
Router::connect(
	'/mistake-training/import',
	['controller' => 'MistakeTraining', 'action' => 'import']
);
Router::connect(
	'/mistake-training',
	['controller' => 'MistakeTraining', 'action' => 'play']
);

/**
 * Tsumego image generation for Open Graph / social sharing
 */
Router::connect(
	'/tsumego-image/:id',
	['controller' => 'TsumegoImages', 'action' => 'tsumegoImage'],
	['pass' => ['id'], 'id' => '[0-9]+'] // ID of the SetConnection
);

/**
 * Board avatars. The url carries the key of the display name, which is what the
 * drawing is made of, so one url serves the avatar in a list and the one on a
 * profile. The png is the same drawing for the pages that share it.
 */
$boardKey = '[0-9a-f]{' . Avatar::KEY_LENGTH . '}';
Router::connect(
	'/board/:key.png',
	['controller' => 'Avatars', 'action' => 'imagePng'],
	['pass' => ['key'], 'key' => $boardKey] // Key of a display name
);
Router::connect(
	'/board/:key',
	['controller' => 'Avatars', 'action' => 'image'],
	['pass' => ['key'], 'key' => $boardKey] // Key of a display name
);

/**
 * The board of an account, addressed by the id of the user. The lists that React
 * draws know the id of every player they show but not the display name the
 * drawing is made of, so this is how an avatar reaches its board when a picture
 * of another host is gone.
 */
Router::connect(
	'/avatar/:id',
	['controller' => 'Avatars', 'action' => 'boardOfUser'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of a user
);

/**
 * User Sets routes
 */
Router::connect(
	'/sets/mine',
	['controller' => 'sets', 'action' => 'mine']
);
Router::connect(
	'/sets/user/:userId',
	['controller' => 'sets', 'action' => 'userSets'],
	['pass' => ['userId']]
);
Router::connect(
	'/sets/delete/:id',
	['controller' => 'sets', 'action' => 'delete'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/sets/edit/:id',
	['controller' => 'sets', 'action' => 'edit'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/sets/share/:id',
	['controller' => 'sets', 'action' => 'share'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/sets/addTsumego/:id',
	['controller' => 'sets', 'action' => 'addTsumego'],
	['pass' => ['id'], 'id' => '[0-9]+|favorites']
);
Router::connect(
	'/sets/createAndAddTsumego/:id',
	['controller' => 'sets', 'action' => 'createAndAddTsumego'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/sets/removeTsumego/:id',
	['controller' => 'sets', 'action' => 'removeTsumego'],
	['pass' => ['id'], 'id' => '[0-9]+|favorites']
);
Router::connect(
	'/sets/reorderTsumego/:id',
	['controller' => 'sets', 'action' => 'reorderTsumego'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
Router::connect(
	'/users/playerColorPreference',
	['controller' => 'Users', 'action' => 'playerColorPreference']
);
Router::connect(
	'/users/boardOrientationPreference',
	['controller' => 'Users', 'action' => 'boardOrientationPreference']
);
Router::connect(
	'/users/avatarPreference',
	['controller' => 'Users', 'action' => 'avatarPreference']
);
Router::connect(
	'/profile',
	['controller' => 'Users', 'action' => 'profile']
);
Router::connect(
	'/me',
	['controller' => 'Users', 'action' => 'profile']
);
Router::connect(
	'/me/solve-history',
	['controller' => 'Users', 'action' => 'meSolveHistory']
);
Router::connect(
	'/me/contributions',
	['controller' => 'Users', 'action' => 'meContributions']
);

//Router::connect('/*', ['routeClass' => 'UrlRoute']);

/**
 * Load the CakePHP default routes. Only remove this if you do not want to use
 * the built-in default routes.
 */
require CAKE . 'Config' . DS . 'routes.php';
