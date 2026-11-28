<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The achievement cards a player earned but has not seen yet. The card of a solve came only with the answer to the
//solve, so one earned just before leaving the page was never shown. A row waits until the browser says the card
//was shown (/achievements/seen), and every page shows the cards still waiting.
//The name and the XP are those of the card: a repeat is named with its count (Superior Accuracy ×6) and pays no XP.
final class AddAchievementPopup extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('CREATE TABLE IF NOT EXISTS achievement_popup (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			user_id int(10) unsigned NOT NULL,
			achievement_id int(10) unsigned NOT NULL,
			name varchar(500) NOT NULL,
			xp int(11) NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT current_timestamp(),
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY achievement_id (achievement_id),
			CONSTRAINT achievement_popup_user_id FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT achievement_popup_achievement_id FOREIGN KEY (achievement_id) REFERENCES achievement (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
	}

	public function down(): void
	{
		$this->execute('DROP TABLE IF EXISTS achievement_popup');
	}
}
