<?php

namespace App\Model\Table;

use App\Utility\Util;
use Cake\ORM\TableRegistry;
use SetConnection;
use User;

//An issue/problem report for a tsumego, which can contain multiple comments. user_id is the author
//of the issue, deleted is when the issue was deleted, NULL when it is not.
//The comments of the issue (TsumegoCommentTable) go with it through the foreign key.
class TsumegoIssueTable extends AppTable
{
	// the rows of tsumego_issue_status
	public const OPENED_STATUS = 1;
	public const CLOSED_STATUS = 2;

	//The issue list leaves out the issues of a problem that is off the site: one in the trash, and one
	//whose collections of the site are all in the trash (see App\Utility\Trash).
	private static function liveProblemSql(string $tsumegoIdColumn): string
	{
		return $tsumegoIdColumn . ' IN (SELECT id FROM tsumego WHERE deleted IS NULL)'
			. ' AND (EXISTS (SELECT 1 FROM set_connection live_connection'
			. ' JOIN `set` live_set ON live_set.id = live_connection.set_id AND live_set.user_id IS NULL AND live_set.deleted IS NULL'
			. ' WHERE live_connection.tsumego_id = ' . $tsumegoIdColumn . ')'
			. ' OR NOT EXISTS (SELECT 1 FROM set_connection any_connection'
			. ' JOIN `set` any_set ON any_set.id = any_connection.set_id AND any_set.user_id IS NULL'
			. ' WHERE any_connection.tsumego_id = ' . $tsumegoIdColumn . '))';
	}


	//Delete an issue if it has no non-deleted comments.
	//Should be called after a comment is deleted or removed from an issue to clean up empty issues automatically.
	//$issueId: The issue ID to check and potentially delete
	//Returns true if issue was deleted, false if it still has comments or doesn't exist
	public static function deleteIfEmpty(int $issueId): bool
	{
		$count = TableRegistry::getTableLocator()->get('TsumegoComment')->find()
			->where(['tsumego_issue_id' => $issueId, 'deleted IS' => null])
			->count();

		if ($count === 0)
			return TableRegistry::getTableLocator()->get('TsumegoIssue')->deleteAll(['id' => $issueId]) > 0;

		return false;
	}

