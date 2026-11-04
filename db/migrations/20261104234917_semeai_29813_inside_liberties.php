<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//Problem 29813 (a semeai of type 2, no eyes and inside liberties) has 3 inside liberties on its board,
//not the 2 stored. With 3 the type 2 formula answers Seki on its own, which webroot/js/multipleChoice.js
//forced for it by its id before.
final class Semeai29813InsideLiberties extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('UPDATE tsumego_semeai SET inside_liberties = 3 WHERE tsumego_id = 29813');
	}

	public function down(): void
	{
		$this->execute('UPDATE tsumego_semeai SET inside_liberties = 2 WHERE tsumego_id = 29813');
	}
}
