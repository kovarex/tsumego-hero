<?php

App::uses('ClassRegistry', 'Utility');
App::uses('Auth', 'Utility');

/**
 * AdminActivityLogger
 *
 * Centralized logging utility for all admin activities.
 * Provides a simple, consistent API for recording admin actions with proper type IDs.
 *
 * Schema: admin_activity (id, user_id, tsumego_id, set_id, type, old_value, new_value, created)
 * - type: INT FK to admin_activity_type(id)
 * - old_value/new_value: VARCHAR(500) for state changes
 *
 * Usage:
 *
 *   // Edit with values (old → new)
 *   AdminActivityLogger::log(AdminActivityType::DESCRIPTION_EDIT, $tsumegoId, null, $oldDesc, $newDesc);
 *
 *   // Simple action (no values)
 *   AdminActivityLogger::log(AdminActivityType::PROBLEM_DELETE, $tsumegoId, $setId);
 */
class AdminActivityLogger
{
	/**
	 * Log any admin activity
	 *
	 * @param int $type Activity type ID (use class constants)
	 * @param int|null $tsumegoId Problem ID (null for set-only activities)
	 * @param int|null $setId Set ID (null for problem-only activities)
	 * @param string|null $oldValue Old value for state changes/edits (null if not applicable)
	 * @param string|null $newValue New value for state changes/edits (null if not applicable)
	 * @return bool True if saved successfully
	 *
	 * Examples:
	 *   // Description edit with old → new
	 *   AdminActivityLogger::log(AdminActivityType::DESCRIPTION_EDIT, $tsumegoId, null, $oldDesc, $newDesc);
	 *
	 *   // Simple action without values
	 *   AdminActivityLogger::log(AdminActivityType::PROBLEM_DELETE, $tsumegoId, $setId);
	 */
	public static function log($type, $tsumegoId = null, $setId = null, $oldValue = null, $newValue = null)
	{
		$userId = Auth::getUserID();
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
