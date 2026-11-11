<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The highest rating of each day a player played in level mode (App\Utility\UserRatingDay), which the rating chart of
//the profile reads instead of grouping the player's tsumego_attempt rows. Filled from now on as attempts are written;
//the days before are filled once after the deploy by bin/cake backfill_rating_days, not here, as grouping the whole
//tsumego_attempt would hold up the deploy.
final class AddUserRatingDay extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE IF NOT EXISTS user_rating_day (
			user_id INT UNSIGNED NOT NULL,
			day DATE NOT NULL COMMENT 'DATE(created) of the attempts, on the server clock',
			rating INT NOT NULL COMMENT 'The highest user_rating of the day''s attempts',
			PRIMARY KEY (user_id, day),
			CONSTRAINT user_rating_day_user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE IF EXISTS user_rating_day');
	}
}
