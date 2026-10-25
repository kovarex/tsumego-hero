<?php

namespace App\Policy;

/**
 * Challenges between players. Any signed-in player can start one, and the page of a challenge is
 * open to anyone, since its link is how players find it to join.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class ChallengePolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canCreate(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * Joining a challenge and playing it: any signed-in player, as long as there is room.
	 *
	 * @param UserRow|null $user
	 */
	public static function canJoin(?array $user): bool
	{
		return $user !== null;
	}
}
