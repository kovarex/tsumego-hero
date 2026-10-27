<?php

namespace App\Utility;

use AdminActivityType;
use ClassRegistry;

//Centralized logging utility for all admin activities.
//Provides a simple, consistent API for recording admin actions with proper type IDs.
//Schema: admin_activity (id, user_id, tsumego_id, set_id, type, old_value, new_value, created)
//- type: INT FK to admin_activity_type(id)
//- old_value/new_value: VARCHAR(500) for state changes
//Usage:
//  // Edit with values (old → new)
//  AdminActivityLogger::log(AdminActivityType::DESCRIPTION_EDIT, $tsumegoId, null, $oldDesc, $newDesc);
//  // Simple action (no values)
//  AdminActivityLogger::log(AdminActivityType::PROBLEM_DELETE, $tsumegoId, $setId);
class AdminActivityLogger
{
	//Log any admin activity
	//$type: Activity type ID (use class constants)
	//$userId: Who did it, when not the logged-in user (null for the logged-in user)
	//Returns true if saved successfully, see the usage above.
	/**
	 * @param int|null $tsumegoId Problem ID (null for set-only activities)
	 * @param int|null $setId Set ID (null for problem-only activities)
	 * @param string|null $oldValue Old value for state changes/edits (null if not applicable)
	 * @param string|null $newValue New value for state changes/edits (null if not applicable)
	 */
	public static function log(int $type, string|int|null $tsumegoId = null, string|int|null $setId = null, string|int|float|null $oldValue = null, string|int|float|null $newValue = null, ?int $userId = null): bool
	{
		$userId ??= Auth::getUserID();
		if (!$userId)
			return false; // Only logged-in users

		$adminActivity = ClassRegistry::init('AdminActivity');
		$adminActivity->create();

		$data = [
			'AdminActivity' => [
				'user_id' => $userId,
				'tsumego_id' => $tsumegoId,
				'set_id' => $setId,
				'type' => $type,
				'old_value' => $oldValue,
				'new_value' => $newValue,
			],
		];

		return (bool) $adminActivity->save($data, false); // Skip validation for performance
	}
}
