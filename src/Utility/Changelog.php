<?php

namespace App\Utility;

//The generated changelog/index.json (produced by scripts/changelog-generate.mjs at deploy time).
class Changelog
{
	//Timestamps of the changelog entries, newest first. Emitted inline in the
	//layout so the "What's new" menu badge can count new items in the client
	//without a round trip on every page load.
	//$file: Changelog index path (overridable for tests)
	/** @return int[] */
	public static function timestamps(?string $file = null): array
	{
		$file = $file ?? (ROOT . DS . 'changelog' . DS . 'index.json');
		if (!file_exists($file))
			return [];

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
