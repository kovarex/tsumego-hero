<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DuplicateTableRemoval extends AbstractMigration
{
    public function change(): void
    {
		$this->execute('DROP TABLE duplicate');
		$this->execute('ALTER TABLE tsumego DROP COLUMN duplicate');
		$this->execute('DELETE FROM admin_activity WHERE type=' . 17); // DUPLICATE_REMOVE
		$this->execute('DELETE FROM admin_activity WHERE type=' . 18); // DUPLICATE_GROUP_CREATE
		$this->execute('DELETE FROM admin_activit_type WHERE id=' . 17);
		$this->execute('DELETE FROM admin_activit_type WHERE id=' . 18);
		$this->execute('DROP TABLE admin_activity_old');
		$this->execute('DROP TABLE comment_backup');
    }
}
