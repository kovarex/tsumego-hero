<?php

namespace App\Model\Table;

//What an admin did, the type of an admin_activity row. The ids are the rows of admin_activity_type;
//tests/ContextPreparator.php seeds them with their names.
class AdminActivityTypeTable extends AppTable
{
	// Problem Edits
	public const DESCRIPTION_EDIT = 1;
	public const HINT_EDIT = 2;
	public const PROBLEM_DELETE = 3; // moved to the trash, see App\Utility\Trash
	public const PROBLEM_RESTORE = 40; // taken back from the trash
	public const AUTHOR_EDIT = 19;
	public const RATING_EDIT = 20;
	public const MINIMUM_RATING_EDIT = 21;
	public const MAXIMUM_RATING_EDIT = 22;
	public const SGF_EDIT = 27;

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
	public const SET_DELETE = 41; // moved to the trash, see App\Utility\Trash
	public const SET_RESTORE = 42; // taken back from the trash

	// Set Bulk Operations (multi-state: 0=disabled, 1=enabled)
	public const SET_ALTERNATIVE_RESPONSE = 15;
	public const SET_PASS_MODE = 16;

	// Tags
	public const ACCEPT_TAG = 23;
	public const REJECT_TAG = 24;
	public const ADD_TAG = 28; // An admin adds a tag to a problem. It doesn't need to be accepted.
	public const DELETE_TAG = 44; // old_value: JSON of the deleted tag row and its tag connections

	// Proposals
	public const ACCEPT_PROPOSAL = 29;
	public const REJECT_PROPOSAL = 30;
	public const REOPEN_PROPOSAL = 35; // a rejected SGF proposal waits for a decision again

	// Tsumego merge
	public const TSUMEGO_MERGE = 25;

	// Delete user
	public const DELETE_USER = 26;
	public const RESTORE_USER = 31;

	// Display name
	public const DISPLAY_NAME_CHANGE = 32;

	// Roles: an owner made someone a player, an admin or an owner (see User::changeRole)
	public const ROLE_CHANGE = 43;

	// Schedule
	public const ADD_TO_SCHEDULE = 33;
	public const CANCEL_SCHEDULE = 34;
}
