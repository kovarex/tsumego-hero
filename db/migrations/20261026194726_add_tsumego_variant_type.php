<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The type of a tsumego variant comes from tsumego_variant_type (TsumegoVariant::TYPE_ constants)
 * instead of a varchar, correct_answer is the number of the answer instead of a double, and the
 * answers no longer default to '0'; the code fills in what a new variant starts with.
 *
 * A variant of a type no code knows goes, nothing showed it.
 */
final class AddTsumegoVariantType extends AbstractMigration
{
	/** id => [the code in tsumego_variant.type, the name] */
	private const TYPES = [
		1 => ['multiple_choice', 'multiple choice'],
		2 => ['score_estimating', 'score estimating'],
	];

	public function up(): void
	{
		$this->execute("CREATE TABLE tsumego_variant_type (
			id int(10) unsigned NOT NULL,
			name varchar(30) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$rows = [];
		$cases = [];
		foreach (self::TYPES as $id => [$code, $name])
		{
			$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
			$cases[] = 'WHEN ' . $this->getAdapter()->getConnection()->quote($code) . ' THEN ' . $id;
		}
		$this->execute('INSERT INTO tsumego_variant_type (id, name) VALUES ' . implode(', ', $rows));

		$this->execute('ALTER TABLE tsumego_variant ADD tsumego_variant_type_id int(10) unsigned DEFAULT NULL AFTER tsumego_id');
		$this->execute('UPDATE tsumego_variant SET tsumego_variant_type_id = CASE type ' . implode(' ', $cases) . ' END');
		$this->execute('DELETE FROM tsumego_variant WHERE tsumego_variant_type_id IS NULL');
		$this->execute("ALTER TABLE tsumego_variant
			DROP COLUMN type,
			MODIFY tsumego_variant_type_id int(10) unsigned NOT NULL,
			MODIFY correct_answer tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'The number of the right answer of a multiple choice problem.',
			MODIFY answer1 varchar(50) NOT NULL DEFAULT '',
			MODIFY answer2 varchar(50) NOT NULL DEFAULT '',
			MODIFY answer3 varchar(50) NOT NULL DEFAULT '',
			MODIFY answer4 varchar(50) NOT NULL DEFAULT '',
			ADD KEY tsumego_variant_tsumego_variant_type_id (tsumego_variant_type_id),
			ADD CONSTRAINT tsumego_variant_tsumego_variant_type_id FOREIGN KEY (tsumego_variant_type_id) REFERENCES tsumego_variant_type (id) ON UPDATE CASCADE");
	}

	public function down(): void
	{
		$cases = [];
		foreach (self::TYPES as $id => [$code])
			$cases[] = 'WHEN ' . $id . ' THEN ' . $this->getAdapter()->getConnection()->quote($code);
		$this->execute("ALTER TABLE tsumego_variant ADD type varchar(50) NOT NULL DEFAULT '0' AFTER tsumego_id");
		$this->execute('UPDATE tsumego_variant SET type = CASE tsumego_variant_type_id ' . implode(' ', $cases) . ' END');
		$this->execute("ALTER TABLE tsumego_variant
			DROP FOREIGN KEY tsumego_variant_tsumego_variant_type_id,
			DROP KEY tsumego_variant_tsumego_variant_type_id,
			DROP COLUMN tsumego_variant_type_id,
			MODIFY correct_answer double NOT NULL DEFAULT 0 COMMENT 'The number of the right answer of a multiple choice problem.',
			MODIFY answer1 varchar(50) NOT NULL DEFAULT '0',
			MODIFY answer2 varchar(50) NOT NULL DEFAULT '0',
			MODIFY answer3 varchar(50) NOT NULL DEFAULT '0',
			MODIFY answer4 varchar(50) NOT NULL DEFAULT '0'");
		$this->execute('DROP TABLE tsumego_variant_type');
	}
}
