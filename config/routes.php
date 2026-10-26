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
require_once(__DIR__ . "/../src/ParseOnlyRoute.php");
require_once(__DIR__ . "/../src/SetViewRoute.php");

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
 * The board editor, see EditorController
 */
Router::connect('/editor', ['controller' => 'editor', 'action' => 'index']);
Router::connect('/editor/', ['controller' => 'editor', 'action' => 'index']);

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
 * Messages between players, see MessagesController
 */
Router::connect(
	'/messages',
	['controller' => 'Messages', 'action' => 'index']
);
Router::connect(
	'/messages/conversations',
	['controller' => 'Messages', 'action' => 'conversations', '[method]' => 'GET']
);
Router::connect(
	'/messages/conversations/:partnerUserId',
	['controller' => 'Messages', 'action' => 'conversation', '[method]' => 'GET'],
	['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
);
Router::connect(
	'/messages/conversations/:partnerUserId',
	['controller' => 'Messages', 'action' => 'send', '[method]' => 'POST'],
	['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
);
Router::connect(
	'/messages/conversations/:partnerUserId/read',
	['controller' => 'Messages', 'action' => 'read'],
	['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
);
Router::connect(
	'/messages/recipients',
	['controller' => 'Messages', 'action' => 'recipients']
);
Router::connect(
	'/messages/:partnerUserId',
	['controller' => 'Messages', 'action' => 'index'],
	['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
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
Router::connect('/roles', ['controller' => 'Roles', 'action' => 'index']);
Router::connect(
	'/roles/change/:id',
	['controller' => 'Roles', 'action' => 'change'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of the user
);
/**
 * The admin area, see AdminController
 */
Router::connect('/admin', ['controller' => 'Admin', 'action' => 'index']);
Router::connect('/admin/activity', ['controller' => 'Admin', 'action' => 'activity']);
Router::connect('/admin/sgf-proposals', ['controller' => 'Admin', 'action' => 'sgfProposals']);
Router::connect('/admin/sgf-changes', ['controller' => 'Admin', 'action' => 'sgfChanges']);
Router::connect(
	'/admin/sgf-changes/review/:id',
	['controller' => 'Admin', 'action' => 'reviewSgfChange'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of the sgf
);
Router::connect('/admin/tag-proposals', ['controller' => 'Admin', 'action' => 'tagProposals']);
Router::connect('/admin/new-tags', ['controller' => 'Admin', 'action' => 'newTags']);
Router::connect('/admin/deleted-accounts', ['controller' => 'Admin', 'action' => 'deletedAccounts']);
Router::connect('/trash', ['controller' => 'Trash', 'action' => 'index']);
Router::connect(
	'/trash/restore-problem/:id',
	['controller' => 'Trash', 'action' => 'restoreProblem'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of the tsumego
);
Router::connect(
	'/trash/restore-set/:id',
	['controller' => 'Trash', 'action' => 'restoreSet'],
	['pass' => ['id'], 'id' => '[0-9]+']
);

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
// The old /sets/view/<id> addresses keep answering for links written before the short ones, and the
// short /sets/<id> ones decline an id that names an action, which the default routes then serve.
Router::connect(
	'/sets/view/:id',
	['controller' => 'sets', 'action' => 'view'],
	['pass' => ['id'], 'id' => '[^/]+', 'routeClass' => 'ParseOnlyRoute']
);
Router::connect(
	'/sets/view/:id/:partition',
	['controller' => 'sets', 'action' => 'view'],
	['pass' => ['id', 'partition'], 'id' => '[^/]+', 'partition' => '[0-9]+', 'routeClass' => 'ParseOnlyRoute']
);
Router::connect(
	'/sets/:id',
	['controller' => 'sets', 'action' => 'view'],
	['pass' => ['id'], 'id' => '[^/]+', 'routeClass' => 'SetViewRoute']
);
Router::connect(
	'/sets/:id/:partition',
	['controller' => 'sets', 'action' => 'view'],
	['pass' => ['id', 'partition'], 'id' => '[^/]+', 'partition' => '[0-9]+', 'routeClass' => 'SetViewRoute']
);
/**
 * Challenges between players, see ChallengesController. The address of a challenge is the link
 * its players send to the ones who should join.
 */
Router::connect(
	'/challenges',
	['controller' => 'Challenges', 'action' => 'index']
);
Router::connect(
	'/challenges/create',
	['controller' => 'Challenges', 'action' => 'create']
);
Router::connect(
	'/challenges/:id',
	['controller' => 'Challenges', 'action' => 'view'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
foreach (['join' => 'join', 'decline' => 'decline', 'invite' => 'invite', 'play' => 'play'] as $path => $challengeAction)
	Router::connect(
		'/challenges/' . $path . '/:id',
		['controller' => 'Challenges', 'action' => $challengeAction],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of a challenge
	);
/**
 * Classes of a teacher, see ClassroomsController. The address of a class is the link the teacher
 * sends to the players who should ask to join.
 */
Router::connect(
	'/classes',
	['controller' => 'Classrooms', 'action' => 'index']
);
Router::connect(
	'/classes/create',
	['controller' => 'Classrooms', 'action' => 'create']
);
Router::connect(
	'/classes/collections',
	['controller' => 'Classrooms', 'action' => 'collections']
);
Router::connect(
	'/classes/collection-problems/:id',
	['controller' => 'Classrooms', 'action' => 'collectionProblems'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of a collection
);
Router::connect(
	'/classes/:id',
	['controller' => 'Classrooms', 'action' => 'view'],
	['pass' => ['id'], 'id' => '[0-9]+']
);
foreach (['invite' => 'invite', 'join' => 'join', 'create-assignment' => 'createAssignment', 'delete' => 'delete'] as $path => $classroomAction)
	Router::connect(
		'/classes/' . $path . '/:id',
		['controller' => 'Classrooms', 'action' => $classroomAction],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of a class
	);
foreach (['accept-member' => 'acceptMember', 'remove-member' => 'removeMember'] as $path => $classroomAction)
	Router::connect(
		'/classes/' . $path . '/:id',
		['controller' => 'Classrooms', 'action' => $classroomAction],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of a membership
	);
Router::connect(
	'/classes/play/:id/:setConnectionId',
	['controller' => 'Classrooms', 'action' => 'play'],
	['pass' => ['id', 'setConnectionId'], 'id' => '[0-9]+', 'setConnectionId' => '[0-9]+'] // Id of an assignment, then of a problem in it
);
Router::connect(
	'/classes/play/:id',
	['controller' => 'Classrooms', 'action' => 'play'],
	['pass' => ['id'], 'id' => '[0-9]+'] // Id of an assignment
);
foreach (['assignment' => 'assignment', 'save-assignment' => 'saveAssignment', 'delete-assignment' => 'deleteAssignment', 'progress' => 'progress'] as $path => $classroomAction)
	Router::connect(
		'/classes/' . $path . '/:id',
		['controller' => 'Classrooms', 'action' => $classroomAction],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of an assignment
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
	'/users/dateFormatPreference',
	['controller' => 'Users', 'action' => 'dateFormatPreference']
);
Router::connect(
	'/users/emailOnNewMessagePreference',
	['controller' => 'Users', 'action' => 'emailOnNewMessagePreference']
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
