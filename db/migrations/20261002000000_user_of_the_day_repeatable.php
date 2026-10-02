<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserOfTheDayRepeatable extends AbstractMigration
{
	public function up(): void
	{
		// User of the Day is repeatable: the achievement value is the number of wins.
		// day_record holds one row per awarded day and is the full history, so it is
		// the source of truth (the cron writes both a day_record and a uotd condition).
		// created is assigned to itself because the column is ON UPDATE
		// current_timestamp(), which would otherwise wipe every unlock date.
		$this->execute("
			UPDATE achievement_status s
			JOIN (SELECT user_id, COUNT(*) AS wins FROM day_record WHERE user_id IS NOT NULL GROUP BY user_id) w
				ON w.user_id = s.user_id
			SET s.value = w.wins, s.created = s.created
			WHERE s.achievement_id = 11");

		// Winners from before the achievement was awarded from data never received it,
		// some of them many times over, so grant it dated to their first win. Days with
		// no winner have a NULL user_id and are not a win for anybody.
		$this->execute("
			INSERT INTO achievement_status (user_id, achievement_id, value, created)
			SELECT d.user_id, 11, COUNT(*), MIN(d.date)
			FROM day_record d
			LEFT JOIN achievement_status s
				ON s.user_id = d.user_id AND s.achievement_id = 11
			WHERE s.id IS NULL AND d.user_id IS NOT NULL
			GROUP BY d.user_id");
	}

	public function down(): void
	{
		// The rows are legitimate awards, there is nothing to undo.
	}
}
