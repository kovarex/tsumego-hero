<?php

class Util {
	/**
	 * @param string $name
	 * @return void
	 */
	public static function clearCookie($name): void {
		setcookie($name, '', 1);
		$_COOKIE[$name] = '';
	}

	public static function generateRandomString($length = 20) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
	}
}
