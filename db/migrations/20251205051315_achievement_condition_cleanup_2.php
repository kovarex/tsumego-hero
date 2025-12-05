<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AchievementConditionCleanup2 extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DELETE achievement_condition.* FROM achievement_condition LEFT JOIN user ON achievement_condition.user_id=user.id WHERE user.id IS NULL");
		$this->execute("ALTER TABLE achievement_condition CHANGE user_id user_id INT UNSIGNED NOT NULL");
		$this->execute("ALTER TABLE achievement_condition ADD CONSTRAINT achievement_condition_user_id FOREIGN KEY (`user_id`) REFERENCES user(id) ON DELETE CASCADE ON UPDATE CASCADE");
		$this->execute("ALTER TABLE achievement_condition ADD INDEX `user_id` (`user_id`)");

		$this->execute("DELETE achievement_condition.* FROM achievement_condition LEFT JOIN `set` ON achievement_condition.set_id = `set`.id WHERE achievement_condition.set_id IS NOT NULL AND `set`.id IS NULL");
		$this->execute("ALTER TABLE achievement_condition CHANGE set_id set_id INT UNSIGNED NULL");
		$this->execute("ALTER TABLE achievement_condition ADD CONSTRAINT achievement_condition_set_id FOREIGN KEY (`set_id`) REFERENCES `set`(id) ON DELETE CASCADE ON UPDATE CASCADE");
		$this->execute("ALTER TABLE achievement_condition ADD INDEX `set_id` (`set_id`)");


		$this->execute("
DELETE ac1
FROM achievement_condition ac1
JOIN achievement_condition ac2
  ON ac1.user_id = ac2.user_id
	AND ac1.set_id = ac2.set_id
	AND ac1.id > ac2.id;  -- delete duplicates with higher id");
		$this->execute("ALTER TABLE achievement_condition ADD UNIQUE `user_id_set_id` (`user_id`, `set_id`)");
    }
}
