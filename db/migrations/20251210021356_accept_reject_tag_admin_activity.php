<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AcceptRejectTagAdminActivity extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
			INSERT INTO admin_activity_type (id, name) VALUES
			(" . AdminActivityType::ACCEPT_TAG . ", 'Accept Tag'),
			(" . AdminActivityType::REJECT_TAG . ", 'Reject Tag')");
    }
}
