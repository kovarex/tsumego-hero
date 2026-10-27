<?php

use App\Utility\Util;

//An admin ticked an SGF change (a version in sgf) as checked, on the SGF changes page of the admin
//area (SgfChangesRenderer). There is no "disagree": an admin who does not agree with a change
//writes to its author, opens an issue or fixes it.
class SgfReview extends AppModel
{
	public $useTable = 'sgf_review';

	//Ticks the change as checked by the admin, or takes the tick back.
	public static function mark(int $sgfID, int $userID, bool $reviewed): void
	{
		if ($reviewed)
			Util::execute('INSERT IGNORE INTO sgf_review (sgf_id, user_id) VALUES (?, ?)', [$sgfID, $userID]);
		else
			Util::execute('DELETE FROM sgf_review WHERE sgf_id = ? AND user_id = ?', [$sgfID, $userID]);
	}

	//The admins who checked each of the changes, in the order they did, as rows with what an avatar
	//needs (see User::avatarOrNull).
	/**
	 * @param list<int> $sgfIDs
	 * @return array<int, list<array<string, mixed>>> sgf id => reviewers
	 */
	public static function reviewersOf(array $sgfIDs): array
	{
		if (empty($sgfIDs))
			return [];
		$rows = Util::query('SELECT sgf_review.sgf_id, sgf_review.created AS reviewed,
	user.id, user.display_name, user.external_id, user.picture, user.email, user.avatar_setting_id, user.uploaded_avatar_hash, user.deleted
FROM sgf_review
JOIN user ON user.id = sgf_review.user_id
WHERE sgf_review.sgf_id IN (' . implode(',', array_fill(0, count($sgfIDs), '?')) . ')
ORDER BY sgf_review.created, user.id', array_values($sgfIDs));
		$reviewers = [];
		foreach ($rows as $row)
			$reviewers[(int) $row['sgf_id']][] = $row;
		return $reviewers;
	}
}
