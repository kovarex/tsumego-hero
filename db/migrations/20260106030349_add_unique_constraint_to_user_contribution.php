<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUniqueConstraintToUserContribution extends AbstractMigration
{
	public function change(): void
	{
		// Clean up duplicate user_id records before adding UNIQUE constraint
		// no should exist at this point, but just in case
		$this->execute("
            DELETE t1 FROM user_contribution t1
            INNER JOIN user_contribution t2 
            WHERE t1.user_id = t2.user_id 
            AND t1.id < t2.id
        ");

		$table = $this->table('user_contribution');

		// Add unique constraint to enable atomic INSERT ... ON DUPLICATE KEY UPDATE
		$table->addIndex(['user_id'], ['unique' => true, 'name' => 'user_id_unique'])
			  ->update();
	}
}
