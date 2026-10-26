<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * tsumego_attempt is the biggest table and every solve writes to it, so each of its indexes costs on
 * every write and takes room in the buffer pool. Of its six secondary indexes:
 *
 * - user_id_index and tsumego_id_index repeat the start of other indexes,
 * - created only served the admin list of the latest attempts, which orders by id now (the same order),
 * - user_rating served nothing,
 * - user_id_and_user_rating only served the highest rating on the profile, which the new column
 *   user.highest_rating keeps instead: raised whenever the rating changes, filled here from the history.
 *
 * They are replaced by (user_id, created), for the history and the graph on the profile, and
 * (tsumego_id, created), for the attempts and the rating graph of a problem; (user_id, tsumego_id) stays
 * for the last attempt of a player on a problem. Both foreign keys keep an index that starts with their
 * column. The indexes are built online (LOCK=NONE), so solving goes on meanwhile.
 *
 * Every step can run again, in case a run stops halfway.
 */
final class ReworkTsumegoAttemptIndexes extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user ADD COLUMN IF NOT EXISTS highest_rating FLOAT NOT NULL DEFAULT 900
			COMMENT 'The highest rating the player ever had, shown on the profile.' AFTER rating");
		$this->execute('UPDATE user SET highest_rating = rating');

		// The attempts store the rating after each change. Read with a plain SELECT, they are not locked,
		// while an UPDATE joined to the same SELECT would lock every one of them until it ended.
		$this->raiseHighestRatings($this->fetchAll('
			SELECT user_id, MAX(user_rating) AS highest_rating
			FROM tsumego_attempt
			WHERE user_rating IS NOT NULL
			GROUP BY user_id'));

		$this->execute('ALTER TABLE tsumego_attempt
			ADD INDEX IF NOT EXISTS user_id_created (user_id, created),
			ADD INDEX IF NOT EXISTS tsumego_id_created (tsumego_id, created),
			DROP INDEX IF EXISTS user_id_index,
			DROP INDEX IF EXISTS tsumego_id_index,
			DROP INDEX IF EXISTS created,
			DROP INDEX IF EXISTS user_rating,
			DROP INDEX IF EXISTS user_id_and_user_rating,
			ALGORITHM=INPLACE, LOCK=NONE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego_attempt
			ADD INDEX IF NOT EXISTS user_id_index (user_id),
			ADD INDEX IF NOT EXISTS tsumego_id_index (tsumego_id),
			ADD INDEX IF NOT EXISTS created (created),
			ADD INDEX IF NOT EXISTS user_rating (user_rating),
			ADD INDEX IF NOT EXISTS user_id_and_user_rating (user_id, user_rating),
			DROP INDEX IF EXISTS user_id_created,
			DROP INDEX IF EXISTS tsumego_id_created,
			ALGORITHM=INPLACE, LOCK=NONE');
		$this->execute('ALTER TABLE user DROP COLUMN IF EXISTS highest_rating');
	}

	/**
	 * @param array<int, array{user_id: int|string, highest_rating: int|string}> $highestRatings
	 */
	private function raiseHighestRatings(array $highestRatings): void
	{
		foreach (array_chunk($highestRatings, 1000) as $chunk)
		{
			$cases = '';
			$userIDs = [];
			foreach ($chunk as $row)
			{
				$cases .= ' WHEN ' . (int) $row['user_id'] . ' THEN ' . (int) $row['highest_rating'];
				$userIDs[] = (int) $row['user_id'];
			}
			$this->execute('UPDATE user SET highest_rating = GREATEST(highest_rating, CASE id' . $cases . ' END)
				WHERE id IN (' . implode(', ', $userIDs) . ')');
		}
	}
}
