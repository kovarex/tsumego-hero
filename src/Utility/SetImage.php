<?php

namespace App\Utility;

use Exception;
use InvalidArgumentException;

//The cover image of a collection. The picture is a row of set_cover, so it is backed up and
//copied with the rest of the database and no folder of the site has to be writable for the web
//server. Its url carries the id of the set and the start of the hash of the picture
//(set.cover_hash): a new picture is a new url, so a browser may keep a cover for a year, and a
//page builds the url from the row of the set without reading the picture.
class SetImage
{
	public const MAX_EDGE = 1600;
	public const QUALITY = 80;
	public const MAX_UPLOAD_BYTES = 2097152;

	//The hex characters of the sha256 of a picture its url carries.
	public const HASH_LENGTH = 16;

	//The path the covers are served from, followed by the id of the set.
	public const URL_PREFIX = '/set-cover/';

	//A cover this much taller than wide (the covers of the hero power collections) sits at
	//the bottom of its cell on the page of the collection.
	public const TALL_RATIO = 1.6;

	//The url a page shows the cover from.
	//$hash: set.cover_hash of the set
	public static function url(int $setId, string $hash): string
	{
		return self::URL_PREFIX . $setId . '/' . $hash . '.webp';
	}

	//Store a picture as the cover of a set, in place of the one it had.
	//$setId: Id of the set the cover belongs to
	//Returns hash of the picture, which is now set.cover_hash
	/** @param array{data: string, hash: string, width: int, height: int} $processed Picture from process() */
	public static function store(int $setId, array $processed): string
	{
		$hash = substr($processed['hash'], 0, self::HASH_LENGTH);
		// Sent as hex, so the bytes of the picture are never read as text of the connection.
		Util::execute(
			'INSERT INTO set_cover (set_id, picture, width, height) VALUES (?, UNHEX(?), ?, ?)
				ON DUPLICATE KEY UPDATE picture = VALUES(picture), width = VALUES(width), height = VALUES(height)',
			[$setId, bin2hex($processed['data']), $processed['width'], $processed['height']]);
		Util::execute('UPDATE `set` SET cover_hash = ? WHERE id = ?', [$hash, $setId]);
		return $hash;
	}

	//Remove the cover of a set.
	public static function remove(int $setId): void
	{
		Util::execute('DELETE FROM set_cover WHERE set_id = ?', [$setId]);
		Util::execute('UPDATE `set` SET cover_hash = NULL WHERE id = ?', [$setId]);
	}

	//The picture an url asks for: the cover of the set, as long as it is still the picture of that hash.
	//Returns WebP data, null when there is no such picture
	public static function picture(int $setId, string $hash): ?string
	{
		$rows = Util::query('
			SELECT set_cover.picture
			FROM set_cover
			JOIN `set` ON `set`.id = set_cover.set_id
			WHERE set_cover.set_id = ? AND `set`.cover_hash = ?', [$setId, $hash]);
		return $rows ? (string) $rows[0]['picture'] : null;
	}

	//The size of the cover of a set, null when it has none.
	/** @return array{width: int, height: int}|null */
	public static function size(int $setId): ?array
	{
		$rows = Util::query('SELECT width, height FROM set_cover WHERE set_id = ?', [$setId]);
		return $rows ? ['width' => (int) $rows[0]['width'], 'height' => (int) $rows[0]['height']] : null;
	}

	//Whether a cover of this size sits at the bottom of its cell (TALL_RATIO)
	/** @param array{width: int, height: int}|null $size */
	public static function isTall(?array $size): bool
	{
		return $size !== null && $size['height'] > self::TALL_RATIO * $size['width'];
	}

	//The extension of the name of an uploaded file, lower case.
	public static function extensionOf(string $fileName): string
	{
		return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	}

	//Why an uploaded file can't be a cover, null when it can.
	/** @param array{name: string, size: int} $upload */
	public static function refusalOf(array $upload): ?string
	{
		if (!in_array(self::extensionOf($upload['name']), ['png', 'jpg', 'jpeg', 'webp']))
			return 'png/jpg/webp allowed.';
		if ($upload['size'] > self::MAX_UPLOAD_BYTES)
			return 'The file is too large (max 2MB).';
		return null;
	}

	//Resize an uploaded image so its longest edge is at most MAX_EDGE and
	//re-encode it as WebP. The original is never stored, which strips EXIF
	//metadata and caps the pixel count (decompression-bomb protection).
	/** @return array{data: string, hash: string, width: int, height: int} */
	public static function process(string $sourcePath, string $ext): array
	{
		if ($ext === 'png')
			$src = imagecreatefrompng($sourcePath);
		elseif ($ext === 'webp')
			$src = imagecreatefromwebp($sourcePath);
		elseif ($ext === 'jpg' || $ext === 'jpeg')
			$src = imagecreatefromjpeg($sourcePath);
		else
			throw new InvalidArgumentException("Unsupported image type: {$ext}");

		if (!$src)
			throw new Exception('Could not read the uploaded image');

		$srcWidth = imagesx($src);
		$srcHeight = imagesy($src);
		$scale = min(1, self::MAX_EDGE / max($srcWidth, $srcHeight));
		$dstWidth = max(1, (int) round($srcWidth * $scale));
		$dstHeight = max(1, (int) round($srcHeight * $scale));

		$dst = imagecreatetruecolor($dstWidth, $dstHeight);
		if ($ext === 'png' || $ext === 'webp')
		{
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
		}
		else
		{
			$white = imagecolorallocate($dst, 255, 255, 255);
			imagefill($dst, 0, 0, $white);
		}
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
		imagedestroy($src);

		ob_start();
		imagewebp($dst, null, self::QUALITY);
		$webp = ob_get_clean();
		imagedestroy($dst);

		if ($webp === false || $webp === '')
			throw new Exception('WebP encoding failed');

		return [
			'data' => $webp,
			'hash' => hash('sha256', $webp),
			'width' => $dstWidth,
			'height' => $dstHeight,
		];
	}
}
