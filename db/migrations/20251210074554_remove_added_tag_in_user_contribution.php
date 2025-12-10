<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveAddedTagInUserContribution extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE user_contribution DROP COLUMN added_tag;");
    }
}
