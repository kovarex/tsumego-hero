<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//Score estimating problems were only in the two collections of them, which are deleted for good, so
//their questions go with the type and the winner column only they used. The problems stay.
final class RemoveScoreEstimating extends AbstractMigration
{
	private const SCORE_ESTIMATING = 2;

	public function up(): void
	{
		$this->execute('DELETE FROM tsumego_variant WHERE tsumego_variant_type_id = ' . self::SCORE_ESTIMATING);
		$this->execute('DELETE FROM tsumego_variant_type WHERE id = ' . self::SCORE_ESTIMATING);
		$this->execute('ALTER TABLE tsumego_variant DROP COLUMN winner');
	}

	//The deleted questions don't come back
	public function down(): void
	{
		$this->execute("ALTER TABLE tsumego_variant ADD winner varchar(500) DEFAULT '0' AFTER answer4");
		$this->execute("INSERT INTO tsumego_variant_type (id, name) VALUES (" . self::SCORE_ESTIMATING . ", 'score estimating')");
	}
}
