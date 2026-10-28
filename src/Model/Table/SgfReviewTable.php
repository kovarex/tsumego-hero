<?php

namespace App\Model\Table;

use Cake\ORM\TableRegistry;

//An admin ticked an SGF change (a version in sgf) as checked, on the SGF changes page of the admin
//area (SgfChangesRenderer). There is no "disagree": an admin who does not agree with a change
//writes to its author, opens an issue or fixes it.
class SgfReviewTable extends AppTable
{
	//The columns of the user a row of reviewersOf has, what an avatar needs (see User::avatarOrNull)
	private const REVIEWER_FIELDS = ['id', 'display_name', 'external_id', 'picture', 'email', 'avatar_setting_id', 'uploaded_avatar_hash', 'deleted'];

	//Ticks the change as checked by the admin, or takes the tick back.
	public static function mark(int $sgfID, int $userID, bool $reviewed): void
	{
		$sgfReviewTable = TableRegistry::getTableLocator()->get('SgfReview');
		$key = ['sgf_id' => $sgfID, 'user_id' => $userID];
		if (!$reviewed)
			$sgfReviewTable->deleteAll($key);
		elseif (!$sgfReviewTable->exists($key))
			$sgfReviewTable->saveOrFail($sgfReviewTable->newEntity($key));
	}

	//The admins who checked each of the changes, in the order they did, as rows of the user's
	//REVIEWER_FIELDS with sgf_id and reviewed, when they ticked it.
	/**
	 * @param list<int> $sgfIDs
	 * @return array<int, list<array<string, mixed>>> sgf id => reviewers
	 */
	public static function reviewersOf(array $sgfIDs): array
	{
		if (empty($sgfIDs))
			return [];
		$rows = TableRegistry::getTableLocator()->get('SgfReview')->find()
			->select(['sgf_id' => 'SgfReview.sgf_id', 'reviewed' => 'SgfReview.created'])
			->select(array_combine(self::REVIEWER_FIELDS, array_map(fn($field) => 'user.' . $field, self::REVIEWER_FIELDS)))
			->innerJoin(['user' => 'user'], ['user.id = SgfReview.user_id'])
			->where(['SgfReview.sgf_id IN' => $sgfIDs])
			->orderBy(['SgfReview.created' => 'ASC', 'user.id' => 'ASC'])
			->disableHydration()
			->toArray();
		$reviewers = [];
		foreach ($rows as $row)
			$reviewers[(int) $row['sgf_id']][] = $row;
		return $reviewers;
	}
}
