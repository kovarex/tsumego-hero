<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A player can upload a picture of their own as the avatar, next to the board, the Gravatar and the
 * Google picture.
 *
 * The picture is a row of user_avatar, so it is backed up and copied with the rest of the database
 * and the site needs no folder the web server writes into. It stays out of user, whose row every
 * request loads. user.uploaded_avatar_hash is the start of the sha256 of the picture: the lists of
 * players build the url of the picture from it without a join, and a new picture gets a new url.
 */
final class AddUploadedAvatar extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT INTO avatar_setting (id, name) VALUES (4, 'uploaded picture')");
		$this->execute('ALTER TABLE user ADD uploaded_avatar_hash char(16) DEFAULT NULL AFTER avatar_setting_id');
		$this->execute('CREATE TABLE user_avatar (
			user_id int(10) unsigned NOT NULL,
			picture mediumblob NOT NULL,
			PRIMARY KEY (user_id),
			CONSTRAINT user_avatar_user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
	}

	public function down(): void
	{
		$this->execute('DROP TABLE user_avatar');
		$this->execute('UPDATE user SET avatar_setting_id = 1 WHERE avatar_setting_id = 4');
		$this->execute('ALTER TABLE user DROP COLUMN uploaded_avatar_hash');
		$this->execute('DELETE FROM avatar_setting WHERE id = 4');
	}
}
