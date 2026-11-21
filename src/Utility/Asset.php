<?php

namespace App\Utility;

use Cake\Routing\Router;

//The address of an image of webroot with the version of its content after it (/img/hp1.png?v=1a2b3c4d), so a
//browser keeps the image for a year (webroot/.htaccess) and asks for it again exactly when it changed. The
//versions come from the build (scripts/image-versions.mjs, in the Vite manifest); without one, as in a
//checkout never built, the time the file was last changed stands in for it. The scripts of the pages
//have imageUrl() for the same.
class Asset
{
	//Versions computed here, for the images the build didn't see
	/** @var array<string, string> */
	private static array $fileVersions = [];

	//$path: The image's address, from the root of the site: '/img/hp1.png'
	public static function url(string $path): string
	{
		$version = self::version($path);
		return $version === null ? $path : $path . '?v=' . $version;
	}

	//The address with the scheme and the host, for a page elsewhere (og:image)
	public static function fullUrl(string $path): string
	{
		return Router::url(self::url($path), true);
	}

	//The <link rel="preload"> that starts loading an image with the head of the page, before what shows it asks;
	//nothing for an image that isn't there
	public static function preload(string $path): string
	{
		if (!is_file(self::fileOf($path)))
			return '';
		return '<link rel="preload" as="image" href="' . h(self::url($path)) . '">';
	}

	//The <style> with the rules of a stylesheet of webroot whose images get their versions: for the few small
	//sheets outside the bundles, like the texture of the board, whose url("../img/...") would otherwise
	//have none. Inline, it is also one request less in the head.
	//$path: The stylesheet, from the root of the site: '/besogo/css/board-texture1.css'
	public static function inlineStyle(string $path): string
	{
		$file = self::fileOf($path);
		$css = is_file($file) ? (string) file_get_contents($file) : '';
		$directory = dirname($path);
		$css = (string) preg_replace_callback('/url\(\s*(["\']?)([^"\')?#]+)\1\s*\)/', function (array $match) use ($directory): string {
			$imagePath = str_starts_with($match[2], '/') ? $match[2] : self::resolve($directory . '/' . $match[2]);
			return 'url("' . self::url($imagePath) . '")';
		}, $css);
		return '<style>' . $css . '</style>';
	}

	private static function version(string $path): ?string
	{
		$relative = ltrim($path, '/');
		$versions = ViteManifest::imageVersions();
		if (isset($versions[$relative]))
			return $versions[$relative];
		if (array_key_exists($relative, self::$fileVersions))
			return self::$fileVersions[$relative];
		$file = self::fileOf($path);
		$time = is_file($file) ? filemtime($file) : false;
		return self::$fileVersions[$relative] = $time === false ? null : dechex($time);
	}

	private static function fileOf(string $path): string
	{
		return WWW_ROOT . str_replace('/', DS, ltrim($path, '/'));
	}

	//The path without its ../ and ./ parts: '/besogo/css/../img/x.jpg' is '/besogo/img/x.jpg'
	private static function resolve(string $path): string
	{
		$parts = [];
		foreach (explode('/', $path) as $part)
			if ($part === '..')
				array_pop($parts);
			elseif ($part !== '.' && $part !== '')
				$parts[] = $part;
		return '/' . implode('/', $parts);
	}

	//Forgets the versions computed here, for a test that changes an image
	public static function reset(): void
	{
		self::$fileVersions = [];
	}
}
