<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveSolved2 extends AbstractMigration
{
    public function change(): void
    {
		$this->execute('ALTER TABLE user DROP COLUMN solved2');
    }
}
