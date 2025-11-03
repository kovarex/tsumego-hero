<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigratePasswords extends AbstractMigration
{
	private function tinkerDecode($string, $key) {
		if (!is_string($string)) {
			return '';
		}
		$j = 1.0;
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

    public function up(): void
    {
		$users = ClassRegistry::init('User')->find('all', ['conditions' => ['password_hash' => '']]) ?: [];
		$userModel = ClassRegistry::init('User');
		echo "Rehashing passwords for ".count($users)." users\n";
		$count = 0;
		foreach ($users as $user) {
			$password = $this->tinkerDecode($user['User']['pw'], 1);
			$user['User']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
			$user['User']['pw'] = null;
			$userModel->save($user);
			++$count;
			if ($count % 500 == 0)
				echo ".";
		}
		echo "\n finished";
		$this->execute("ALTER TABLE USERS DROP COLUMN pw");
    }
}
