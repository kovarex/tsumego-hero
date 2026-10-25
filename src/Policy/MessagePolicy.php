<?php

namespace App\Policy;

/**
 * Messages between players. Every signed-in player has their own conversations and
 * can write to anyone, the recipient and the text are checked by Message::problemWith().
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class MessagePolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canRead(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canSend(?array $user): bool
	{
		return $user !== null;
	}
}
