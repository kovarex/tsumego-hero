<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TsumegoAttemptModeRemoval extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DELETE FROM tsumego_attempt WHERE mode=3");
		$this->execute("ALTER TABLE tsumego_attempt DROP COLUMN mode");
    }
}
