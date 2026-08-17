<?php

App::uses('BasePolicy', 'Policy');

/**
 * Tag connections: proposing a tag requires the can_contribute capability;
 * removing one is allowed for admins or the proposer (unapproved proposals only).
 */
class TagConnectionPolicy extends BasePolicy
{
	public static function canAdd($user): bool
	{
		return static::hasPermission($user, 'can_contribute');
	}

	public static function canRemove($user, $tagConnection): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $tagConnection['user_id'] === $user['id']
			&& !$tagConnection['approved'];
	}
}
