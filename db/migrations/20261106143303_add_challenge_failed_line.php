<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A problem failed or run out of time on in a challenge keeps the line the player played, so the challenge page can
//show where everyone went wrong; the moves are points of the SGF version played. Plain time mode keeps none.
//An earlier version of this change (20261106004605, never on prod) put the line on time_mode_attempt; where it ran,
//its lines of challenge runs move here and its columns go.
final class AddChallengeFailedLine extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE challenge_failed_line (
			time_mode_attempt_id INT UNSIGNED NOT NULL PRIMARY KEY,
			moves VARCHAR(400) NOT NULL
				COMMENT 'The moves from the root to the fail or the time-out, two letters each in the coordinates of sgf_id, tt for a pass',
			sgf_id INT UNSIGNED NULL DEFAULT NULL
				COMMENT 'The SGF version the line was played on',
			CONSTRAINT challenge_failed_line_time_mode_attempt_id FOREIGN KEY (time_mode_attempt_id) REFERENCES time_mode_attempt (id) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT challenge_failed_line_sgf_id FOREIGN KEY (sgf_id) REFERENCES sgf (id) ON DELETE SET NULL ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		if (!$this->table('time_mode_attempt')->hasColumn('failed_line'))
			return;
		$this->execute('INSERT INTO challenge_failed_line (time_mode_attempt_id, moves, sgf_id)
			SELECT a.id, a.failed_line, a.sgf_id
			FROM time_mode_attempt a
			JOIN time_mode_session s ON s.id = a.time_mode_session_id
			WHERE a.failed_line IS NOT NULL AND s.challenge_id IS NOT NULL');
		$this->execute('ALTER TABLE time_mode_attempt DROP FOREIGN KEY time_mode_attempt_sgf_id, DROP COLUMN sgf_id, DROP COLUMN failed_line');
	}

	public function down(): void
	{
		$this->execute('DROP TABLE challenge_failed_line');
	}
}
