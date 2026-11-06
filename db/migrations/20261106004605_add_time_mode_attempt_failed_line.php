<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A failed or timed out problem of time mode and of a challenge keeps the line the player played, so the
//challenge page can show where everyone went wrong; the moves are points of the SGF version played.
final class AddTimeModeAttemptFailedLine extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE time_mode_attempt
			ADD COLUMN failed_line VARCHAR(400) NULL DEFAULT NULL
				COMMENT 'The moves from the root to the fail or the time-out, two letters each in the coordinates of sgf_id, tt for a pass; null when none was sent',
			ADD COLUMN sgf_id INT UNSIGNED NULL DEFAULT NULL
				COMMENT 'The SGF version the failed line was played on',
			ADD CONSTRAINT time_mode_attempt_sgf_id FOREIGN KEY (sgf_id) REFERENCES sgf (id) ON DELETE SET NULL ON UPDATE CASCADE");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE time_mode_attempt DROP FOREIGN KEY time_mode_attempt_sgf_id, DROP COLUMN sgf_id, DROP COLUMN failed_line');
	}
}
