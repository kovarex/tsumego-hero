<?php

namespace App\Utility;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

//The picture a player uploaded as their avatar.
//What the player sends is never stored: it is decoded, cut to the square in its
//middle and drawn again at one fixed size as a WebP. That strips whatever else the
//file carried (EXIF with the place a photo was taken, a script hidden in a comment)
//and gives every uploaded avatar the same small file.
//The picture is a row of user_avatar, so it is backed up with the database and no
//folder of the site has to be writable for the web server. Its url carries the id of
//the account and the start of the hash of the picture (user.uploaded_avatar_hash):
//a new picture is a new url, so the browser may keep a picture for a year, and a
//list of players builds the url from its own row.
class UploadedAvatar
{
	//The side of the stored picture, in pixels: twice the 128 pixels the largest avatar
	//of a page asks for (User::AVATAR_PIXELS_PROFILE), so a share preview stays sharp too.
	public const SIZE = 256;

	//The largest file the upload takes, unless PHP is set to take less (maxBytes()).
	public const MAX_BYTES = 10 * 1024 * 1024;

	//The most pixels a picture may have. A decoded picture takes four bytes a pixel, so
	//this keeps a small file that unpacks into a huge picture (a decompression bomb)
	//from taking the memory of the process. A photo of a phone has 12 megapixels.
	public const MAX_PIXELS = 16_000_000;

	public const QUALITY = 85;

	//The hex characters of the sha256 of a picture its url carries.
	public const HASH_LENGTH = 16;

	//The path the pictures are served from, followed by the id of the account.
	public const URL_PREFIX = '/avatar/';

	//The types of picture the upload takes, which are the ones a browser shows.
	private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

	//The url a page shows the picture from.
	//$hash: user.uploaded_avatar_hash of the account
	public static function url(int $userID, string $hash): string
	{
		return self::URL_PREFIX . $userID . '/' . $hash . '.webp';
	}

	//The picture of an upload form, checked and made into an avatar.
	//Returns WebP data of the avatar
	/**
	 * @param array{tmp_name?: string, error?: int, size?: int}|null $file The entry of $_FILES
	 * @throws InvalidArgumentException With a message for the player when the file can't be an avatar
	 */
	public static function fromUpload(?array $file): string
	{
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		if ($error === UPLOAD_ERR_NO_FILE)
			throw new InvalidArgumentException('Pick a picture to upload first.');
		if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($error === UPLOAD_ERR_OK && ($file['size'] ?? 0) > self::maxBytes()))
			throw new InvalidArgumentException('The picture is too large, the limit is ' . self::maxSize() . '.');
		if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']))
			throw new InvalidArgumentException('The upload failed, please try again.');

