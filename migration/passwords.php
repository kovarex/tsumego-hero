<?php

function tinkerDecode($string, $key) {
	if (!is_string($string)) {
		return '';
	}
	$j = 1;
	$hash = '';
	$key = sha1((string) $key);
	$strLen = strlen($string);
	$keyLen = strlen($key);
	for ($i = 0; $i < $strLen; $i += 2) {
		$ordStr = hexdec(base_convert(strrev(substr($string, $i, 2)), 36, 16));
		if ($j == $keyLen) {
			$j = 0;
		}
		$ordKey = ord(substr($key, $j, 1));
		$j++;
		$hash .= chr($ordStr - $ordKey);
	}

	return $hash;
}

echo "Running custom script...";


$host     = $argv[1];
$user     = $argv[2];
$password = $argv[3];
$db       = $argv[4];
$charset  = 'utf8mb4';

$db = new mysqli($host, $user, $password, $db);
$db->set_charset($charset);
$db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

$overallCount = $db->query("SELECT Count(*) as count  from `user` WHERE password_hash = ''")->fetch_assoc()['count'];

$users = $db->query("SELECT id, pw from `user` WHERE password_hash = ''");
echo "Rehashing passwords for ".$overallCount." users\n";
$count = 0;

while ($user = $users->fetch_assoc()) {
	$password = tinkerDecode($user['pw'], 1);
	$passwordHash = password_hash($password, PASSWORD_DEFAULT);
	$db->query("UPDATE `user` SET password_hash = '".$passwordHash."', pw = null WHERE id = ".$user['id']);
	$count++;
	if ($count % 100 == 0) {
		echo ".";
		if ($count % 1000 == 0)
			echo " ".(($count / $overallCount) * 100)."%\n";
	}
}
echo "\n finished\n";
