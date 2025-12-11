<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OldSetsRemoval extends AbstractMigration
{
    public function change(): void
    {
		$this->execute("DELETE FROM `set` where id IN (159, 161);");
    }
}
