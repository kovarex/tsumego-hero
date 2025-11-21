<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReputationPurge extends AbstractMigration
{
    public function up(): void {
		$this->query("DROP TABLE reputation");
    }
}
