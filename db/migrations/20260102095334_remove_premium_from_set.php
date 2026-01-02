<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemovePremiumFromSet extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `set` DROP COLUMN premium");
		$this->execute("DELETE FROM achievement WHERE id = 100");
    }
}
