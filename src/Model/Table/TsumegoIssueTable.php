<?php

namespace App\Model\Table;

use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

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


	//An issue with its comments as the comment section shows it (app/issues/issueTypes.ts).
	/**
	 * @param list<TsumegoComment> $comments The comments of the issue, in the order they were written
	 * @param array<int, array<string, mixed>> $authors The writers of the issue and its comments, see TsumegoCommentTable::authorsOf()
	 * @return array<string, mixed>
	 */
	public static function payload(TsumegoIssue $issue, array $comments, array $authors): array
	{
		return [
			'id' => $issue->id,
			'tsumego_issue_status_id' => $issue->tsumego_issue_status_id,
			'created' => $issue->created->format('c'),
			'user_id' => $issue->user_id,
		] + TsumegoCommentTable::authorPayload($authors[$issue->user_id] ?? null) + [
			'comments' => array_map(fn(TsumegoComment $comment) => TsumegoCommentTable::payload($comment, $authors[$comment->user_id] ?? null), $comments),
		];
	}

	//Opens an issue on the problem of the comment, with the comment as its first one; the writer
	//of the comment is the author of the issue. The comment is saved, new or moved from elsewhere.
	//Returns the issue, null when the comment fails the validation (nothing is saved then)
	public static function openWith(TsumegoComment $comment): ?TsumegoIssue
	{
		// false from the callback rolls the issue back
		return Util::database()->transactional(function () use ($comment): TsumegoIssue|false {
			$issueTable = TableRegistry::getTableLocator()->get('TsumegoIssue');
			$issue = $issueTable->saveOrFail($issueTable->newEntity([
				'tsumego_id' => $comment->tsumego_id,
				'user_id' => $comment->user_id,
				'tsumego_issue_status_id' => self::OPENED_STATUS,
			]));
			$comment->tsumego_issue_id = $issue->id;
			return TableRegistry::getTableLocator()->get('TsumegoComment')->save($comment) ? $issue : false;
		}) ?: null;
	}

	//Whether the issue exists and is one of the problem, so a comment of the problem can go in it.
	public static function isOfProblem(int $issueId, int $tsumegoId): bool
	{
		return TableRegistry::getTableLocator()->get('TsumegoIssue')->exists(['id' => $issueId, 'tsumego_id' => $tsumegoId]);
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

	//The columns of the user `alias` a page of issues shows, each named with the prefix, for authorOf().
	private static function authorColumns(string $alias, string $prefix): string
	{
		return implode(', ', array_map(fn(string $column) => $alias . '.' . $column . ' AS ' . $prefix . $column,
			['id', 'display_name', 'deleted', 'rating', 'external_id', 'picture', 'avatar_setting_id', 'uploaded_avatar_hash', 'email', 'user_role_id']));
	}

	//The user row authorColumns() selected with the prefix.
	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function authorOf(array $row, string $prefix): array
	{
		$author = [];
		foreach ($row as $column => $value)
			if (str_starts_with($column, $prefix))
				$author[substr($column, strlen($prefix))] = $value;
		return $author;
	}

	//A page of the issues of the issues page, the newest first, each with its comments in the order
	//they were written, its problem and its number in the whole list.
	//$status: 'opened', 'closed' or 'all'
	/** @return list<array<string, mixed>> */
	public static function findForIndex(string $status = 'opened', int $limit = 20, int $page = 1): array
	{
		$offset = ($page - 1) * $limit;
		$statusCondition = match ($status)
		{
			'opened' => 'AND tsumego_issue_status_id = ' . self::OPENED_STATUS,
			'closed' => 'AND tsumego_issue_status_id = ' . self::CLOSED_STATUS,
			default => '',
		};

		// the page of issues first, then everything about them
		$issueIds = array_column(Util::query('SELECT id
			FROM tsumego_issue
			WHERE deleted IS NULL AND ' . self::liveProblemSql('tsumego_issue.tsumego_id') . '
			' . $statusCondition . '
			ORDER BY created DESC, id DESC
			LIMIT ' . $limit . ' OFFSET ' . $offset), 'id');
		if (empty($issueIds))
			return [];

		$rows = Util::query('SELECT
				ti.id AS issue_id, ti.tsumego_issue_status_id, ti.tsumego_id, ti.user_id AS issue_user_id, ti.created AS issue_created,
				' . self::authorColumns('u', 'issue_author_') . ',
				tc.id AS comment_id, tc.message AS comment_text, tc.created AS comment_created, tc.user_id AS comment_user_id,
				' . self::authorColumns('cu', 'comment_author_') . ',
				s.id AS set_id, s.title AS set_title, sc.num AS tsumego_num
			FROM tsumego_issue ti
			LEFT JOIN user u ON u.id = ti.user_id
			LEFT JOIN tsumego_comment tc ON tc.tsumego_issue_id = ti.id AND tc.deleted IS NULL
			LEFT JOIN user cu ON cu.id = tc.user_id
			LEFT JOIN set_connection sc ON sc.id = (
				SELECT sc2.id
				FROM set_connection sc2
				JOIN `set` s2 ON s2.id = sc2.set_id
				WHERE sc2.tsumego_id = ti.tsumego_id AND NOT ' . SetConnectionTable::privateCollectionSql('s2') . '
				ORDER BY ' . SetConnectionTable::displayOrderSql('s2', 'sc2') . '
				LIMIT 1
			)
			LEFT JOIN `set` s ON s.id = sc.set_id
			WHERE ti.id IN (' . implode(',', array_map('intval', $issueIds)) . ')
			ORDER BY ti.created DESC, ti.id DESC, tc.created ASC');

		$issues = [];
		foreach ($rows as $row)
		{
			$issueId = $row['issue_id'];
			if (!isset($issues[$issueId]))
			{
				$author = ['id' => $row['issue_user_id']] + self::authorOf($row, 'issue_author_');
				$issues[$issueId] = [
					'issue' => [
						'id' => $issueId,
						'tsumego_issue_status_id' => $row['tsumego_issue_status_id'],
						'tsumego_id' => $row['tsumego_id'],
						'user_id' => $row['issue_user_id'],
						'user_display_name' => UserTable::displayNameOrNull($author),
						'user_rating' => $author['rating'],
						'user_avatar_url' => UserTable::avatarOrNull($author),
						'created' => Util::toIso8601($row['issue_created']),
					],
					'comments' => [],
					'author' => ['name' => UserTable::displayName($author)],
					'tsumegoId' => $row['tsumego_id'],
					'Set' => $row['set_id'] ? ['id' => $row['set_id'], 'title' => $row['set_title']] : null,
					'TsumegoNum' => $row['tsumego_num'],
				];
			}

			if (empty($row['comment_id']))
				continue;
			$author = ['id' => $row['comment_user_id']] + self::authorOf($row, 'comment_author_');
			$issues[$issueId]['comments'][] = [
				'id' => $row['comment_id'],
				'text' => $row['comment_text'],
				'created' => Util::toIso8601($row['comment_created']),
				'user_id' => $row['comment_user_id'],
				'user_display_name' => UserTable::displayNameOrNull($author),
				'user_rating' => $author['rating'],
				'user_avatar_url' => UserTable::avatarOrNull($author),
				'isAdmin' => UserTable::isAdmin($author),
			];
		}

		$result = [];
		foreach ($issueIds as $index => $issueId)
			if (isset($issues[$issueId]))
				$result[] = $issues[$issueId] + ['issueNumber' => $offset + $index + 1];
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

	//What the tabs of the comment section count on a problem: its standalone comments, its issues and
	//the open ones among them. The play page asks for it on every view, so it is one query.
	/** @return array{total: int, comments: int, issues: int, openIssues: int} */
	public static function getCommentSectionCounts(int $tsumegoId): array
	{
		$row = Util::query('SELECT
				(SELECT COUNT(*) FROM tsumego_comment WHERE tsumego_id = ? AND tsumego_issue_id IS NULL AND deleted IS NULL) AS comments,
				COUNT(*) AS issues,
				COALESCE(SUM(tsumego_issue_status_id = ?), 0) AS open_issues
			FROM tsumego_issue WHERE tsumego_id = ?',
			[$tsumegoId, self::OPENED_STATUS, $tsumegoId])[0];
		$comments = (int) $row['comments'];
		$issues = (int) $row['issues'];
		return [
			'total' => $comments + $issues,
			'comments' => $comments,
			'issues' => $issues,
			'openIssues' => (int) $row['open_issues'],
		];
	}

	//What the comments of a problem hold that may need a look, in a few words for the More toggle of the
	//play page, like "3 comments, 1 open issue"; closed issues are done, so they aren't mentioned
	/** @param array{total: int, comments: int, issues: int, openIssues: int} $counts */
	public static function commentSummary(array $counts): string
	{
		$plural = fn(int $count, string $word) => $count . ' ' . $word . ($count === 1 ? '' : 's');
		$parts = [];
		if ($counts['comments'] > 0)
			$parts[] = $plural($counts['comments'], 'comment');
		if ($counts['openIssues'] > 0)
			$parts[] = $plural($counts['openIssues'], 'open issue');
		return implode(', ', $parts);
	}
}
