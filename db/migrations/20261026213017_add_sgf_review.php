<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The admins tick the SGF changes they checked (see SgfReview), so each of them sees what is still
//to check and who of the others checked a change already. The uploads page of the admin area is
//called SGF changes now.
final class AddSgfReview extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `sgf_review` (
  `sgf_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'The admin who checked the version',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sgf_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sgf_review_sgf_id` FOREIGN KEY (`sgf_id`) REFERENCES `sgf` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sgf_review_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("UPDATE `admin_page` SET `name` = 'SGF changes' WHERE `id` = 2;");
	}

	public function down(): void
	{
		$this->execute("UPDATE `admin_page` SET `name` = 'Uploads' WHERE `id` = 2;");
		$this->execute('DROP TABLE `sgf_review`;');
	}
}
