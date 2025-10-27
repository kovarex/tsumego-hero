<?php

App::uses('Constants', 'Utility');

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

		Auth::$user = ClassRegistry::init('User')->findById((int) CakeSession::read('loggedInUserID'))['User'];
	}

	public static function isLoggedIn(): bool {
		return (bool) Auth::$user;
	}

	public static function getUserID(): int {
		return Auth::$user ? Auth::$user['id'] : 0;
	}

	public static function &getUser() {
		if (!Auth::$user) {
      debug_backtrace();
			die("Accessing user for writing when null");
		}
		return Auth::$user;
	}

	public static function isAdmin(): bool {
		return Auth::isLoggedIn() && Auth::getUser()['isAdmin'];
	}

	public static function hasPremium(): bool {
		return Auth::isLoggedIn() && Auth::getUser()['premium'];
	}

	public static function premiumLevel(): int {
		return Auth::isLoggedIn() ? Auth::getUser()['premium'] : 0;
	}

	public static function saveUser(): void {
		assert(Auth::isLoggedIn());
		ClassRegistry::init('User')->save(Auth::getUser());
	}

	public static function logout(): void {
		CakeSession::delete('loggedInUserID');
		Auth::$user = null;
	}

	public static function getWithDefault($key, $default) {
		if (!Auth::isLoggedIn()) {
			return $default;
		}
		return Auth::getUser()[$key];
	}

	public static function getMode(): int {
		return Auth::isLoggedIn() ? (int) Auth::getUser()['mode'] : Constants::$LEVEL_MODE;
	}

	public static function isInLevelMode(): bool {
		return Auth::getMode() == Constants::$LEVEL_MODE;
	}

	public static function isInTimeMode(): bool {
		return Auth::getMode() == Constants::$TIME_MODE;
	}

	private static $user = null;
}
