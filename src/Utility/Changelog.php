<?php

namespace App\Utility;

use Cake\Cache\Cache;

//The generated changelog/index.json (produced by scripts/changelog-generate.mjs at deploy time).
class Changelog
{
	private const CACHE_KEY = 'changelog_timestamps';

	//Timestamps of the changelog entries, newest first. Emitted inline in the
	//layout so the "What's new" menu badge can count new items in the client
	//without a round trip on every page load. Every page shows them and the file changes only with a
	//deploy, so they are kept in the cache with the time the file was written.
	//$file: Changelog index path (overridable for tests)
	/** @return int[] */
	public static function timestamps(?string $file = null): array
	{
		$file = $file ?? (ROOT . DS . 'changelog' . DS . 'index.json');
		if (!file_exists($file))
			return [];

		$modified = filemtime($file);
		$cached = Cache::read(self::CACHE_KEY, 'long');
		if (is_array($cached) && $cached['file'] === $file && $cached['modified'] === $modified)
			return $cached['timestamps'];

		$timestamps = self::read($file);
		Cache::write(self::CACHE_KEY, ['file' => $file, 'modified' => $modified, 'timestamps' => $timestamps], 'long');
		return $timestamps;
	}

	/** @return int[] */
	private static function read(string $file): array
	{
		$data = json_decode((string) file_get_contents($file), true) ?: [];
		$entries = $data['entries'] ?? [];

		$timestamps = [];
		foreach ($entries as $entry)
			if (isset($entry['ts']) && is_numeric($entry['ts']))
				$timestamps[] = (int) $entry['ts'];

		rsort($timestamps);
		return $timestamps;
	}
}
