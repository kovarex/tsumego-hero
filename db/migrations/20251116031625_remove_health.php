<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveHealth extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE user DROP COLUMN health");
    }
}
