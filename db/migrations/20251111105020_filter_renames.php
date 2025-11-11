<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FilterRenames extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE user_contribution RENAME COLUMN search1 to filtered_sets");
		$this->execute("ALTER TABLE user_contribution RENAME COLUMN search2 to filtered_ranks");
		$this->execute("ALTER TABLE user_contribution RENAME COLUMN search3 to filtered_tags");
		$this->execute("ALTER TABLE user_contribution RENAME COLUMN collectionSize to collection_size");
    }
}
