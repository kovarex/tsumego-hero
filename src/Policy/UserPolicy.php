<?php

namespace App\Policy;

//User-level authorization.
//Self-service actions that operate on the caller's own account or progress
//(personal preferences, reporting one's own result, starting one's own time
//mode session) are decided by the identity alone: being signed in is the whole
//rule.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class UserPolicy extends BasePolicy
{
	/** @param UserRow|null $user */
	public static function canEditPreferences(?array $user): bool
	{
		return $user !== null;
	}

	//Reporting the result of a problem one just played.
	/** @param UserRow|null $user */
	public static function canReportResult(?array $user): bool
	{
		return $user !== null;
	}

	//Starting a time mode session.
	/** @param UserRow|null $user */
	public static function canStartTimeMode(?array $user): bool
	{
		return $user !== null;
	}
}
