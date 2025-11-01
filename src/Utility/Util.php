<?php

class Util {
	/* @return The value of the cleared cookie */
	public static function clearCookie(string $name): ?string {
		$result = !empty($_COOKIE[$name]) ? $_COOKIE[$name] : null;
		setcookie($name, '', 1);
		$_COOKIE[$name] = '';
		return $result;
	}

	public static function getCookie(string $name, $default = null) {
		return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
	}

	public static function generateRandomString($length = 20) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	public static function getPercentButAvoid100UntillComplete(int $value, int $max): int {
		assert($value <= $max);
		$result = (int) round($value / $max);
		if ($result == 100 && $value < $max) {
			return 99;
		}
		return $result;
	}

	public static function wierdEncrypt(string $str): string {
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
		return base64_encode(openssl_encrypt($str, $encrypt_method, $key, 0, $iv));
	}

	public static function wierdDecrypt(string $str): string {
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
		return openssl_decrypt(base64_decode($str), $encrypt_method, $key, 0, $iv);
	}

	public static function extract(string $name, array &$inputArray) {
		$result = $inputArray[$name];
		unset($inputArray[$name]);
		return $result;
	}

	public static function getRatio(float|int $amount, float|int $max): float {
		if ($max == 0) {
			return 0;
		}
		return $amount / $max;
	}

	public static function getPercent(float|int $amount, float|int $max): float {
		return self::getRatio($amount, $max) * 100;
	}

	public static function indexByID($array, $prefix1, $prefix2) {
		$result = [];
		foreach ($array as $value) {
			$result[$value[$prefix1]['id']] = $value[$prefix1][$prefix2];
		}
		return $result;
	}
}
