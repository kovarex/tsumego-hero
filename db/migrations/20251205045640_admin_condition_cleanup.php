<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AdminConditionCleanup extends AbstractMigration
{
    public function up(): void
    {

		$this->execute("DELETE achievement_status.* FROM achievement_status LEFT JOIN user ON achievement_status.user_id=user.id WHERE user.id IS NULL");
		$this->execute("ALTER TABLE achievement_status CHANGE user_id user_id INT UNSIGNED NOT NULL");
		$this->execute("ALTER TABLE achievement_status ADD CONSTRAINT achievement_status_user_id FOREIGN KEY (`user_id`) REFERENCES user(id) ON DELETE CASCADE ON UPDATE CASCADE");
		$this->execute("ALTER TABLE achievement_status ADD INDEX `user_id` (`user_id`)");

		$this->execute("ALTER TABLE achievement CHANGE id id INT UNSIGNED NOT NULL");

		$this->execute("DELETE achievement_status.* FROM achievement_status LEFT JOIN achievement ON achievement_status.achievement_id=achievement.id WHERE achievement.id IS NULL");
		$this->execute("ALTER TABLE achievement_status CHANGE achievement_id achievement_id INT UNSIGNED NOT NULL");
		$this->execute("ALTER TABLE achievement_status ADD CONSTRAINT achievement_status_achievement_id FOREIGN KEY (`achievement_id`) REFERENCES achievement(id) ON DELETE CASCADE ON UPDATE CASCADE");
		$this->execute("ALTER TABLE achievement_status ADD INDEX `achievement_id` (`achievement_id`)");

		$this->execute("ALTER TABLE achievement_status ADD UNIQUE `user_id_achievement_id` (`user_id`, `achievement_id`)");
	}
}
