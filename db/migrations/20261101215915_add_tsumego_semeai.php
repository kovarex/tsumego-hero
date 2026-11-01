<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The values of a semeai problem move from six columns of tsumego, NULL on every other problem, into
//tsumego_semeai: a row there is what makes a problem a semeai problem, whatever set it is in. Its
//type comes from semeai_type (SemeaiTypeTable::TYPE_ constants, the ids semeai_type had). variance
//moves too: only a semeai problem reads it, the 100 that new sandbox problems got was read by nothing.
//Every row moves as it is: the values stay nullable as they were (NULL means the default, as 0 did
//for variance and the maximum), and a type the explanation doesn't know gets a row "unknown <id>"
//in semeai_type, so no problem is dropped.
final class AddTsumegoSemeai extends AbstractMigration
{
	//id => name, the types the explanation tells apart (webroot/js/multipleChoice.js)
	private const TYPES = [
		1 => 'no eyes, less than 2 inside liberties',
		2 => 'no eyes, 2 or more inside liberties',
		3 => 'eye vs no eye',
		4 => 'same sized big eyes',
		5 => 'big eye vs smaller eye',
		6 => 'small eye vs small eye',
	];

	public function up(): void
	{
		// signed like the old semeai_type column, so any value it held has its row
		$this->execute("CREATE TABLE semeai_type (
			id int(11) NOT NULL,
			name varchar(50) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$rows = [];
		foreach (self::TYPES as $id => $name)
			$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
		$this->execute('INSERT INTO semeai_type (id, name) VALUES ' . implode(', ', $rows));
		$this->execute("INSERT INTO semeai_type (id, name)
			SELECT DISTINCT semeai_type, CONCAT('unknown ', semeai_type) FROM tsumego
			WHERE semeai_type IS NOT NULL AND semeai_type <> 0 AND semeai_type NOT BETWEEN 1 AND " . count(self::TYPES));

		$this->execute("CREATE TABLE tsumego_semeai (
			tsumego_id int(10) unsigned NOT NULL,
			semeai_type_id int(11) NOT NULL,
			liberty_count int(11) DEFAULT NULL COMMENT 'The liberties of each group before the question takes some away, NULL when unknown (no question can be made).',
			minimum_liberties int(11) DEFAULT NULL COMMENT 'The fewest liberties a group keeps, NULL for 1.',
			maximum_liberties int(11) DEFAULT NULL COMMENT 'The most liberties a group keeps, NULL or 0 for liberty_count.',
			variance int(11) DEFAULT NULL COMMENT 'How far apart the liberties of the groups can be, NULL or 0 for maximum minus minimum.',
			inside_liberties int(11) DEFAULT NULL COMMENT 'The shared inside liberties, which the type 2 explanation counts.',
			PRIMARY KEY (tsumego_id),
			KEY tsumego_semeai_semeai_type_id (semeai_type_id),
			CONSTRAINT tsumego_semeai_tsumego_id FOREIGN KEY (tsumego_id) REFERENCES tsumego (id) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT tsumego_semeai_semeai_type_id FOREIGN KEY (semeai_type_id) REFERENCES semeai_type (id) ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute('INSERT INTO tsumego_semeai
			(tsumego_id, semeai_type_id, liberty_count, minimum_liberties, maximum_liberties, variance, inside_liberties)
			SELECT id, semeai_type, liberty_count, minimum_liberties, maximum_liberties, variance, inside_liberties
			FROM tsumego WHERE semeai_type IS NOT NULL AND semeai_type <> 0');

		$this->execute('ALTER TABLE tsumego
			DROP COLUMN semeai_type,
			DROP COLUMN liberty_count,
			DROP COLUMN minimum_liberties,
			DROP COLUMN maximum_liberties,
			DROP COLUMN variance,
			DROP COLUMN inside_liberties');
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE tsumego
			ADD minimum_liberties int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
			ADD maximum_liberties int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
			ADD variance int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
			ADD liberty_count int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
			ADD inside_liberties int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
			ADD semeai_type int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races'");
		$this->execute('UPDATE tsumego JOIN tsumego_semeai semeai ON semeai.tsumego_id = tsumego.id
			SET tsumego.semeai_type = semeai.semeai_type_id,
				tsumego.liberty_count = semeai.liberty_count,
				tsumego.minimum_liberties = semeai.minimum_liberties,
				tsumego.maximum_liberties = semeai.maximum_liberties,
				tsumego.variance = semeai.variance,
				tsumego.inside_liberties = semeai.inside_liberties');
		$this->execute('DROP TABLE tsumego_semeai');
		$this->execute('DROP TABLE semeai_type');
	}
}
