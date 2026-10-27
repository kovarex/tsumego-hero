<?php

namespace App\Utility;

use PDO;
use RuntimeException;

//The name of a player in the phpBB forum tables.
//The forum shares our database and its single sign-on looks a player up by the username_clean
//key, so that key has to be the one phpBB itself computes with utf8_clean_string(): case folded,
//normalised, lookalike characters mapped to their latin counterparts and whitespace collapsed.
//phpBB caches the name in a few more tables as well, so a rename writes those copies too.
class PhpbbNames
{
	//Tables caching a player's name, as [table, name column, id column of that player].
	private const NAME_CACHES = [
		['phpbb_topics', 'topic_first_poster_name', 'topic_poster'],
		['phpbb_topics', 'topic_last_poster_name', 'topic_last_poster_id'],
		['phpbb_forums', 'forum_last_poster_name', 'forum_last_poster_id'],
		['phpbb_moderator_cache', 'username', 'user_id'],
	];

	//Turns a display name into the username_clean key phpBB stores for it.
	//The key has to be computed by phpBB itself, because the forum looks players up by this key
	//and would not find one we invented.
	/** @throws RuntimeException when phpBB's string functions are not available */
	public static function clean(string $displayName): string
	{
		self::loadPhpbb();

		if (!function_exists('utf8_clean_string'))
			throw new RuntimeException('phpBB string functions are missing, cannot compute username_clean');

		return utf8_clean_string($displayName);
	}

	//Writes a player's display name into their phpBB rows.
	//The ids beside the cached names are phpBB ids, so the account is translated through
	//external_id and only rows of that account are written; a rename never touches the
	//rows of another player.
	public static function sync(PDO $connection, int $userId, string $displayName): void
	{
		$statement = $connection->prepare(
			"UPDATE phpbb_users SET username = ?, username_clean = ? WHERE external_id = ?"
		);
		$statement->execute([$displayName, self::clean($displayName), $userId]);

		foreach (self::NAME_CACHES as [$table, $nameColumn, $idColumn])
		{
			$statement = $connection->prepare("UPDATE `$table` SET `$nameColumn` = ? WHERE `$idColumn` IN"
				. ' (SELECT `user_id` FROM `phpbb_users` WHERE `external_id` = ?)');
			$statement->execute([$displayName, $userId]);
		}

		// Guest posts keep a copy of the poster name, posts of registered players keep it empty.
		$statement = $connection->prepare(
			'UPDATE phpbb_posts SET post_username = ? WHERE poster_id IN'
			. " (SELECT `user_id` FROM phpbb_users WHERE `external_id` = ?) AND post_username <> ''"
		);
		$statement->execute([$displayName, $userId]);
	}

	//Loads phpBB's own string functions, so that username_clean is exactly the key phpBB expects.
	private static function loadPhpbb(): void
	{
		if (function_exists('utf8_clean_string'))
			return;

		global $phpbb_root_path, $phpEx;

		$root = dirname(__DIR__, 2) . '/webroot/forums/';
		$tools = $root . 'includes/utf/utf_tools.php';
		if (!is_file($tools))
			return;

		if (!defined('IN_PHPBB'))
			define('IN_PHPBB', true);

		$phpbb_root_path = $root;
		$phpEx = 'php';

		require_once $tools;
	}
}
