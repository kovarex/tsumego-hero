<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveTagTsumego extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DELETE FROM tag_connection WHERE tag_id = 9");
		$this->execute("DELETE FROM tag WHERE id = 9");
    }
}
