<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserIdToSet extends AbstractMigration
{
	public function up(): void
	{
		// 1. Add user_id to set and default_set_id to user
		$this->execute("ALTER TABLE `set` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`");
		$this->execute("ALTER TABLE `user` ADD COLUMN `default_set_id` INT UNSIGNED NULL AFTER `rating`");

		// 2. For each user with favorites, create a default "Favorites" set
		//    and migrate their favorites to set_connection.
		//    Skip users who no longer exist in the `user` table.
		$users = $this->fetchAll(
			"SELECT DISTINCT f.user_id, u.name FROM favorite f "
			. "JOIN `user` u ON u.id = f.user_id "
			. "ORDER BY f.user_id"
		);

		foreach ($users as $user)
		{
			$userId = (int) $user['user_id'];
			$name = $user['name'];

			// Create default "Favorites" set
			$escapedName = addslashes($name);
			$this->execute(
				"INSERT INTO `set` (user_id, title, public, image, author, `order`, created) "
				. "VALUES ({$userId}, 'Favorites', 0, NULL, '{$escapedName}', 99999, NOW())"
			);

			// Get the set ID
			$setRow = $this->fetchRow(
				"SELECT id FROM `set` WHERE user_id = {$userId} AND title = 'Favorites' AND public = 0"
			);
			if (!$setRow)
				continue;
			$setId = (int) $setRow['id'];

			// Point user.default_set_id to this set
			$this->execute("UPDATE `user` SET default_set_id = {$setId} WHERE id = {$userId}");

			// Migrate favorites to set_connection
			$this->execute(
				"INSERT INTO `set_connection` (set_id, tsumego_id, num, created) "
				. "SELECT {$setId}, tsumego_id, "
				. "ROW_NUMBER() OVER (ORDER BY created), created "
				. "FROM favorite WHERE user_id = {$userId}"
			);
		}

		// 3. Add FK constraints (only after data is migrated)
		$this->execute(
			"ALTER TABLE `set` ADD FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE"
		);
		$this->execute(
			"ALTER TABLE `user` ADD FOREIGN KEY (`default_set_id`) REFERENCES `set`(`id`) ON DELETE SET NULL"
		);

		// 4. Drop the favorite table
		$this->execute("DROP TABLE `favorite`");
	}

	public function down(): void
	{
		// Recreate favorite table
		$this->execute(
			"CREATE TABLE `favorite` ("
			. "`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, "
			. "`user_id` INT UNSIGNED NOT NULL, "
			. "`tsumego_id` INT UNSIGNED NOT NULL, "
			. "`created` DATETIME NULL DEFAULT NULL, "
			. "PRIMARY KEY (`id`), "
			. "INDEX (`user_id`), "
			. "INDEX (`tsumego_id`)"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);

		// Restore favorites from user-owned sets named "Favorites"
		$sets = $this->fetchAll(
			"SELECT id, user_id FROM `set` WHERE title = 'Favorites' AND public = 0 AND user_id IS NOT NULL"
		);

		foreach ($sets as $set)
		{
			$setId = (int) $set['id'];
			$userId = (int) $set['user_id'];

			$this->execute(
				"INSERT INTO `favorite` (user_id, tsumego_id, created) "
				. "SELECT {$userId}, tsumego_id, created "
				. "FROM `set_connection` WHERE set_id = {$setId}"
			);
		}

		// Remove user-owned sets that were created by this migration
		$this->execute("DELETE FROM `set_connection` WHERE set_id IN "
			. "(SELECT id FROM `set` WHERE title = 'Favorites' AND public = 0 AND user_id IS NOT NULL)");
		$this->execute("DELETE FROM `set` WHERE title = 'Favorites' AND public = 0 AND user_id IS NOT NULL");

		// Remove FKs and columns
		$this->execute("ALTER TABLE `user` DROP FOREIGN KEY `user_ibfk_1`");
		$this->execute("ALTER TABLE `user` DROP COLUMN `default_set_id`");
		$this->execute("ALTER TABLE `set` DROP FOREIGN KEY `set_ibfk_1`");
		$this->execute("ALTER TABLE `set` DROP COLUMN `user_id`");
	}
}
