<?php

class HtmlSanitizer
{
	/** @var string HTMLPurifier HTML.Allowed whitelist (tag[attr] list). */
	private const HTML_ALLOWED = 'br,a[href],b,i,p,h1,h2,h3,ul,ol,li,img[src|alt],div,span,font[color],table,tr,td,th';

	/** @var \HTMLPurifier_Config|null */
	private static $config = null;

	public static function sanitize(string $html): string
	{
		$purifier = new \HTMLPurifier(self::getConfig());

		return $purifier->purify($html);
	}

	private static function getConfig(): \HTMLPurifier_Config
	{
		if (self::$config === null)
		{
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
			$cachePath = defined('TMP') ? TMP . 'cache' . DS . 'htmlpurifier' : sys_get_temp_dir();
			if (!is_dir($cachePath))
				@mkdir($cachePath, 0777, true);
			$config->set('Cache.SerializerPath', $cachePath);
			self::$config = $config;
		}

		return self::$config;
	}
}
