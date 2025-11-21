<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UniqueDayRecord extends AbstractMigration
{
    public function up(): void {
		$this->execute('ALTER TABLE day_record ADD UNIQUE `date` (`date`)');
    }
}
