<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMissingAdminActivityTypes extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
			(26, 'Delete User'),
			(27, 'SGF Edit'),
			(28, 'Add Tag'),
			(29, 'Accept Proposal'),
			(30, 'Reject Proposal')");
    }
}
