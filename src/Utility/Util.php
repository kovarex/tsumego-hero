<?php

class Util
{
	public static function getCookieDomain(): ?string
	{
		$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
		return $host;
	}

	public static function setCookie($name, $value)
	{
		setcookie($name, $value, [
			'expires'  => time() + 365 * 24 * 60 * 60,
			'path'     => '/',
			'secure'   => true,
			'httponly' => false,
			'samesite' => 'Lax'
		]);
		// Also update $_COOKIE so the value is available in the current request
		$_COOKIE[$name] = $value;
	}

	/* @return The value of the cleared cookie */
	public static function clearCookie(string $name): ?string
	{
		$previous = $_COOKIE[$name] ?? null;
		setcookie($name, '',
			[
				'expires'  => time() - 3600,
				'path'     => '/',
				'secure'   => true,
				'httponly' => false,
				'samesite' => 'Lax'
			]);

		unset($_COOKIE[$name]);
		return $previous;
	}

	/* @return Int value of the cleared cookie, returns null if the cookeie isn't present. throws if it isn't numeric */
	public static function clearNumericCookie(string $name): ?int
	{
		$result = Util::clearCookie($name);
		if (!$result)
			return null;
		if (!is_numeric($result))
			throw new Exception("Cookie " . $name . " should be numeric but has value '" . strval($result) . "'.");
		return intval($result);
	}

	/* @return Int value of the cleared cookie, throws excpetion when the cookie isn't present or isn't numeric */
	public static function clearRequiredNumericCookie(string $name): int
	{
		$result = Util::clearNumericCookie($name);
		if (is_null($result))
			throw new Exception("Cookie " . $name . " is expected to be defined, but it isn't");
		return $result;
	}

	public static function getCookie(string $name, $default = null)
	{
		return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
	}

