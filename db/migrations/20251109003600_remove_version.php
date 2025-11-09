<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveVersion extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE sgf DROP COLUMN version");
    }
}
