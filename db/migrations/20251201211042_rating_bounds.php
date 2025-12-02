<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RatingBounds extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
ALTER TABLE `tsumego`
	ADD `minimum_rating`
		FLOAT
		NULL
		DEFAULT NULL
		COMMENT 'Lower bound of what the rating is allowed to be for this tsumego.'
		AFTER `rating`,
	ADD `maximum_rating`
		FLOAT
		NULL
		DEFAULT NULL
		COMMENT 'Upper bound of what the rating is allowed to be for this tsumego.'
	AFTER `minimum_rating`");
    }
}
