<?php

class AdminActivityUtil
{
	public static function requestSolution(int $tsumegoID): bool
	{
		if (!Auth::isAdmin())
			return false;

		$adminActivity = [];
		$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
		$adminActivity['AdminActivity']['tsumego_id'] = $tsumegoID;
		$adminActivity['AdminActivity']['file'] = 'settings';
		$adminActivity['AdminActivity']['answer'] = 'requested solution';
		ClassRegistry::init('AdminActivity')->create();
		ClassRegistry::init('AdminActivity')->save($adminActivity);
		return true;
	}
}
