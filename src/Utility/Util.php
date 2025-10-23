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

}
