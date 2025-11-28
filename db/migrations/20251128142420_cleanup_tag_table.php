<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CleanupTagTable extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE tag rename tag_connection");

		$this->execute("DELETE tag_connection.* FROM `tag_connection` LEFT JOIN tsumego ON tag_connection.tsumego_id=tsumego.id WHERE tsumego.id IS NULL");
		$this->execute("ALTER TABLE `tag_connection` CHANGE `tsumego_id` `tsumego_id` INT UNSIGNED NOT NULL");
		$this->execute("ALTER TABLE `tag_connection` ADD CONSTRAINT `tag_connection_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");

		$this->execute("UPDATE tag_connection LEFT JOIN user ON tag_connection.user_id = user.id SET tag_connection.user_id = NULL WHERE user.id IS NULL");
		$this->execute("ALTER TABLE `tag_connection` CHANGE `user_id` `user_id` INT UNSIGNED NULL DEFAULT NULL");
		$this->execute("ALTER TABLE `tag_connection` ADD CONSTRAINT `tag_connection_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ");

		$this->execute("ALTER TABLE `tag_connection` CHANGE `created` `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");

		$this->execute("ALTER TABLE tag_name rename tag");
		$this->execute("ALTER TABLE `tag_connection` CHANGE `tag_name_id` `tag_id` INT UNSIGNED NOT NULL");
		$this->execute("DELETE tag_connection.* FROM `tag_connection` LEFT JOIN tag ON tag_connection.tag_id=tag.id WHERE tag.id IS NULL");
		$this->execute("ALTER TABLE `tag` CHANGE `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
		$this->execute("ALTER TABLE `tag_connection` ADD CONSTRAINT `tag_connection_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
   }
}
