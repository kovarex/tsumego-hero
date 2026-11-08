<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//An empty description of a problem means its default goal, "Black to play" or "White to play" for the color to move
//(App\Utility\ProblemGoal), so the descriptions that are just that default are emptied: "Black to play" in any case,
//with or without a full stop, of a problem whose current SGF (the newest accepted version) Black starts, and the same
//for White. A description naming the other color stays, as it says something else, and so does any longer one.
final class ClearDefaultGoals extends AbstractMigration
{
	public function up(): void
	{
		$rows = $this->execute("UPDATE tsumego t
			JOIN sgf s ON s.id = (SELECT MAX(id) FROM sgf WHERE tsumego_id = t.id AND sgf_status_id = 2)
			SET t.description = ''
			WHERE t.deleted IS NULL
				AND s.first_move_color IN ('B', 'W')
				AND LOWER(TRIM(TRAILING '.' FROM TRIM(t.description))) = IF(s.first_move_color = 'W', 'white to play', 'black to play')");
		$this->getOutput()?->writeln("Emptied $rows descriptions that were the default goal of their problem");
	}

	//An emptied description shows the same goal as the text it had, so nothing is put back
	public function down(): void {}
}
