<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Sprint extends AbstractMigration
{
    public function up(): void {
		$this->execute('ALTER TABLE user ADD COLUMN sprint_start DATETIME NULL');
	}
}
