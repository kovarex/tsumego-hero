<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigratePasswords extends AbstractMigration
{
	private function tinkerDecode($string, $key) {
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

    public function up(): void
    {
		$users = $this->query("SELECT id, pw from `user` WHERE password_hash = ''")->fetchAll(PDO::FETCH_ASSOC);
		echo "Rehashing passwords for ".count($users)." users\n";
		$count = 0;

		foreach ($users as $user) {
			$password = $this->tinkerDecode($user['pw'], 1);
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);
			$this->execute("UPDATE `user` SET password_hash = '".$passwordHash."', pw = null WHERE id = ".$user['id']);
			$count++;
			if ($count % 100 == 0) {
				echo ".";
				if ($count % 1000 == 0)
					echo " ".(($count / count($users)) * 100)."%\n";
			}
		}
		echo "\n finished";
		$this->execute("ALTER TABLE `user` DROP COLUMN pw");
    }
}
