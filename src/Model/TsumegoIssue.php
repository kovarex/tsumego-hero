<?php

class TsumegoIssue extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'tsumego_issue';
		parent::__construct($id, $table, $ds);
	}

	public static $OPENED_STATUS = 1;
	public static $CLOSED_STATUS = 2;
	public static $REVIEW_STATUS = 3;
	public static $DELETED_STATUS = 4;

	public static function statusName($status)
	{
		if ($status === self::$OPENED_STATUS)
			return 'Opened';
		if ($status === self::$CLOSED_STATUS)
			return 'Closed';
		if ($status === self::$REVIEW_STATUS)
			return 'Reviewed';
		if ($status === self::$DELETED_STATUS)
			return 'Deleted';
		throw new Exception("Invalid issue status: $status");
	}
}
