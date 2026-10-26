<?php

namespace App\Utility;

/**
 * The trash of problems and collections.
 *
 * Deleting a problem or a collection of the site only marks it with the time of the deletion
 * (tsumego.deleted, set.deleted). It disappears from the site, an admin can still open it and take
 * it back, and after self::$RETENTION_DAYS the daily cron removes it from the database for good,
 * together with everything that belongs to it.
 *
 * A problem in the trash keeps its place in its collections, so it comes back exactly where it was.
 * A collection in the trash hides the problems that can only be reached through it, and when it is
 * purged, the problems that belong to no other collection of the site are purged with it.
 *
 * Collections a player assembled are not site content and are deleted at once (SetsController::delete).
 */
class Trash
{
	public static int $RETENTION_DAYS = 30;

	public static int $PURGE_BATCH_SIZE = 50;

	/**
	 * When something deleted at the given time leaves the trash for good.
	 */
	public static function purgeDate(string $deleted): string
	{
		return date('Y-m-d H:i:s', strtotime($deleted . ' +' . self::$RETENTION_DAYS . ' days'));
	}

	/**
	 * Moves a problem to the trash. A collection of the site that has no other problem left goes
	 * to the trash with it, so an empty collection does not stay on the site.
	 */
	public static function deleteProblem(int $tsumegoID): void
	{
		$now = date('Y-m-d H:i:s');
		Util::execute('UPDATE tsumego SET deleted = ? WHERE id = ? AND deleted IS NULL', [$now, $tsumegoID]);
		Util::execute('UPDATE `set` s
			JOIN set_connection sc ON sc.set_id = s.id AND sc.tsumego_id = ?
			SET s.deleted = ?
			WHERE s.user_id IS NULL AND s.deleted IS NULL
				AND NOT EXISTS (
					SELECT 1 FROM set_connection other
					JOIN tsumego t ON t.id = other.tsumego_id AND t.deleted IS NULL
					WHERE other.set_id = s.id)', [$tsumegoID, $now]);
	}

	/**
	 * Takes a problem back from the trash. A collection that went to the trash with it, because it
	 * was its last problem, comes back too. A collection deleted on its own stays in the trash.
	 */
	public static function restoreProblem(int $tsumegoID): void
	{
		Util::execute('UPDATE `set` s
			JOIN set_connection sc ON sc.set_id = s.id AND sc.tsumego_id = ?
			JOIN tsumego t ON t.id = sc.tsumego_id
			SET s.deleted = NULL
			WHERE s.user_id IS NULL AND s.deleted = t.deleted', [$tsumegoID]);
		Util::execute('UPDATE tsumego SET deleted = NULL WHERE id = ?', [$tsumegoID]);
	}

	/**
	 * Moves a collection of the site to the trash, with its problems in place.
	 */
	public static function deleteSet(int $setID): void
	{
		Util::execute('UPDATE `set` SET deleted = ? WHERE id = ? AND deleted IS NULL', [date('Y-m-d H:i:s'), $setID]);
	}

	public static function restoreSet(int $setID): void
	{
		Util::execute('UPDATE `set` SET deleted = NULL WHERE id = ?', [$setID]);
	}

	/**
	 * Removes from the database what has been in the trash for longer than self::$RETENTION_DAYS.
	 *
	 * The problems go in small batches, each a statement of its own, as a problem takes the statuses
	 * and attempts of all its players with it. Should the request be cut short, the next run carries
	 * on: the problems of an expired collection are purged before the collection itself, so they
	 * are still found through it.
	 *
	 * @return array{sets: int, problems: int} How many collections and problems were removed
	 */
	public static function purgeExpired(): array
	{
		$limit = date('Y-m-d H:i:s', strtotime('-' . self::$RETENTION_DAYS . ' days'));
		$setIDs = self::ids(Util::query('SELECT id FROM `set` WHERE deleted <= ?', [$limit]), 'id');
		$problemIDs = self::ids(Util::query('SELECT id FROM tsumego WHERE deleted <= ?', [$limit]), 'id');
		if ($setIDs)
		{
			$placeholders = self::placeholders($setIDs);
			// the problems the expired collections leave in no other collection of the site
			$problemIDs = array_values(array_unique(array_merge($problemIDs, self::ids(Util::query(
				'SELECT DISTINCT sc.tsumego_id FROM set_connection sc
				WHERE sc.set_id IN (' . $placeholders . ')
					AND NOT EXISTS (
						SELECT 1 FROM set_connection other
						JOIN `set` s ON s.id = other.set_id AND s.user_id IS NULL
						WHERE other.tsumego_id = sc.tsumego_id AND other.set_id NOT IN (' . $placeholders . '))',
				array_merge($setIDs, $setIDs)), 'tsumego_id'))));
		}

		foreach (array_chunk($problemIDs, self::$PURGE_BATCH_SIZE) as $batch)
			self::purgeProblems($batch);
		if ($setIDs)
			Util::execute('DELETE FROM `set` WHERE id IN (' . self::placeholders($setIDs) . ')', $setIDs);
		foreach ($setIDs as $setID)
			SetImage::removeFolder($setID);
		return ['sets' => count($setIDs), 'problems' => count($problemIDs)];
	}

	/**
	 * @param int[] $tsumegoIDs
	 */
	private static function purgeProblems(array $tsumegoIDs): void
	{
		// everything that belongs to the problem goes with it through the foreign keys (TrashTest makes sure of it)
		Util::execute('DELETE FROM tsumego WHERE id IN (' . self::placeholders($tsumegoIDs) . ')', $tsumegoIDs);
	}

	/**
	 * @return int[]
	 */
	private static function ids(array $rows, string $column): array
	{
		return array_map('intval', array_column($rows, $column));
	}

	private static function placeholders(array $values): string
	{
		return implode(',', array_fill(0, count($values), '?'));
	}
}
