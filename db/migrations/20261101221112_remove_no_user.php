<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//User 33 was "noUser", a fake account the automatic SGF versions were stored under, which the code
//singled out by its id and its name. An automatic version has no user now (sgf.user_id NULL), so its
//versions move to NULL and the account goes.
//Anything else that still points at the account stops the migration, with each column and its count,
//read from the schema: an integer column with a foreign key to user, named *user_id or an author,
//teacher, owner or creator id, that holds 33, or a text column named like author, name or user that
//holds 'noUser'. The forum tables (phpbb_*) have their own users and are left out.
final class RemoveNoUser extends AbstractMigration
{
	private const USER_ID = 33;
	private const NAME = 'noUser';

	public function up(): void
	{
		$user = $this->fetchRow('SELECT id, name, display_name FROM user WHERE id = ' . self::USER_ID);
		if (!$user)
			return;
		if ($user['name'] !== self::NAME && $user['display_name'] !== self::NAME)
			throw new RuntimeException('User ' . self::USER_ID . ' is not ' . self::NAME . ' but ' . ($user['display_name'] ?? $user['name']) . ', nothing was changed');

		$references = [];
		foreach (self::userIDColumns($this) as $column)
		{
			if ($column['table_name'] === 'sgf' && $column['column_name'] === 'user_id')
				continue;
			$count = (int) $this->fetchRow('SELECT COUNT(*) AS n FROM `' . $column['table_name'] . '` WHERE `' . $column['column_name'] . '` = ' . self::USER_ID)['n'];
			if ($count > 0)
				$references[] = $column['table_name'] . '.' . $column['column_name'] . ': ' . $count;
		}
		foreach (self::userNameColumns($this) as $column)
		{
			$count = (int) $this->fetchRow('SELECT COUNT(*) AS n FROM `' . $column['table_name'] . '` WHERE `' . $column['column_name'] . "` = '" . self::NAME . "'")['n'];
			if ($count > 0)
				$references[] = $column['table_name'] . '.' . $column['column_name'] . " = '" . self::NAME . "': " . $count;
		}
		if ($references)
			throw new RuntimeException('User ' . self::USER_ID . ' (' . self::NAME . ') is still referenced, nothing was changed: ' . implode(', ', $references));

		$this->execute('UPDATE sgf SET user_id = NULL WHERE user_id = ' . self::USER_ID);
		$this->execute('DELETE FROM user WHERE id = ' . self::USER_ID);
	}

	public function down(): void
	{
		throw new Phinx\Migration\IrreversibleMigrationException('The noUser account is gone, its SGF versions are automatic ones');
	}

	//The integer columns that can hold a user id: every foreign key to user.id, every *user_id column
	//(but user_role_id) and the author/teacher/owner/creator ids, with a foreign key or without.
	//The user table itself and the forum tables (phpbb_*) are left out.
	/** @return list<array{table_name: string, column_name: string}> */
	private static function userIDColumns(AbstractMigration $migration): array
	{
		return $migration->fetchAll("SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
			FROM information_schema.COLUMNS c
			JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
			WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME <> 'user' AND c.TABLE_NAME NOT LIKE 'phpbb\\_%'
			AND c.DATA_TYPE IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
			AND (
				EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
					WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME
					AND k.REFERENCED_TABLE_NAME = 'user' AND k.REFERENCED_COLUMN_NAME = 'id')
				OR (c.COLUMN_NAME LIKE '%user\\_id' AND c.COLUMN_NAME <> 'user_role_id')
				OR c.COLUMN_NAME IN ('author_id', 'teacher_id', 'owner_id', 'creator_id'))
			ORDER BY 1, 2");
	}

	//The text columns that can hold a user's name: those named like author, name or user. The user
	//table itself and the forum tables (phpbb_*) are left out.
	/** @return list<array{table_name: string, column_name: string}> */
	private static function userNameColumns(AbstractMigration $migration): array
	{
		return $migration->fetchAll("SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
			FROM information_schema.COLUMNS c
			JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
			WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME <> 'user' AND c.TABLE_NAME NOT LIKE 'phpbb\\_%'
			AND c.DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')
			AND (c.COLUMN_NAME LIKE '%author%' OR c.COLUMN_NAME LIKE '%name%' OR c.COLUMN_NAME LIKE '%user%')
			ORDER BY 1, 2");
	}
}
