<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTsumegoOfTheDay extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE day_record DROP COLUMN tsumego");
		$this->execute("ALTER TABLE day_record DROP COLUMN newTsumego");
		$this->execute("ALTER TABLE day_record DROP COLUMN userbg");
		$this->execute("ALTER TABLE tsumego_attempt ADD INDEX `created` (`created`)");
    }
}
