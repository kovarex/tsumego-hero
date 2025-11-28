<?php

App::uses('ClassRegistry', 'Utility');
App::uses('CakeSession', 'Model/Datasource');

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
 *   AdminActivityLogger::log(AdminActivityLogger::DESCRIPTION_EDIT, $tsumegoId, null, $oldDesc, $newDesc);
 *
 *   // Simple action (no values)
 *   AdminActivityLogger::log(AdminActivityLogger::PROBLEM_DELETE, $tsumegoId, $setId);
 */
class AdminActivityLogger
{
	// Activity Type IDs (from admin_activity_type table)
	// Problem Edits
	public const DESCRIPTION_EDIT = 1;
	public const HINT_EDIT = 2;
	public const PROBLEM_DELETE = 3;

	// Problem Settings (multi-state: 0=disabled, 1=enabled)
	public const ALTERNATIVE_RESPONSE = 4;
	public const PASS_MODE = 5;

	// Problem Type Changes (multi-state: 0=delete, 1=add)
	public const MULTIPLE_CHOICE = 6;
	public const SCORE_ESTIMATING = 7;

	// Requests
	public const SOLUTION_REQUEST = 8;

	// Set Metadata Edits
	public const SET_TITLE_EDIT = 9;
	public const SET_DESCRIPTION_EDIT = 10;
	public const SET_COLOR_EDIT = 11;
	public const SET_ORDER_EDIT = 12;
	public const SET_RATING_EDIT = 13;

	// Set Operations
	public const PROBLEM_ADD = 14;

	// Set Bulk Operations (multi-state: 0=disabled, 1=enabled)
	public const SET_ALTERNATIVE_RESPONSE = 15;
	public const SET_PASS_MODE = 16;

	// Duplicate Management
	public const DUPLICATE_REMOVE = 17;
	public const DUPLICATE_GROUP_CREATE = 18;

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
	 *   AdminActivityLogger::log(AdminActivityLogger::DESCRIPTION_EDIT, $tsumegoId, null, $oldDesc, $newDesc);
	 *
	 *   // Simple action without values
	 *   AdminActivityLogger::log(AdminActivityLogger::PROBLEM_DELETE, $tsumegoId, $setId);
	 */
	public static function log($type, $tsumegoId = null, $setId = null, $oldValue = null, $newValue = null)
	{
		$userId = CakeSession::read('loggedInUserID');
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
