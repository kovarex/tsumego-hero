<?php

use App\Routing\Route\ParseOnlyRoute;
use App\Routing\Route\SetViewRoute;
use App\Routing\Route\UrlRoute;
use App\Utility\Avatar;
use App\Utility\UploadedAvatar;
use Cake\Routing\Route\InflectedRoute;
use Cake\Routing\RouteBuilder;

//The routes of the site, Router::connect() of Cake 2 on the RouteBuilder of Cake 5.
return function (RouteBuilder $routes): void
{

	// XML Sitemap for search engines
	$routes->connect('/sitemap.xml', [
		'controller' => 'Sitemaps',
		'action' => 'index'
	]);

	//Connect '/' (base path) to the sites index.
	$routes->connect('/', ['controller' => 'Sites', 'action' => 'index'], ['routeClass' => UrlRoute::class]);

	//The board editor, see EditorController
	$routes->connect('/editor', ['controller' => 'Editor', 'action' => 'index']);
	$routes->connect('/editor/', ['controller' => 'Editor', 'action' => 'index']);

	//Public changelog / "what's new" page
	$routes->connect('/changelog', ['controller' => 'Changelog', 'action' => 'index']);

	//TsumegoComments routes - for managing comments on tsumego problems
	$routes->connect(
		'/tsumego-comments/index/{tsumegoId}',
		['controller' => 'TsumegoComments', 'action' => 'index'],
		['pass' => ['tsumegoId'], 'tsumegoId' => '[0-9]+']
	);
	$routes->connect(
		'/tsumego-comments/add',
		['controller' => 'TsumegoComments', 'action' => 'add']
	);
	$routes->connect(
		'/tsumego-comments/delete/{id}',
		['controller' => 'TsumegoComments', 'action' => 'delete'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	// Backward compatibility with old singular URL
	$routes->connect(
		'/tsumegoComment/add',
		['controller' => 'TsumegoComments', 'action' => 'add']
	);

	//TsumegoIssues routes - for managing issues on tsumego problems
	$routes->connect(
		'/tsumego-issues/api',
		['controller' => 'TsumegoIssues', 'action' => 'api']
	);
	$routes->connect(
		'/tsumego-issues',
		['controller' => 'TsumegoIssues', 'action' => 'index']
	);
	$routes->connect(
		'/tsumego-issues/create',
		['controller' => 'TsumegoIssues', 'action' => 'create']
	);
	$routes->connect(
		'/tsumego-issues/close/{id}',
		['controller' => 'TsumegoIssues', 'action' => 'close'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/tsumego-issues/reopen/{id}',
		['controller' => 'TsumegoIssues', 'action' => 'reopen'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/tsumego-issues/move-comment/{id}',
		['controller' => 'TsumegoIssues', 'action' => 'moveComment'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);

	//Messages between players, see MessagesController
	$routes->connect(
		'/messages',
		['controller' => 'Messages', 'action' => 'index']
	);
	$routes->connect(
		'/messages/conversations',
		['controller' => 'Messages', 'action' => 'conversations', '[method]' => 'GET']
	);
	$routes->connect(
		'/messages/conversations/{partnerUserId}',
		['controller' => 'Messages', 'action' => 'conversation', '[method]' => 'GET'],
		['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
	);
	$routes->connect(
		'/messages/conversations/{partnerUserId}',
		['controller' => 'Messages', 'action' => 'send', '[method]' => 'POST'],
		['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
	);
	$routes->connect(
		'/messages/conversations/{partnerUserId}/read',
		['controller' => 'Messages', 'action' => 'read'],
		['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
	);
	$routes->connect(
		'/messages/recipients',
		['controller' => 'Messages', 'action' => 'recipients']
	);
	$routes->connect(
		'/messages/{partnerUserId}',
		['controller' => 'Messages', 'action' => 'index'],
		['pass' => ['partnerUserId'], 'partnerUserId' => '[0-9]+']
	);

	//Mistake Training
	$routes->connect(
		'/mistake-training/play/{setConnectionID}',
		['controller' => 'MistakeTraining', 'action' => 'play'],
		['pass' => ['setConnectionID'], 'setConnectionID' => '[0-9]+']
	);
	$routes->connect(
		'/mistake-training/overview',
		['controller' => 'MistakeTraining', 'action' => 'overview']
	);
	$routes->connect(
		'/mistake-training/import',
		['controller' => 'MistakeTraining', 'action' => 'import']
	);
	$routes->connect(
		'/mistake-training',
		['controller' => 'MistakeTraining', 'action' => 'play']
	);

	//Tsumego image generation for Open Graph / social sharing
	$routes->connect(
		'/tsumego-image/{id}',
		['controller' => 'TsumegoImages', 'action' => 'tsumegoImage'],
		['pass' => ['id'], 'id' => '[0-9]+'] // ID of the SetConnection
	);

	//Board avatars. The url carries the key of the display name, which is what the
	//drawing is made of, so one url serves the avatar in a list and the one on a
	//profile. The png is the same drawing for the pages that share it.
	$boardKey = '[0-9a-f]{' . Avatar::KEY_LENGTH . '}';
	$routes->connect(
		'/board/{key}.png',
		['controller' => 'Avatars', 'action' => 'imagePng'],
		['pass' => ['key'], 'key' => $boardKey] // Key of a display name
	);
	$routes->connect(
		'/board/{key}',
		['controller' => 'Avatars', 'action' => 'image'],
		['pass' => ['key'], 'key' => $boardKey] // Key of a display name
	);

	//The board of an account, addressed by the id of the user. The lists that React
	//draws know the id of every player they show but not the display name the
	//drawing is made of, so this is how an avatar reaches its board when a picture
	//of another host is gone.
	$routes->connect(
		'/avatar/{id}',
		['controller' => 'Avatars', 'action' => 'boardOfUser'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of a user
	);

	//The picture a player uploaded as their avatar, stored in the database. The hash
	//changes with the picture, so the url can be cached for good.
	$routes->connect(
		'/avatar/{id}/{hash}.webp',
		['controller' => 'Avatars', 'action' => 'picture'],
		['pass' => ['id', 'hash'], 'id' => '[0-9]+', 'hash' => '[0-9a-f]{' . UploadedAvatar::HASH_LENGTH . '}'] // Id of a user, hash of their picture
	);

	//User Sets routes
	$routes->connect('/roles', ['controller' => 'Roles', 'action' => 'index']);
	$routes->connect(
		'/roles/change/{id}',
		['controller' => 'Roles', 'action' => 'change'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of the user
	);
	//The admin area, see AdminController
	$routes->connect('/admin', ['controller' => 'Admin', 'action' => 'index']);
	$routes->connect('/admin/activity', ['controller' => 'Admin', 'action' => 'activity']);
	$routes->connect('/admin/sgf-proposals', ['controller' => 'Admin', 'action' => 'sgfProposals']);
	$routes->connect('/admin/sgf-changes', ['controller' => 'Admin', 'action' => 'sgfChanges']);
	$routes->connect(
		'/admin/sgf-changes/review/{id}',
		['controller' => 'Admin', 'action' => 'reviewSgfChange'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of the sgf
	);
	$routes->connect('/admin/tag-proposals', ['controller' => 'Admin', 'action' => 'tagProposals']);
	$routes->connect('/admin/new-tags', ['controller' => 'Admin', 'action' => 'newTags']);
	$routes->connect('/admin/deleted-accounts', ['controller' => 'Admin', 'action' => 'deletedAccounts']);
	$routes->connect('/trash', ['controller' => 'Trash', 'action' => 'index']);
	$routes->connect(
		'/trash/restore-problem/{id}',
		['controller' => 'Trash', 'action' => 'restoreProblem'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of the tsumego
	);
	$routes->connect(
		'/trash/restore-set/{id}',
		['controller' => 'Trash', 'action' => 'restoreSet'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);

	$routes->connect(
		'/sets/mine',
		['controller' => 'Sets', 'action' => 'mine']
	);
	$routes->connect(
		'/sets/user/{userId}',
		['controller' => 'Sets', 'action' => 'userSets'],
		['pass' => ['userId']]
	);
	$routes->connect(
		'/sets/delete/{id}',
		['controller' => 'Sets', 'action' => 'delete'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/sets/edit/{id}',
		['controller' => 'Sets', 'action' => 'edit'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/sets/share/{id}',
		['controller' => 'Sets', 'action' => 'share'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/sets/addTsumego/{id}',
		['controller' => 'Sets', 'action' => 'addTsumego'],
		['pass' => ['id'], 'id' => '[0-9]+|favorites']
	);
	$routes->connect(
		'/sets/createAndAddTsumego/{id}',
		['controller' => 'Sets', 'action' => 'createAndAddTsumego'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	$routes->connect(
		'/sets/removeTsumego/{id}',
		['controller' => 'Sets', 'action' => 'removeTsumego'],
		['pass' => ['id'], 'id' => '[0-9]+|favorites']
	);
	$routes->connect(
		'/sets/reorderTsumego/{id}',
		['controller' => 'Sets', 'action' => 'reorderTsumego'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	// The old /sets/view/<id> addresses keep answering for links written before the short ones, and the
	// short /sets/<id> ones decline an id that names an action, which the default routes then serve.
	$routes->connect(
		'/sets/view/{id}',
		['controller' => 'Sets', 'action' => 'view'],
		['pass' => ['id'], 'id' => '[^/]+', 'routeClass' => ParseOnlyRoute::class]
	);
	$routes->connect(
		'/sets/view/{id}/{partition}',
		['controller' => 'Sets', 'action' => 'view'],
		['pass' => ['id', 'partition'], 'id' => '[^/]+', 'partition' => '[0-9]+', 'routeClass' => ParseOnlyRoute::class]
	);
	$routes->connect(
		'/sets/{id}',
		['controller' => 'Sets', 'action' => 'view'],
		['pass' => ['id'], 'id' => '[^/]+', 'routeClass' => SetViewRoute::class]
	);
	$routes->connect(
		'/sets/{id}/{partition}',
		['controller' => 'Sets', 'action' => 'view'],
		['pass' => ['id', 'partition'], 'id' => '[^/]+', 'partition' => '[0-9]+', 'routeClass' => SetViewRoute::class]
	);
	//Challenges between players, see ChallengesController. The address of a challenge is the link
	//its players send to the ones who should join.
	$routes->connect(
		'/challenges',
		['controller' => 'Challenges', 'action' => 'index']
	);
	$routes->connect(
		'/challenges/create',
		['controller' => 'Challenges', 'action' => 'create']
	);
	$routes->connect(
		'/challenges/{id}',
		['controller' => 'Challenges', 'action' => 'view'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	foreach (['join' => 'join', 'decline' => 'decline', 'invite' => 'invite', 'play' => 'play'] as $path => $challengeAction)
		$routes->connect(
			'/challenges/' . $path . '/{id}',
			['controller' => 'Challenges', 'action' => $challengeAction],
			['pass' => ['id'], 'id' => '[0-9]+'] // Id of a challenge
		);
	//Classes of a teacher, see ClassroomsController. The address of a class is the link the teacher
	//sends to the players who should ask to join.
	$routes->connect(
		'/classes',
		['controller' => 'Classrooms', 'action' => 'index']
	);
	$routes->connect(
		'/classes/create',
		['controller' => 'Classrooms', 'action' => 'create']
	);
	$routes->connect(
		'/classes/collections',
		['controller' => 'Classrooms', 'action' => 'collections']
	);
	$routes->connect(
		'/classes/collection-problems/{id}',
		['controller' => 'Classrooms', 'action' => 'collectionProblems'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of a collection
	);
	$routes->connect(
		'/classes/{id}',
		['controller' => 'Classrooms', 'action' => 'view'],
		['pass' => ['id'], 'id' => '[0-9]+']
	);
	foreach (['invite' => 'invite', 'join' => 'join', 'create-assignment' => 'createAssignment', 'delete' => 'delete'] as $path => $classroomAction)
		$routes->connect(
			'/classes/' . $path . '/{id}',
			['controller' => 'Classrooms', 'action' => $classroomAction],
			['pass' => ['id'], 'id' => '[0-9]+'] // Id of a class
		);
	foreach (['accept-member' => 'acceptMember', 'remove-member' => 'removeMember'] as $path => $classroomAction)
		$routes->connect(
			'/classes/' . $path . '/{id}',
			['controller' => 'Classrooms', 'action' => $classroomAction],
			['pass' => ['id'], 'id' => '[0-9]+'] // Id of a membership
		);
	$routes->connect(
		'/classes/play/{id}/{setConnectionId}',
		['controller' => 'Classrooms', 'action' => 'play'],
		['pass' => ['id', 'setConnectionId'], 'id' => '[0-9]+', 'setConnectionId' => '[0-9]+'] // Id of an assignment, then of a problem in it
	);
	$routes->connect(
		'/classes/play/{id}',
		['controller' => 'Classrooms', 'action' => 'play'],
		['pass' => ['id'], 'id' => '[0-9]+'] // Id of an assignment
	);
	foreach (['assignment' => 'assignment', 'save-assignment' => 'saveAssignment', 'delete-assignment' => 'deleteAssignment', 'progress' => 'progress'] as $path => $classroomAction)
		$routes->connect(
			'/classes/' . $path . '/{id}',
			['controller' => 'Classrooms', 'action' => $classroomAction],
			['pass' => ['id'], 'id' => '[0-9]+'] // Id of an assignment
		);

	$routes->connect(
		'/users/playerColorPreference',
		['controller' => 'Users', 'action' => 'playerColorPreference']
	);
	$routes->connect(
		'/users/boardOrientationPreference',
		['controller' => 'Users', 'action' => 'boardOrientationPreference']
	);
	$routes->connect(
		'/users/dateFormatPreference',
		['controller' => 'Users', 'action' => 'dateFormatPreference']
	);
	$routes->connect(
		'/users/emailOnNewMessagePreference',
		['controller' => 'Users', 'action' => 'emailOnNewMessagePreference']
	);
	$routes->connect(
		'/users/avatarPreference',
		['controller' => 'Users', 'action' => 'avatarPreference']
	);
	$routes->connect(
		'/users/avatarUpload',
		['controller' => 'Users', 'action' => 'avatarUpload']
	);
	$routes->connect(
		'/users/avatarRemove',
		['controller' => 'Users', 'action' => 'avatarRemove']
	);
	$routes->connect(
		'/profile',
		['controller' => 'Users', 'action' => 'profile']
	);
	$routes->connect(
		'/me',
		['controller' => 'Users', 'action' => 'profile']
	);
	$routes->connect(
		'/me/solve-history',
		['controller' => 'Users', 'action' => 'meSolveHistory']
	);
	$routes->connect(
		'/me/contributions',
		['controller' => 'Users', 'action' => 'meContributions']
	);

	//$routes->connect('/*', ['routeClass' => UrlRoute::class]);

	//The default routes of Cake 2: /controller/action/arguments, with the action named as in the url
	$routes->fallbacks(InflectedRoute::class);
};
