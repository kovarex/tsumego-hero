<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTsumegoRd extends AbstractMigration
{
    public function up(): void
    {
		$this->execute('ALTER TABLE tsumego DROP COLUMN rd');
    }
}
