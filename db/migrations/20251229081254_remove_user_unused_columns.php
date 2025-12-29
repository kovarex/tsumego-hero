<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUserUnusedColumns extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE user DROP COLUMN rd");
		$this->execute("ALTER TABLE user DROP COLUMN _sessid");
		$this->execute("ALTER TABLE user DROP COLUMN nextlvl");
    }
}
