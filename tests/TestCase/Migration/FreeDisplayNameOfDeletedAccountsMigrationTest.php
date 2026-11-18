<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261118014655_free_display_name_of_deleted_accounts.php';

//The display names of the accounts deleted so far move to deleted_display_name, the accounts get the placeholder,
//on the site and in the forum, and the accounts not deleted keep theirs. The test takes the migration back first,
//which gives the deleted accounts their names again, unless another account took one in the meantime.
class FreeDisplayNameOfDeletedAccountsMigrationTest extends AppTestCase
{
	public function testTheDeletedAccountsLetTheirDisplayNamesGo(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alive', 'display_name' => 'Alive'],
			'other-users' => [['name' => 'ghost', 'display_name' => 'Ghost', 'deleted' => true], ['name' => 'phantom', 'display_name' => 'Phantom', 'deleted' => true]]]);
		$aliveID = (int) $context->user['id'];
		$ghostID = (int) $context->otherUsers[0]['id'];
		$phantomID = (int) $context->otherUsers[1]['id'];
		Util::execute("INSERT INTO phpbb_users (user_id, username, username_clean, user_permissions, user_sig, external_id) VALUES (700020, ?, ?, '', '', ?)",
			['[deleted ' . $ghostID . ']', '[deleted ' . $ghostID . ']', $ghostID]);
		$migration = self::migration();
		$migration->down();
		try
		{
			$this->assertSame('Ghost', self::displayName($ghostID), 'taken back, the deleted account has its name again');
			$this->assertSame([['username' => 'Ghost', 'username_clean' => 'ghost']], Util::query('SELECT username, username_clean FROM phpbb_users WHERE user_id = 700020'));

			$migration->up();

			$this->assertSame([['display_name' => '[deleted ' . $ghostID . ']', 'deleted_display_name' => 'Ghost']],
				Util::query('SELECT display_name, deleted_display_name FROM user WHERE id = ?', [$ghostID]));
			$this->assertSame('[deleted ' . $phantomID . ']', self::displayName($phantomID));
			$this->assertSame([['display_name' => 'Alive', 'deleted_display_name' => null]],
				Util::query('SELECT display_name, deleted_display_name FROM user WHERE id = ?', [$aliveID]));
			$this->assertSame([['username' => '[deleted ' . $ghostID . ']', 'username_clean' => '[deleted ' . $ghostID . ']']],
				Util::query('SELECT username, username_clean FROM phpbb_users WHERE user_id = 700020'));

			// a new account takes the name, taking the migration back leaves it with the new account
			Util::execute("UPDATE user SET display_name = 'Ghost' WHERE id = ?", [$aliveID]);
			$migration->down();
			$this->assertSame('[deleted ' . $ghostID . ']', self::displayName($ghostID));
			$this->assertSame('Phantom', self::displayName($phantomID));
			$this->assertSame('Ghost', self::displayName($aliveID));
		}
		finally
		{
			// the schema the other tests run on comes back whatever failed
			if (!Util::query("SHOW COLUMNS FROM user LIKE 'deleted_display_name'"))
				$migration->up();
		}
	}

	private static function displayName(int $userID): string
	{
		return Util::query('SELECT display_name FROM user WHERE id = ?', [$userID])[0]['display_name'];
	}

	private static function migration(): FreeDisplayNameOfDeletedAccounts
	{
		$config = require ROOT . '/phinx.php';
		$migration = new FreeDisplayNameOfDeletedAccounts('test', 20261118014655);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
