<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeduplicateTags extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
DELETE tc1
FROM tag_connection tc1
JOIN tag_connection tc2
  ON tc1.tsumego_id = tc2.tsumego_id
 AND tc1.tag_id = tc2.tag_id
 AND tc1.id > tc2.id");
		$this->execute("ALTER TABLE tag_connection ADD UNIQUE INDEX tsumego_id_tag_id (tsumego_id, tag_id)");
    }
}
