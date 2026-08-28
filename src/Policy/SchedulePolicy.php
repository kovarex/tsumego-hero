<?php

App::uses('BasePolicy', 'Policy');

class SchedulePolicy extends BasePolicy
{
	public static function canIndex($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAdd($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canPreview($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canCancel($user): bool
	{
		return static::isAdmin($user);
	}
}
