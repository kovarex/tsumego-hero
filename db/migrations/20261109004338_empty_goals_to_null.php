<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A problem without a goal of its own has no description (NULL), which means its default goal "<color> to play"
//(App\Utility\ProblemGoal), so the empty and blank descriptions are made NULL
final class EmptyGoalsToNull extends AbstractMigration
{
	public function up(): void
	{
		$rows = $this->execute("UPDATE tsumego SET description = NULL WHERE TRIM(description) = ''");
		$this->getOutput()?->writeln("Made $rows empty descriptions NULL");
	}

	//NULL and empty mean the same goal, so nothing is put back
	public function down(): void {}
}
