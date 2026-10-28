<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The collections page reads every problem of every official collection with its position in it:
//set_connection by set_id, with num and tsumego_id. The unique key (set_id, tsumego_id) finds the rows
//but not num, so each of them was read from the table as well. This index holds all three, so the
//page reads the index alone. The index is built online (LOCK=NONE).
final class AddSetConnectionSetNumIndex extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE set_connection ADD INDEX IF NOT EXISTS set_id_num_tsumego_id (set_id, num, tsumego_id), ALGORITHM=INPLACE, LOCK=NONE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE set_connection DROP INDEX IF EXISTS set_id_num_tsumego_id, ALGORITHM=INPLACE, LOCK=NONE');
	}
}
