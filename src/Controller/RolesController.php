<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\Util;

//Who is an admin or an owner (see User::ROLE_OWNER). Only an owner decides it: the list of the
//admins and owners is here, the role of anyone else is changed on their profile.
class RolesController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Owner', 'manageRoles');
		$this->set('_page', 'user');
		$this->set('_title', 'Admins and owners');
		$this->set('users', Util::query('
SELECT id, display_name, user_role_id
FROM user
WHERE ' . User::isAdminSql('user_role_id') . ' AND deleted IS NULL
ORDER BY user_role_id DESC, display_name'));
	}

	#[HttpPost]
	public function change(?string $userID = null): mixed
	{
		$this->Authorization->authorize('Owner', 'manageRoles');
		$user = ClassRegistry::init('User')->findById((int) $userID);
		if (!$user)
			throw new NotFoundException('User not found');

		$roleID = (int) ($this->request->data['user_role_id'] ?? 0);
		if ($error = User::changeRole((int) $userID, $roleID))
			CookieFlash::set($error, 'error');
		else
			CookieFlash::set('The role of ' . $user['User']['display_name'] . ' is ' . User::ROLE_NAMES[$roleID] . ' now', 'success');
		// an owner who just gave up the role can't see the list anymore
		if (!Auth::isOwner())
			return $this->redirect('/users/view/' . (int) $userID);
		return $this->redirect($this->referer('/roles', true));
	}
}
