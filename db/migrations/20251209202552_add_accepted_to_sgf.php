<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAcceptedToSgf extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `sgf` ADD `accepted` BOOLEAN NOT NULL AFTER `created`");
		$this->execute("UPDATE `sgf` SET accepted = true");
	}
}
