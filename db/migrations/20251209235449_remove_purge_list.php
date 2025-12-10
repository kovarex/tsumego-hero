<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemovePurgeList extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE `purge`");
		$this->execute("DROP TABLE `purge_list`");
    }
}
