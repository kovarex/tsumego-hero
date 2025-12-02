<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
require_once __DIR__ . '/../../src/Model/AdminActivityType.php';

final class TsumegoEditAdminActivities extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
			INSERT INTO admin_activity_type (id, name) VALUES
			(" . AdminActivityType::AUTHOR_EDIT . ", 'Author Edit'),
			(" . AdminActivityType::RATING_EDIT . ", 'Rating Edit'),
			(" . AdminActivityType::MINIMUM_RATING_EDIT . ", 'Minimum Rating Edit'),
			(" . AdminActivityType::MAXIMUM_RATING_EDIT . ", 'Maximum Rating Edit')");
    }
}
