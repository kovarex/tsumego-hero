<?php

namespace App\Model\Table;

use App\Utility\Util;
use Sgf;

//An overview of the admin area (see AdminController) whose link counts what is new since the admin last
//saw it. When that was is a row of admin_overview_seen for each admin and overview. An admin who never
//saw an overview has nothing new there yet, the counting starts with the first visit.
class AdminOverviewTypeTable extends AppTable
{
	// the rows of admin_overview_type
	public const ACTIVITY = 1;
	public const SGF_CHANGES = 2;

	//Remembers that the admin saw the overview now.
	//Returns when they saw it the time before, null the first time
	public static function markSeen(int $userID, int $adminOverviewTypeID): ?string
	{
		$previous = Util::query('SELECT seen FROM admin_overview_seen WHERE user_id = ? AND admin_overview_type_id = ?', [$userID, $adminOverviewTypeID]);
		Util::execute('INSERT INTO admin_overview_seen (user_id, admin_overview_type_id, seen) VALUES (?, ?, NOW())
ON DUPLICATE KEY UPDATE seen = VALUES(seen)', [$userID, $adminOverviewTypeID]);
		return $previous[0]['seen'] ?? null;
	}

	//What the other admins did since the admin last saw the admin activity.
	public static function countNewActivity(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total
FROM admin_overview_seen
JOIN admin_activity ON admin_activity.created > admin_overview_seen.seen AND admin_activity.user_id <> admin_overview_seen.user_id
WHERE admin_overview_seen.user_id = ? AND admin_overview_seen.admin_overview_type_id = ?', [$userID, self::ACTIVITY])[0]['total'];
	}

	//The changes accepted by others since the admin last saw the SGF changes, the proposals
	//waiting for a decision and the rejected ones are not on the page.
	public static function countNewSgfChanges(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total
FROM admin_overview_seen
JOIN sgf ON sgf.sgf_status_id = ? AND sgf.accepted > admin_overview_seen.seen AND NOT (sgf.accepted_by_user_id <=> admin_overview_seen.user_id)
WHERE admin_overview_seen.user_id = ? AND admin_overview_seen.admin_overview_type_id = ?', [Sgf::STATUS_ACCEPTED, $userID, self::SGF_CHANGES])[0]['total'];
	}
}