	public static function generateRandomString($length = 20)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++)
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		return $randomString;
	}

	public static function getPercentButAvoid100UntilComplete(int $value, int $max): int
	{
		assert($value <= $max);
		$result = (int) round(Util::getRatio($value, $max)) * 100;
		if ($result == 100 && $value < $max)
			return 99;
		return $result;
	}

	private const SECRET_KEY = 'my_simple_secret_keyx';
	private const SECRET_IV = 'my_simple_secret_ivx';
	private const ENCRYPT_METHOD = 'AES-256-CBC';

	public static function encrypt(string $str): string
	{
		$key = hash('sha256', self::SECRET_KEY);
		$iv = substr(hash('sha256', self::SECRET_IV), 0, 16);
		return base64_encode(openssl_encrypt($str, self::ENCRYPT_METHOD, $key, 0, $iv));
	}

	public static function decrypt(string $str): string
	{
		$key = hash('sha256', self::SECRET_KEY);
		$iv = substr(hash('sha256', self::SECRET_IV), 0, 16);
		return openssl_decrypt(base64_decode($str), self::ENCRYPT_METHOD, $key, 0, $iv);
	}

	public static function extract(string $name, array &$inputArray)
	{
		$result = $inputArray[$name];
		unset($inputArray[$name]);
		return $result;
	}

	public static function extractWithDefault(string $name, array &$inputArray, $default)
	{
		$result = $inputArray[$name];
		unset($inputArray[$name]);
		if (isset($result))
			return $result;
		return $default;
	}

	public static function getRatio(float|int $amount, float|int $max): float
	{
		if ($max == 0)
			return 0;
		return $amount / $max;
	}

	public static function getPercent(float|int $amount, float|int $max): float
	{
		return self::getRatio($amount, $max) * 100;
	}

	public static function indexByID($array, $prefix1, $prefix2)
	{
		$result = [];
		foreach ($array as $value)
			$result[$value[$prefix1]['id']] = $value[$prefix1][$prefix2];
		return $result;
	}

	public static function isInGithubCI()
	{
		if ($testEnvironment = @$_SERVER['TEST_ENVIRONMENT'])
			return $testEnvironment == 'github-ci';
		if ($host = @$_SERVER['HTTP_HOST'])
			return str_contains($host, 'host.docker.internal');
		return false;
	}

	public static function isInTestEnvironment(): bool
	{
		if (@$_SERVER['DDEV_PRIMARY_URL'] && str_contains($_SERVER['DDEV_PRIMARY_URL'], "tsumego.ddev.site"))
			return true;
		return Util::isInGithubCI();
	}

	public static function getMyAddress()
	{
		if (Util::isInGithubCI())
			return $_SERVER['TEST_APP_URL'];
		if ($url = @$_SERVER['DDEV_PRIMARY_URL'] && $_SERVER['HTTP_X_FORWARDED_HOST'])
			return 'https://' . $_SERVER['HTTP_X_FORWARDED_HOST'];
		return "https://test.tsumego.ddev.site:33003";
	}

	public static function getInternalAddress()
	{
		if (Util::isInGithubCI())
			return 'https://host.docker.internal:8443';
		return 'http://localhost';
	}

	public static function addSqlCondition(&$existingCondition, $condition): void
	{
		if (empty($condition))
			return;
		if (empty($existingCondition))
		{
			$existingCondition = $condition;
			return;
		}
		$existingCondition .= " AND " ;
		if (str_contains($condition, " OR "))
			$existingCondition .= '(' . $condition . ')';
		else
			$existingCondition .= $condition;
	}

	public static function addSqlOrCondition(&$existingCondition, $condition): void
	{
		if (empty($existingCondition))
		{
			$existingCondition = $condition;
			return;
		}
		$existingCondition .= " OR " . $condition;
	}

	public static function boolString($bool)
	{
		return $bool ? 'true' : 'false';
	}

	public static function getHealthBasedOnLevel(int $level): int
	{
		return intdiv($level, 5) + 10;
	}

	public static function query($sql, $params = [])
	{
		/** @phpstan-ignore-next-line */
		$stmt = ClassRegistry::init('Tsumego')->getDataSource()->getConnection()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function clampOptional($value, $min, $max)
	{
		$result = $value;
		if (!is_null($min))
			$result = max($min, $result);
		if (!is_null($max))
			$result = min($max, $result);
		return $result;
	}

	public static function strOrNull($input): ?string
	{
		if (is_null($input))
			return null;
		return strval($input);
	}

	public static function getGraphColor(): string
	{
		return Auth::lightMode() == Auth::$LIGHT_MODE ? '#ddd' : '#3e3e3e';
	}

	public static function getGraphGridColor(): string
	{
		return Auth::lightMode() == Auth::$LIGHT_MODE ? '#000' : '#fff';
	}

	public static function getValueGraphHeight($input)
	{
		return 160 + count($input) * 25;
	}

	public static function smallScoreTableRowColor(int $index): string
	{
		if ($index == 0)
			return '#ffec85';
		if ($index == 1)
			return '#939393';
		if ($index == 2)
			return '#c28d47';
		if ($index == 3)
			return '#85e35d';
		if ($index == 4)
			return '#85e35d';
		if ($index == 5)
			return '#85e35d';
		if ($index == 6)
			return '#85e35d';
		if ($index == 7)
			return '#85e35d';
		if ($index == 8)
			return '#85e35d';
		if ($index == 9)
			return '#85e35d';
		if ($index == 10)
			return '#85e35d';
		if ($index == 11)
			return '#85e35d';
		if ($index == 12)
			return '#85e35d';
		if ($index == 13)
			return '#85e35d';
		if ($index == 14)
			return '#85e35d';
		if ($index == 15)
			return '#85e35d';
		if ($index == 16)
			return '#85e35d';
		if ($index == 17)
			return '#85e35d';
		if ($index == 18)
			return '#85e35d';
		if ($index == 19)
			return '#85e35d';
		if ($index == 20)
			return '#9cf974';
		if ($index == 21)
			return '#9cf974';
		if ($index == 22)
			return '#9cf974';
		if ($index == 23)
			return '#9cf974';
		if ($index == 24)
			return '#9cf974';
		if ($index == 25)
			return '#9cf974';
		if ($index == 26)
			return '#9cf974';
		if ($index == 27)
			return '#9cf974';
		if ($index == 28)
			return '#9cf974';
		if ($index == 29)
			return '#9cf974';
		if ($index < 40)
			return '#b6f998';
		if ($index < 50)
			return '#d3f9c2';
		return '#e8f9e0';
	}
}
