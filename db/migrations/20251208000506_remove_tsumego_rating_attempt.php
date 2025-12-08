<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTsumegoRatingAttempt extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE tsumego_rating_attempt");
    }
}