		return self::process($file['tmp_name']);
	}

	//The largest file an upload can bring: MAX_BYTES, or less when PHP takes less in a
	//request, so the player is told the limit that holds on this server.
	public static function maxBytes(): int
	{
		$limit = self::MAX_BYTES;
		foreach (['upload_max_filesize', 'post_max_size'] as $setting)
		{
			$bytes = @ini_parse_quantity((string) ini_get($setting));
			if ($bytes > 0)
				$limit = min($limit, $bytes);
		}
		return $limit;
	}

	//Returns the largest file an upload can bring, the way a player reads it
	public static function maxSize(): string
	{
		return round(self::maxBytes() / (1024 * 1024), 1) . ' MB';
	}

	//Make a picture into an avatar: the square in its middle, upright, SIZE pixels a side,
	//as a WebP. The type is read from the content of the file and not from its name.
	//Returns WebP data
	/** @throws InvalidArgumentException With a message for the player when the file can't be an avatar */
	public static function process(string $sourcePath): string
	{
		$info = @getimagesize($sourcePath);
		if ($info === false || !in_array($info[2], self::TYPES, true))
			throw new InvalidArgumentException('The file is not a JPEG, PNG, WebP or GIF picture.');

		[$width, $height, $type] = $info;
		if ($width < 1 || $height < 1)
			throw new InvalidArgumentException('The picture could not be read.');
		if ($width * $height > self::MAX_PIXELS)
			throw new InvalidArgumentException('The picture is too large, the limit is ' . intdiv(self::MAX_PIXELS, 1_000_000) . ' megapixels.');

		$source = match ($type)
		{
			IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
			IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
			IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
			IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
		};
		if (!$source)
			throw new InvalidArgumentException('The picture could not be read.');

		// The header can claim another size than the pixels it decodes into.
		$width = imagesx($source);
		$height = imagesy($source);
		$side = min($width, $height);

		$avatar = imagecreatetruecolor(self::SIZE, self::SIZE);
		imagealphablending($avatar, false);
		imagesavealpha($avatar, true);
		imagefill($avatar, 0, 0, imagecolorallocatealpha($avatar, 0, 0, 0, 127));
		imagecopyresampled($avatar, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);
		imagedestroy($source);

		// The middle square of a picture turned upright is the middle square turned upright,
		// so the small avatar is turned and not the whole photo.
		if ($type === IMAGETYPE_JPEG)
			$avatar = self::turnUpright($avatar, self::exifOrientation($sourcePath));

		ob_start();
		$encoded = imagewebp($avatar, null, self::QUALITY);
		$webp = ob_get_clean();
		imagedestroy($avatar);

		if (!$encoded || $webp === false || $webp === '')
			throw new RuntimeException('The picture could not be encoded.');

		return $webp;
	}

	//Store the avatar of an account, in place of the one it had.
	//$userID: The account the avatar belongs to
	//$webp: Avatar from process()
	//Returns hash of the picture, for user.uploaded_avatar_hash
	public static function store(int $userID, string $webp): string
	{
		// Sent as hex, so the bytes of the picture are never read as text of the connection.
		Util::execute(
			'INSERT INTO user_avatar (user_id, picture) VALUES (?, UNHEX(?)) ON DUPLICATE KEY UPDATE picture = VALUES(picture)',
			[$userID, bin2hex($webp)]);
		return self::hash($webp);
	}

	//Remove the avatar of an account.
	public static function remove(int $userID): void
	{
		Util::execute('DELETE FROM user_avatar WHERE user_id = ?', [$userID]);
	}

	//The picture an url asks for: the avatar of the account, as long as it is still the
	//picture of that hash and the account is not deleted.
	//Returns WebP data, null when there is no such picture
	public static function picture(int $userID, string $hash): ?string
	{
		$rows = Util::query('
			SELECT user_avatar.picture
			FROM user_avatar
			JOIN user ON user.id = user_avatar.user_id
			WHERE user_avatar.user_id = ? AND user.uploaded_avatar_hash = ? AND user.deleted IS NULL', [$userID, $hash]);
		return $rows ? (string) $rows[0]['picture'] : null;
	}

	//The part of the sha256 of a picture its url carries.
	public static function hash(string $webp): string
	{
		return substr(hash('sha256', $webp), 0, self::HASH_LENGTH);
	}

	//How a camera says its photo has to be turned to stand upright (EXIF tag 274), 1 when it says nothing.
	//$path: A JPEG
	private static function exifOrientation(string $path): int
	{
		if (!function_exists('exif_read_data'))
			return 1;
		$exif = @exif_read_data($path);
		return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
	}

	//Turn a picture the way its EXIF orientation says. Rotation in GD is counterclockwise.
	//$orientation: EXIF orientation, 1 to 8
	private static function turnUpright(GdImage $image, int $orientation): GdImage
	{
		$angle = match ($orientation)
		{
			3, 4 => 180,
			5, 6 => 270,
			7, 8 => 90,
			default => 0,
		};
		if ($angle !== 0)
		{
			$turned = imagerotate($image, $angle, 0);
			if ($turned !== false)
			{
				imagedestroy($image);
				$image = $turned;
			}
		}

		// 2, 4, 5 and 7 are the turns of 1, 3, 6 and 8 for a photo that is mirrored as well.
		if (in_array($orientation, [2, 4, 5, 7], true))
			imageflip($image, IMG_FLIP_HORIZONTAL);

		return $image;
	}
}
