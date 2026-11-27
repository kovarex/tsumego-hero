<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The pages of a player read their statuses and attempts through indexes that hold the player and the problem, or
//the player and the time, but not what the pages want of the rows: the status, the time of the status, whether an
//attempt was solved. So every row is looked up in the table itself, and the rows of a player lie all over it, as
//they were written over the years between those of the others. A player with 15000 statuses had /sets read 1271
//pages from the disk, the profile 1688 and the settings 1268 when they weren't in memory (10 million statuses);
//with the indexes holding those columns, 128, 210 and 125. On the network disk of the hosting a page read is slow.
//- tsumego_status gets (user_id, tsumego_id, status, updated): the statuses of a page's problems, the solved
//  problems of a player and their progress older than a year read the index alone. The unique key stays as the
//  key; this costs about 24 bytes a status (235 MB for 10 million).
//- tsumego_attempt's (user_id, created) becomes (user_id, created, id, solved): the days on the profile read the
//  index alone, and the solve history still pages through it in its order (created, id), which solved before id
//  would break into a sort of all the player's attempts.
//The indexes are built online (LOCK=NONE), so solving goes on meanwhile; every step can run again.
final class IndexWhatPlayerPagesRead extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE tsumego_status
			ADD INDEX IF NOT EXISTS user_id_tsumego_id_status_updated (user_id, tsumego_id, status, updated),
			ALGORITHM=INPLACE, LOCK=NONE');
		$this->execute('ALTER TABLE tsumego_attempt
			ADD INDEX IF NOT EXISTS user_id_created_id_solved (user_id, created, id, solved),
			ALGORITHM=INPLACE, LOCK=NONE');
		$this->execute('ALTER TABLE tsumego_attempt DROP INDEX IF EXISTS user_id_created, ALGORITHM=INPLACE, LOCK=NONE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego_attempt
			ADD INDEX IF NOT EXISTS user_id_created (user_id, created),
			ALGORITHM=INPLACE, LOCK=NONE');
		$this->execute('ALTER TABLE tsumego_attempt DROP INDEX IF EXISTS user_id_created_id_solved, ALGORITHM=INPLACE, LOCK=NONE');
		$this->execute('ALTER TABLE tsumego_status DROP INDEX IF EXISTS user_id_tsumego_id_status_updated, ALGORITHM=INPLACE, LOCK=NONE');
	}
}
