<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//S[undefined] is what besogo wrote for a status it couldn't read (old saves, and the first normalise_sgfs on prod
//from a + an old save had written as a space). It reads as no status, and a save now leaves such a status out, so
//every S[undefined] is removed from the stored SGFs, with the line break besogo wrote before it; nothing else of the
//text changes. The rows come out as the fixed normalise_sgfs writes them.
final class DropUndefinedStatuses extends AbstractMigration
{
	public function up(): void
	{
		$count = (int) $this->fetchRow("SELECT COALESCE(SUM((LENGTH(sgf) - LENGTH(REPLACE(sgf, 'S[undefined]', ''))) / LENGTH('S[undefined]')), 0) AS count
			FROM sgf WHERE sgf LIKE '%S[undefined]%'")['count'];
		$rows = $this->execute("UPDATE sgf SET sgf = REPLACE(REPLACE(REPLACE(sgf, '\\r\\nS[undefined]', ''), '\\nS[undefined]', ''), 'S[undefined]', '')
			WHERE sgf LIKE '%S[undefined]%'");
		$this->getOutput()->writeln("Removed $count S[undefined] from $rows sgf rows");
	}

	//What was removed meant nothing; it isn't put back
	public function down(): void {}
}
