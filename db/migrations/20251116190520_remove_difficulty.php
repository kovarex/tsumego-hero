<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveDifficulty extends AbstractMigration
{
    public function up(): void {
		$this->execute("ALTER TABLE tsumego DROP COLUMN difficulty");
		$this->execute("ALTER TABLE `progress_deletion` MODIFY `user_id` INT UNSIGNED NOT NULL");
		$this->execute("DELETE progress_deletion.* from progress_deletion LEFT JOIN user on progress_deletion.user_id=user.id WHERE user.id is NULL");
		$this->execute("ALTER TABLE `progress_deletion` ADD CONSTRAINT `progress_deletion_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");

		$this->execute("ALTER TABLE `progress_deletion` MODIFY `set_id` INT UNSIGNED NOT NULL");
		$this->execute("DELETE progress_deletion.* from progress_deletion LEFT JOIN `set` on progress_deletion.set_id=`set`.id WHERE `set`.id is NULL");
		$this->execute("ALTER TABLE `progress_deletion` ADD CONSTRAINT `progress_deletion_set_id` FOREIGN KEY (`set_id`) REFERENCES `set`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");

		$this->execute("ALTER TABLE `progress_deletion` CHANGE `created` `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}
