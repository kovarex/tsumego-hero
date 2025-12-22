<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendSgfByFirstMoveAndCorrectMoves extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `sgf` ADD `first_move_color` CHAR NULL DEFAULT NULL COMMENT 'The color B or W or N of the first move. N would mean other. Secondary data filled by jobs. It is used to make duplicite position search more effective.'");
		$this->execute("ALTER TABLE `sgf` ADD `correct_moves` VARCHAR(128) NULL DEFAULT NULL COMMENT 'List of pairs of letter coordinates specifying the set of correct first moves. Secondary data filled by jobs. It is used to make duplicite position search more effective.'");
    }
}
