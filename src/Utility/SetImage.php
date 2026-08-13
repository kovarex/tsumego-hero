<?php

class SetImage
{
	public const MAX_EDGE = 1600;
	public const QUALITY = 80;

	/**
	 * Resize an uploaded image so its longest edge is at most MAX_EDGE and
	 * re-encode it as WebP. The original is never stored, which strips EXIF
	 * metadata and caps the pixel count (decompression-bomb protection).
	 *
	 * @return array{data: string, hash: string, width: int, height: int}
	 */
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