	//Find issues for the global issues index page.
	//Returns issues with all their comments, formatted to be compatible with
	//the TsumegoIssues/issue.ctp element (same format as Tsumego::loadCommentsData).
	//$status: 'opened', 'closed', or 'all' (default: 'opened')
	//$limit: Number of results per page (default: 20)
	//$page: Page number for pagination (default: 1)
	//Returns formatted issues ready for the view (compatible with issue.ctp element)
	public static function findForIndex(string $status = 'opened', int $limit = 20, int $page = 1): array
	{
		$offset = ($page - 1) * $limit;

		// Build status condition
		$statusCondition = '';
		if ($status === 'opened')
			$statusCondition = 'AND tsumego_issue_status_id = ' . self::OPENED_STATUS;
		elseif ($status === 'closed')
			$statusCondition = 'AND tsumego_issue_status_id = ' . self::CLOSED_STATUS;

		// Query 1: Get paginated issue IDs
		$idsSql = "
			SELECT id
			FROM tsumego_issue
			WHERE deleted IS NULL
				AND " . self::liveProblemSql('tsumego_issue.tsumego_id') . "
			{$statusCondition}
			ORDER BY created DESC, id DESC
			LIMIT {$limit} OFFSET {$offset}
		";
		$issueIds = array_column(Util::query($idsSql), 'id');

		if (empty($issueIds))
			return [];

		$issueIdsStr = implode(',', array_map('intval', $issueIds));

		// Query 2: Get full data for those issues only
		$sql = "
			SELECT
				ti.id AS issue_id,
				ti.tsumego_issue_status_id,
				ti.tsumego_id,
				ti.user_id AS issue_user_id,
				ti.created AS issue_created,
				u.display_name AS issue_author_name,
				u.rating AS issue_author_rating,
				u.external_id AS issue_author_external_id,
				u.picture AS issue_author_picture,
				u.avatar_setting_id AS issue_author_avatar_setting_id,
				u.uploaded_avatar_hash AS issue_author_uploaded_avatar_hash,
				u.email AS issue_author_email,
				tc.id AS comment_id,
				tc.message AS comment_text,
				tc.created AS comment_created,
				tc.user_id AS comment_user_id,
				cu.id AS comment_author_id,
				cu.display_name AS comment_author_name,
				cu.external_id AS comment_author_external_id,
				cu.picture AS comment_author_picture,
				cu.avatar_setting_id AS comment_author_avatar_setting_id,
				cu.uploaded_avatar_hash AS comment_author_uploaded_avatar_hash,
				cu.email AS comment_author_email,
				cu.rating AS comment_author_rating,
				cu.user_role_id AS comment_author_user_role_id,
				s.id AS set_id,
				s.title AS set_title,
				sc.num AS tsumego_num
			FROM tsumego_issue ti
			LEFT JOIN user u ON u.id = ti.user_id
			LEFT JOIN tsumego_comment tc ON tc.tsumego_issue_id = ti.id AND tc.deleted IS NULL
			LEFT JOIN user cu ON cu.id = tc.user_id
			LEFT JOIN set_connection sc ON sc.id = (
				SELECT sc2.id
				FROM set_connection sc2
				JOIN `set` s2 ON s2.id = sc2.set_id
				WHERE sc2.tsumego_id = ti.tsumego_id AND NOT " . SetConnection::privateCollectionSql('s2') . "
				ORDER BY " . SetConnection::displayOrderSql('s2', 'sc2') . "
				LIMIT 1
			)
			LEFT JOIN `set` s ON s.id = sc.set_id
			WHERE ti.id IN ({$issueIdsStr})
			ORDER BY ti.created DESC, ti.id DESC, tc.created ASC
		";

		$rows = Util::query($sql);

		// Group results by issue, maintaining order from first query
		$issuesMap = [];

		foreach ($rows as $row)
		{
			$issueId = $row['issue_id'];

			if (!isset($issuesMap[$issueId]))
			{
				$statusId = $row['tsumego_issue_status_id'];

				$issuesMap[$issueId] = [
					'issue' => [
						'id' => $issueId,
						'tsumego_issue_status_id' => $statusId,
						'tsumego_id' => $row['tsumego_id'],
						'user_id' => $row['issue_user_id'],
						'user_display_name' => User::displayNameOrNull(['id' => $row['issue_user_id'], 'display_name' => $row['issue_author_name']]),
						'user_rating' => $row['issue_author_rating'] ?? null,
						'user_avatar_url' => User::avatarOrNull([
							'id' => $row['issue_user_id'],
							'display_name' => $row['issue_author_name'] ?? null,
							'external_id' => $row['issue_author_external_id'] ?? null,
							'picture' => $row['issue_author_picture'] ?? null,
							'avatar_setting_id' => $row['issue_author_avatar_setting_id'] ?? null,
							'uploaded_avatar_hash' => $row['issue_author_uploaded_avatar_hash'] ?? null,
							'email' => $row['issue_author_email'] ?? null,
						]),
						'created' => Util::toIso8601($row['issue_created']),
					],
					'comments' => [],
					'author' => ['name' => User::displayName(['id' => $row['issue_user_id'], 'display_name' => $row['issue_author_name']])],
					'tsumegoId' => $row['tsumego_id'],
					'Set' => $row['set_id'] ? [
						'id' => $row['set_id'],
						'title' => $row['set_title'],
					] : null,
					'TsumegoNum' => $row['tsumego_num'] ?? null,
				];
			}

			// Add comment if exists
			if (!empty($row['comment_id']))
			{
				$issuesMap[$issueId]['comments'][] = [
					'id' => $row['comment_id'],
					'text' => $row['comment_text'],
					'created' => Util::toIso8601($row['comment_created']),
					'user_id' => $row['comment_user_id'],
					'user_display_name' => User::displayNameOrNull(['id' => $row['comment_user_id'], 'display_name' => $row['comment_author_name'] ?? null]),
					'user_rating' => $row['comment_author_rating'],
					'user_avatar_url' => User::avatarOrNull([
						'id' => $row['comment_user_id'],
						'display_name' => $row['comment_author_name'] ?? null,
						'external_id' => $row['comment_author_external_id'] ?? null,
						'picture' => $row['comment_author_picture'] ?? null,
						'avatar_setting_id' => $row['comment_author_avatar_setting_id'] ?? null,
						'uploaded_avatar_hash' => $row['comment_author_uploaded_avatar_hash'] ?? null,
						'email' => $row['comment_author_email'] ?? null,
					]),
					'isAdmin' => User::isAdmin(['user_role_id' => $row['comment_author_user_role_id'] ?? null]),
				];
			}
		}

		// Build result in original order (from first query)
		$result = [];
		foreach ($issueIds as $index => $issueId)
			if (isset($issuesMap[$issueId]))
			{
				$issueData = $issuesMap[$issueId];
				$issueData['issueNumber'] = $offset + $index + 1;
				$result[] = $issueData;
			}

		return $result;
	}

	//Get counts for the issues index tabs.
	/** @return array{open: int, closed: int} */
	public static function getIndexCounts(): array
	{
		$row = Util::query('SELECT'
			. ' COALESCE(SUM(tsumego_issue_status_id = ' . self::OPENED_STATUS . '), 0) AS open,'
			. ' COALESCE(SUM(tsumego_issue_status_id = ' . self::CLOSED_STATUS . '), 0) AS closed'
			. ' FROM tsumego_issue WHERE deleted IS NULL AND ' . self::liveProblemSql('tsumego_issue.tsumego_id'))[0];
		return ['open' => (int) $row['open'], 'closed' => (int) $row['closed']];
	}

	//Get comment section counts for a specific tsumego.
	//Used for updating the comment tabs (ALL/COMMENTS/ISSUES).
	/** @return array{total: int, comments: int, issues: int, openIssues: int} */
	public static function getCommentSectionCounts(int $tsumegoId): array
	{
		// Count standalone comments (not in any issue)
		$commentCount = TableRegistry::getTableLocator()->get('TsumegoComment')->find()
			->where(['tsumego_id' => $tsumegoId, 'tsumego_issue_id IS' => null, 'deleted IS' => null])
			->count();

		$tsumegoIssueTable = TableRegistry::getTableLocator()->get('TsumegoIssue');

		// Count issues for this tsumego
		$issueCount = $tsumegoIssueTable->find()->where(['tsumego_id' => $tsumegoId])->count();

		// Count open issues for this tsumego
		$openIssueCount = $tsumegoIssueTable->find()->where(['tsumego_id' => $tsumegoId, 'tsumego_issue_status_id' => self::OPENED_STATUS])->count();

		return [
			'total' => $commentCount + $issueCount,
			'comments' => $commentCount,
			'issues' => $issueCount,
			'openIssues' => $openIssueCount,
		];
	}
}
