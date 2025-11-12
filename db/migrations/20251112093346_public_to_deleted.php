<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PublicToDeleted extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE tsumego CHANGE `public` `deleted` BOOL NOT NULL;");
		$this->execute("UPDATE tsumego SET `deleted` = !`deleted`");
		$this->execute("ALTER TABLE tsumego ADD COLUMN delete_date DATETIME NULL DEFAULT NULL;");
		$this->execute("UPDATE tsumego SET delete_date = NOW() WHERE `deleted`=TRUE;");
		$this->execute("ALTER TABLE tsumego DROP COLUMN deleted;");
		$this->execute("ALTER TABLE tsumego RENAME COLUMN delete_date to deleted");
    }
}
