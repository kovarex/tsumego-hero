<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UndeleteSandboxTsumego extends AbstractMigration
{
    public function up(): void {
		$this->execute("
UPDATE tsumego
JOIN set_connection ON set_connection.tsumego_id = tsumego.id
JOIN `set` ON set_connection.set_id = `set`.id
SET tsumego.deleted = NULL
WHERE `set`.public = 0");
    }
}
