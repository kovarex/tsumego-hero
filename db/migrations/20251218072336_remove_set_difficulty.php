<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveSetDifficulty extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `set` DROP COLUMN difficulty");
	}
}
