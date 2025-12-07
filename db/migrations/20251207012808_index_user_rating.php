<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IndexUserRating extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `user` ADD INDEX `rating` (`rating`)");
    }
}
