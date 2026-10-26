<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The result of a review of the mistake training comes from mistake_training_result
 * (MistakeTraining::REVIEW_ constants) instead of a letter: mistake_training_review.result becomes
 * mistake_training_result_id and mistake_training_pool.last_review_result becomes
 * last_mistake_training_result_id.
 */
final class AddMistakeTrainingResult extends AbstractMigration
{
	/** id => [the letter, the name] */
	private const RESULTS = [
		1 => ['S', 'solved'],
		2 => ['F', 'missed'],
		3 => ['G', 'graduated'],
	];

	public function up(): void
	{
		$this->execute("CREATE TABLE mistake_training_result (
			id int(10) unsigned NOT NULL,
			name varchar(30) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$rows = [];
		$cases = [];
		foreach (self::RESULTS as $id => [$letter, $name])
		{
			$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
			$cases[] = 'WHEN ' . $this->getAdapter()->getConnection()->quote($letter) . ' THEN ' . $id;
		}
		$this->execute('INSERT INTO mistake_training_result (id, name) VALUES ' . implode(', ', $rows));

		$this->execute('ALTER TABLE mistake_training_review ADD mistake_training_result_id int(10) unsigned DEFAULT NULL AFTER day');
		$this->execute('UPDATE mistake_training_review SET mistake_training_result_id = CASE result ' . implode(' ', $cases) . ' END');
		// every review has one of the three, a row without one would be a broken history
		$this->execute('DELETE FROM mistake_training_review WHERE mistake_training_result_id IS NULL');
		$this->execute('ALTER TABLE mistake_training_review
			DROP COLUMN result,
			MODIFY mistake_training_result_id int(10) unsigned NOT NULL,
			ADD KEY mistake_training_review_mistake_training_result_id (mistake_training_result_id),
			ADD CONSTRAINT mistake_training_review_mistake_training_result_id FOREIGN KEY (mistake_training_result_id) REFERENCES mistake_training_result (id) ON UPDATE CASCADE');

		$this->execute('ALTER TABLE mistake_training_pool ADD last_mistake_training_result_id int(10) unsigned DEFAULT NULL AFTER last_review_day');
		// a pool row without a review keeps NULL, so does one with a letter no code wrote
		$this->execute('UPDATE mistake_training_pool SET last_mistake_training_result_id = CASE last_review_result ' . implode(' ', $cases) . ' END');
		$this->execute('ALTER TABLE mistake_training_pool
			DROP COLUMN last_review_result,
			ADD KEY mistake_training_pool_last_mistake_training_result_id (last_mistake_training_result_id),
			ADD CONSTRAINT mistake_training_pool_last_mistake_training_result_id FOREIGN KEY (last_mistake_training_result_id) REFERENCES mistake_training_result (id) ON UPDATE CASCADE');
	}

	public function down(): void
	{
		$cases = [];
		foreach (self::RESULTS as $id => [$letter])
			$cases[] = 'WHEN ' . $id . ' THEN ' . $this->getAdapter()->getConnection()->quote($letter);

		$this->execute('ALTER TABLE mistake_training_pool ADD last_review_result varchar(1) DEFAULT NULL AFTER last_review_day');
		$this->execute('UPDATE mistake_training_pool SET last_review_result = CASE last_mistake_training_result_id ' . implode(' ', $cases) . ' END');
		$this->execute('ALTER TABLE mistake_training_pool
			DROP FOREIGN KEY mistake_training_pool_last_mistake_training_result_id,
			DROP KEY mistake_training_pool_last_mistake_training_result_id,
			DROP COLUMN last_mistake_training_result_id');

		$this->execute("ALTER TABLE mistake_training_review ADD result varchar(1) NOT NULL DEFAULT '' COMMENT 'S solved, F missed, G graduated' AFTER day");
		$this->execute('UPDATE mistake_training_review SET result = CASE mistake_training_result_id ' . implode(' ', $cases) . ' END');
		$this->execute("ALTER TABLE mistake_training_review
			DROP FOREIGN KEY mistake_training_review_mistake_training_result_id,
			DROP KEY mistake_training_review_mistake_training_result_id,
			DROP COLUMN mistake_training_result_id,
			MODIFY result varchar(1) NOT NULL COMMENT 'S solved, F missed, G graduated'");
		$this->execute('DROP TABLE mistake_training_result');
	}
}
