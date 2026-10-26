<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A player can upload a picture of their own as the avatar, next to the board, the Gravatar and the
 * Google picture. The picture is a file under webroot/img/avatars, and user.uploaded_avatar is its
 * name relative to webroot/img, NULL for a player who has none.
 */
final class AddUploadedAvatar extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT INTO avatar_setting (id, name) VALUES (4, 'uploaded picture')");
		$this->execute('ALTER TABLE user ADD uploaded_avatar varchar(64) DEFAULT NULL AFTER avatar_setting_id');
	}

	public function down(): void
	{
		$this->execute('UPDATE user SET avatar_setting_id = 1 WHERE avatar_setting_id = 4');
		$this->execute('ALTER TABLE user DROP COLUMN uploaded_avatar');
		$this->execute('DELETE FROM avatar_setting WHERE id = 4');
	}
}
