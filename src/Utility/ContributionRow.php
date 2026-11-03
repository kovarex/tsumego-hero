<?php

namespace App\Utility;

use App\Model\Table\RejectTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;

//A single row in a user's contributions timeline.
final readonly class ContributionRow
{
	public function __construct(
		public string $type,
		public string $status,
		public string $created,
		public string $tagId = '',
		public string $tag = '',
		public string $tsumegoId = '',
		public string $tsumegoLabel = '',
	) {}

	public static function fromQueryRow(array $row): self
	{
		return new self(
			type: $row['type'] ?? '',
			status: $row['status'] ?? '',
			created: $row['created'] ?? '',
			tagId: $row['tag_id'] ?? '',
			tag: $row['tag'] ?? '',
			tsumegoId: $row['tsumego_id'] ?? '',
			tsumegoLabel: $row['tsumego_label'] ?? '',
		);
	}

	//A page of what the player contributed, the newest first: the tags they proposed, the tags
	//they put on problems, their proposals of solutions, and what of it was rejected. A problem
	//is named by the collection it is shown in.
	/** @return array{0: list<self>, 1: int} The rows, and how many there are on all the pages */
	public static function ofUser(int $userId, int $limit, int $offset): array
	{
		$problemJoin = fn(string $join, string $tsumegoIdColumn) => $join . ' set_connection sc ON sc.id = '
			. SetConnectionTable::displaySetConnectionIdSql($tsumegoIdColumn) . ' '
			. $join . ' `set` s ON s.id = sc.set_id ';
		$rows = Util::query(
			'SELECT SQL_CALC_FOUND_ROWS type, status, created, tag_id, tag, tsumego_id, tsumego_label, row_id FROM ('
				// the tags they proposed
				. 'SELECT \'tag name\' AS type, IF(t.approved, \'accepted\', \'pending\') AS status, t.created, t.id AS tag_id, t.name AS tag, \'\' AS tsumego_id, \'\' AS tsumego_label, t.id AS row_id '
				. 'FROM tag t WHERE t.user_id = ? '
				. 'UNION ALL '
				// the tags they put on problems
				. 'SELECT \'tag\', IF(tc.approved, \'accepted\', \'pending\'), tc.created, t.id, t.name, tc.tsumego_id, CONCAT(s.title, \' - \', sc.num), tc.id '
				. 'FROM tag_connection tc '
				. 'JOIN tag t ON t.id = tc.tag_id '
				. $problemJoin('JOIN', 'tc.tsumego_id')
				. 'WHERE tc.user_id = ? '
				. 'UNION ALL '
				// their proposals of solutions; the rejected ones come from their reject rows below
				. 'SELECT \'proposal\', IF(sg.sgf_status_id = ' . SgfTable::STATUS_ACCEPTED . ', \'accepted\', \'pending\'), sg.created, \'\', \'\', sg.tsumego_id, CONCAT(s.title, \' - \', sc.num), sg.id '
				. 'FROM sgf sg '
				. $problemJoin('JOIN', 'sg.tsumego_id')
				. 'WHERE sg.user_id = ? AND sg.sgf_status_id != ' . SgfTable::STATUS_REJECTED . ' '
				. 'UNION ALL '
				// what was rejected, reject_type names it like the rows above; a rejected new tag has no problem
				. 'SELECT rt.name, \'rejected\', r.created, COALESCE(t.id, \'\'), COALESCE(t.name, r.tag_name, \'\'), COALESCE(r.tsumego_id, \'\'), '
				. 'COALESCE(CONCAT(s.title, \' - \', sc.num), \'\'), r.id '
				. 'FROM reject r '
				. 'JOIN reject_type rt ON rt.id = r.reject_type_id '
				. 'LEFT JOIN tag t ON t.id = r.tag_id '
				. $problemJoin('LEFT JOIN', 'r.tsumego_id')
				. 'WHERE r.user_id = ? AND (sc.id IS NOT NULL OR r.reject_type_id = ' . RejectTable::TYPE_TAG_NAME . ') '
			. ') AS contributions '
			. 'ORDER BY created DESC, row_id DESC '
			. 'LIMIT ' . $limit . ' OFFSET ' . $offset,
			[$userId, $userId, $userId, $userId]);
		$count = (int) Util::query('SELECT FOUND_ROWS() AS count')[0]['count'];
		return [array_map(self::fromQueryRow(...), $rows), $count];
	}
}
