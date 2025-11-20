<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TsumegoStatusUpdated extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE tsumego_status CHANGE COLUMN created updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
}
