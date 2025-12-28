<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TagConnectionProposalsIndex extends AbstractMigration
{
    public function up(): void
    {
    	$this->execute("ALTER TABLE `tag_connection` ADD INDEX `index_tag_connection_approved_created_tag` (`approved`, `created`, `tag_id`)");
    }
}
