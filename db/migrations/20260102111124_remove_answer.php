<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveAnswer extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE answer");
    }
}
