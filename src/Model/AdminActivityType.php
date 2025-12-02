<?php

class AdminActivityType extends AppModel
{
	public $useTable = 'admin_activity_type';

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
}
