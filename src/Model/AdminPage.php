<?php

use App\Utility\Util;

/**
 * A page of the admin area (see AdminController) whose link counts what is new since the admin last
 * opened it. When that was is a row of admin_page_view for each admin and page. An admin who never
 * opened a page has nothing new there yet, the counting starts with the first visit.
 */
class AdminPage extends AppModel
{
	public const ACTIVITY = 1;
	public const UPLOADS = 2;

	public $useTable = 'admin_page';

	/**
	 * Remembers that the admin opened the page now.
	 *
	 * @return string|null When they opened it the time before, null the first time
	 */
	public static function markViewed(int $userID, int $adminPageID): ?string
	{
		$previous = Util::query('SELECT viewed FROM admin_page_view WHERE user_id = ? AND admin_page_id = ?', [$userID, $adminPageID]);
		Util::execute('INSERT INTO admin_page_view (user_id, admin_page_id, viewed) VALUES (?, ?, NOW())
ON DUPLICATE KEY UPDATE viewed = VALUES(viewed)', [$userID, $adminPageID]);
		return $previous[0]['viewed'] ?? null;
	}

	/**
	 * What the other admins did since the admin last looked at the admin activity.
	 */
	public static function countNewActivity(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total
FROM admin_page_view
JOIN admin_activity ON admin_activity.created > admin_page_view.viewed AND admin_activity.user_id <> admin_page_view.user_id
WHERE admin_page_view.user_id = ? AND admin_page_view.admin_page_id = ?', [$userID, self::ACTIVITY])[0]['total'];
	}

	/**
	 * The SGFs uploaded by others since the admin last looked at the uploads, the rejected
	 * proposals are not on the page.
	 */
	public static function countNewUploads(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total
FROM admin_page_view
JOIN sgf ON sgf.created > admin_page_view.viewed AND sgf.sgf_status_id <> ? AND NOT (sgf.user_id <=> admin_page_view.user_id)
WHERE admin_page_view.user_id = ? AND admin_page_view.admin_page_id = ?', [Sgf::STATUS_REJECTED, $userID, self::UPLOADS])[0]['total'];
	}
}
