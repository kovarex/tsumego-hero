<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveDifficulty extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE tsumego DROP COLUMN difficulty");
    }
}
