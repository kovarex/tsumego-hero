<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Recounts the completed sets of every user (achievement_condition category 'set').
 *
 * Sets used to be stored in parts of 200 problems, later they were merged into one set each,
 * so a set of 1000 problems only counted once. The sets are now counted in parts of 200 problems
 * again (see SetCompletionCounter), this brings the stored counts of existing users in line.
 *
 * The achievements themselves are unlocked by the regular check on the user's next page load.
 *
 * Phinx runs the whole migration in one transaction, so the order matters for the live site:
 * the slow recount is a plain SELECT, which doesn't lock anything (an INSERT ... SELECT would lock
 * every row it reads and block solves until the end). Only the quick delete and inserts at the
 * end take locks, and those are held just until the commit right after them.
 */
final class RecountCompletedSets extends AbstractMigration
{
	private const int INSERT_BATCH_SIZE = 1000;

	public function up(): void
	{
		// Same partitioning as SetCompletionCounter::$PARTITION_SIZE, hardcoded so the migration
		// does not depend on application code.
		$counts = $this->fetchAll("
			SELECT completed.user_id, COUNT(*) AS completed_sets
			FROM (
				SELECT ts.user_id
				FROM (
					SELECT
						partitioned.tsumego_id,
						partitioned.set_id,
						partitioned.part,
						COUNT(*) OVER (PARTITION BY partitioned.set_id, partitioned.part) AS part_size
					FROM (
						SELECT
							sc.set_id,
							sc.tsumego_id,
							FLOOR((ROW_NUMBER() OVER (PARTITION BY sc.set_id ORDER BY sc.num, sc.tsumego_id) - 1) / 200) AS part
						FROM set_connection sc
						JOIN `set` s ON s.id = sc.set_id AND s.public = 1 AND s.user_id IS NULL
						JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
					) partitioned
				) parts
				JOIN tsumego_status ts ON ts.tsumego_id = parts.tsumego_id AND ts.status IN ('S', 'W', 'C')
				JOIN `user` u ON u.id = ts.user_id
				GROUP BY ts.user_id, parts.set_id, parts.part
				HAVING COUNT(*) = MAX(parts.part_size)
			) completed
			GROUP BY completed.user_id
		");

		$this->execute("DELETE FROM achievement_condition WHERE category = 'set'");
		$rows = array_map(fn($count) => [
			'user_id' => (int) $count['user_id'],
			'category' => 'set',
			'value' => (int) $count['completed_sets'],
		], $counts);
		foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch)
			$this->table('achievement_condition')->insert($batch)->saveData();
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back, the previous counts are not kept.');
	}
}
