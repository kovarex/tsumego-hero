<?php

declare(strict_types=1);

use App\Utility\SetImage;
use Phinx\Migration\AbstractMigration;

//The cover of a set moves from a file to a row of set_cover, as the uploaded avatar did (user_avatar).
//A file left the database blind to which images of webroot/img are covers: set.image named them, but
//only on the server, so the repository couldn't tell a cover of a private set from an image nothing
//uses. A row is backed up and copied with the rest of the database, and the site needs no folder the
//web server writes into (webroot/img/sets/).
//set.cover_hash is the start of the sha256 of the picture: the pages build the url of the cover from
//the row of the set, and a new picture gets a new url.
//The cover of every set is read from the file set.image names, made a WebP no larger than the upload
//makes one (SetImage::process()) and stored. A set whose file is missing or can't be
//read gets no cover, as its page showed a broken image before; each one is printed. set.image stays as
//it was, unread, so the files it names can be told apart from the images of the site when they go.
final class SetCoversInDatabase extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('CREATE TABLE IF NOT EXISTS set_cover (
			set_id int(10) unsigned NOT NULL,
			picture mediumblob NOT NULL,
			width smallint(5) unsigned NOT NULL,
			height smallint(5) unsigned NOT NULL,
			PRIMARY KEY (set_id),
			CONSTRAINT set_cover_set_id FOREIGN KEY (set_id) REFERENCES `set` (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		$this->execute("ALTER TABLE `set` ADD COLUMN IF NOT EXISTS cover_hash char(16) DEFAULT NULL
			COMMENT 'Start of the sha256 of the picture in set_cover, part of the url of the cover' AFTER image");
		$this->execute("ALTER TABLE `set` MODIFY image varchar(50) DEFAULT NULL
			COMMENT 'The file of webroot/img the cover was read from before set_cover; no longer read'");

		$connection = $this->getAdapter()->getConnection();
		$sets = $this->fetchAll("SELECT id, image FROM `set` WHERE image IS NOT NULL AND image <> '' AND cover_hash IS NULL ORDER BY id");
		foreach ($sets as $set)
		{
			$path = self::imageDirectory() . str_replace('/', DIRECTORY_SEPARATOR, (string) $set['image']);
			if (!is_file($path))
			{
				$this->getOutput()->writeln('Set ' . $set['id'] . ': the cover ' . $set['image'] . ' is missing, the set gets none');
				continue;
			}
			// The type the content has, the name of an old file can say another
			$extension = match (@getimagesize($path)[2] ?? null)
			{
				IMAGETYPE_PNG => 'png',
				IMAGETYPE_JPEG => 'jpg',
				IMAGETYPE_WEBP => 'webp',
				default => null,
			};
			try
			{
				if ($extension === null)
					throw new InvalidArgumentException('not a PNG, JPEG or WebP picture');
				$picture = SetImage::process($path, $extension);
			}
			catch (Exception $exception)
			{
				$this->getOutput()->writeln('Set ' . $set['id'] . ': the cover ' . $set['image'] . ' could not be read (' . $exception->getMessage() . '), the set gets none');
				continue;
			}
			$this->execute('INSERT INTO set_cover (set_id, picture, width, height) VALUES ('
				. (int) $set['id'] . ', UNHEX(' . $connection->quote(bin2hex($picture['data'])) . '), '
				. $picture['width'] . ', ' . $picture['height'] . ')');
			$this->execute('UPDATE `set` SET cover_hash = ' . $connection->quote(substr($picture['hash'], 0, SetImage::HASH_LENGTH))
				. ' WHERE id = ' . (int) $set['id']);
		}
	}

	public function down(): void
	{
		$this->execute('DROP TABLE IF EXISTS set_cover');
		$this->execute('ALTER TABLE `set` DROP COLUMN IF EXISTS cover_hash');
		$this->execute('ALTER TABLE `set` MODIFY image varchar(50) DEFAULT NULL');
	}

	//webroot/img/, where the files set.image names are
	public static function imageDirectory(): string
	{
		return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;
	}
}
