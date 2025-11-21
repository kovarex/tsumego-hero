<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUserBoard extends AbstractMigration
{
    public function up(): void {
		$this->execute("DROP TABLE user_board");
    }
}
