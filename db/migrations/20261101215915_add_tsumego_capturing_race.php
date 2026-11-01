<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The values of a capturing race quiz (The Rules of Capturing Races) move from six columns of tsumego,
//NULL on every other problem, into tsumego_capturing_race: a row there is what makes a problem such a
//quiz. Its type comes from capturing_race_type (CapturingRaceTypeTable::TYPE_ constants), and the
//values every quiz reads are NOT NULL. variance moves too: only the quiz reads it, the 100 that new
//sandbox problems got was read by nothing.
//A problem with a type but without a value its quiz needs stops the migration, with the problems named.
final class AddTsumegoCapturingRace extends AbstractMigration
{
	//id => name, the types the quiz explains (webroot/js/multipleChoice.js)
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
		$broken = $this->fetchAll('SELECT id FROM tsumego
			WHERE semeai_type IS NOT NULL AND semeai_type <> 0
			AND (semeai_type NOT BETWEEN 1 AND ' . count(self::TYPES) . ' OR liberty_count IS NULL OR variance IS NULL)');
		if ($broken)
			throw new RuntimeException('Capturing race problems with an unknown type or without liberty_count or variance: '
				. implode(', ', array_column($broken, 'id')));

		$this->execute("CREATE TABLE capturing_race_type (
			id int(10) unsigned NOT NULL,
			name varchar(50) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$rows = [];
		foreach (self::TYPES as $id => $name)
			$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
		$this->execute('INSERT INTO capturing_race_type (id, name) VALUES ' . implode(', ', $rows));

		$this->execute("CREATE TABLE tsumego_capturing_race (
			tsumego_id int(10) unsigned NOT NULL,
			capturing_race_type_id int(10) unsigned NOT NULL,
			liberty_count int(11) NOT NULL COMMENT 'The liberties of each group before the quiz takes some away.',
			minimum_liberties int(11) DEFAULT NULL COMMENT 'The fewest liberties a group keeps, NULL for 1.',
			maximum_liberties int(11) DEFAULT NULL COMMENT 'The most liberties a group keeps, NULL for liberty_count.',
			variance int(11) NOT NULL COMMENT 'How far apart the liberties of the groups can be, 0 for maximum minus minimum.',
			inside_liberties int(11) DEFAULT NULL COMMENT 'The shared inside liberties, which the type 2 explanation counts.',
			PRIMARY KEY (tsumego_id),
			KEY tsumego_capturing_race_capturing_race_type_id (capturing_race_type_id),
			CONSTRAINT tsumego_capturing_race_tsumego_id FOREIGN KEY (tsumego_id) REFERENCES tsumego (id) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT tsumego_capturing_race_capturing_race_type_id FOREIGN KEY (capturing_race_type_id) REFERENCES capturing_race_type (id) ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute('INSERT INTO tsumego_capturing_race
			(tsumego_id, capturing_race_type_id, liberty_count, minimum_liberties, maximum_liberties, variance, inside_liberties)
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
		$this->execute('UPDATE tsumego JOIN tsumego_capturing_race race ON race.tsumego_id = tsumego.id
			SET tsumego.semeai_type = race.capturing_race_type_id,
				tsumego.liberty_count = race.liberty_count,
				tsumego.minimum_liberties = race.minimum_liberties,
				tsumego.maximum_liberties = race.maximum_liberties,
				tsumego.variance = race.variance,
				tsumego.inside_liberties = race.inside_liberties');
		$this->execute('DROP TABLE tsumego_capturing_race');
		$this->execute('DROP TABLE capturing_race_type');
	}
}
