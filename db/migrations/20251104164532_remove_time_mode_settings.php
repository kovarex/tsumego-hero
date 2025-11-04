<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTimeModeSettings extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE time_mode_setting");
    }
}
