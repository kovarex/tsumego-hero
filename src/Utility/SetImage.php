<?php

namespace App\Utility;

use Exception;
use InvalidArgumentException;
use RuntimeException;

//The cover image of a collection: its file lives under the set it belongs to,
//and every place that reads or writes one asks here where that is.
class SetImage
{
	public const MAX_EDGE = 1600;
	public const QUALITY = 80;

	//Store the picture of a set and answer the name it is known by, relative to
	//the webroot. The name carries a part of the hash of the picture, so a cover
	//never changes under a name that a browser keeps for a year.
	//$setId: Id of the set the cover belongs to
	//Returns name of the cover, relative to the webroot
	/** @param array{data: string, hash: string, width: int, height: int} $processed Picture from process() */
	public static function store(int $setId, array $processed): string
	{
		$folder = self::folderOf($setId);
		if (!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder))
			throw new RuntimeException('The image could not be stored.');

		$image = 'sets/' . $setId . '/' . substr($processed['hash'], 0, 16) . '.webp';
		if (@file_put_contents(self::pathOf($image), $processed['data']) === false)
			throw new RuntimeException('The image could not be stored.');

		return $image;
	}

	//Remove a cover. Anything that is not a cover is left alone: the images of the
	//site itself are shared assets and not uploads.
	//$image: Name of a cover, relative to the webroot
	public static function remove(?string $image): void
	{
		if ($image === null || !str_starts_with($image, 'sets/'))
			return;

		$path = self::pathOf($image);
		if (is_file($path))
			unlink($path);
	}

	//Remove the covers of a set, which is what is left of it after the set itself is gone.
	public static function removeFolder(int $setId): void
	{
		$folder = self::folderOf($setId);
		if (!is_dir($folder))
			return;

		foreach (glob($folder . DS . '*') ?: [] as $file)
			if (is_file($file))
				unlink($file);
		@rmdir($folder);
	}

	//Returns the folder of the covers of a set
	private static function folderOf(int $setId): string
	{
		return WWW_ROOT . 'img' . DS . 'sets' . DS . $setId;
	}

	//$image: Name of a cover, relative to the webroot
	//Returns where that cover is on disk
	private static function pathOf(string $image): string
	{
		return WWW_ROOT . 'img' . DS . str_replace('/', DS, $image);
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
