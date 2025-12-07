<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FixRatingsTooHigh extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("UPDATE user SET rating = 2700 WHERE rating > 2900");
    }
}
