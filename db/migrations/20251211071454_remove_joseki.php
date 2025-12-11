<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveJoseki extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE joseki;");
    }
}
