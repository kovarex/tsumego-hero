<?php

class Auth {
	public static function init($user = null): void {
		if ($user) {
			Auth::$user = $user['User'];
			CakeSession::write('loggedInUserID', Auth::$user['id']);
			return;
		}

		if (!CakeSession::check('loggedInUserID')) {
			Auth::$user = null;
			return;
		}

		Auth::$user = ClassRegistry::init('User')->findById(CakeSession::read('loggedInUserID'))['User'];
	}

	public static function isLoggedIn(): bool {
		return (bool) Auth::$user;
	}

	public static function getUserID(): int {
		return Auth::getUser() ? Auth::getUser()['id'] : 0;
	}

	public static function &getUser(): ?array {
		return Auth::$user;
	}

	public static function isAdmin(): bool {
		return Auth::getUser() && Auth::getUser()['isAdmin'];
	}

	public static function hasPremium(): bool {
		return Auth::getUser() && Auth::getUser()['premium'];
	}

	public static function premiumLevel(): int {
		return Auth::getUser() ? Auth::getUser()['premium'] : 0;
	}

	public static function saveUser() {
		assert(Auth::getUser());
		ClassRegistry::init('User')->save(Auth::getUser());
	}

	public static function logout() {
		CakeSession::delete('loggedInUserID');
		Auth::$user = null;
	}

	private static $user = null;
}
