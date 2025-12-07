<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EloToRating extends AbstractMigration
{
    public function up(): void
    {
		$this->execute('ALTER TABLE tsumego_attempt RENAME COLUMN elo TO user_rating');
		$this->execute('ALTER TABLE tsumego_attempt RENAME COLUMN tsumego_elo TO tsumego_rating');
		$this->execute('ALTER TABLE tsumego_attempt ADD INDEX user_rating (`user_rating`)');
    }
}
