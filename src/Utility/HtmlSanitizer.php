<?php

class HtmlSanitizer
{
	/** @var string HTMLPurifier HTML.Allowed whitelist (tag[attr] list). */
	private const HTML_ALLOWED = 'br,a[href],b,i,p,h1,h2,h3,ul,ol,li,img[src|alt],div,span,font[color],table,tr,td,th';

	/** @var string Cache dir for HTMLPurifier's serialized definitions (CakePHP TMP/cache, always present). */
	private const CACHE_PATH = CACHE . 'htmlpurifier';

	/** @var \HTMLPurifier|null Reused instance; building it is the expensive part. */
	private static $purifier = null;

	public static function sanitize(string $html): string
	{
		return self::purifier()->purify($html);
	}

	private static function purifier(): \HTMLPurifier
	{
		if (self::$purifier === null)
		{
			if (!is_dir(self::CACHE_PATH))
				mkdir(self::CACHE_PATH, 0755, true);

			$config = \HTMLPurifier_Config::createDefault();
			$config->set('HTML.Allowed', self::HTML_ALLOWED);
			// http/https/mailto keep external links working; data: preserves
			// inline data:image imgs (only real jpeg/gif/png pass the data:
			// validator). javascript: is NOT listed, so it is dropped.
			$config->set('URI.AllowedSchemes', [
				'http' => true,
				'https' => true,
				'mailto' => true,
				'data' => true,
			]);
			// External images (hotlinking/tracking) are not allowed; relative and
			// data:image URLs still work. Links to external sites are unaffected.
			$config->set('URI.DisableExternalResources', true);
			$config->set('Cache.SerializerPath', self::CACHE_PATH);
			$config->set('Cache.SerializerPermissions', 0755);
			self::$purifier = new \HTMLPurifier($config);
		}

		return self::$purifier;
	}
}
