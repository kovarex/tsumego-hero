<?php

use App\Utility\Auth;
use App\Utility\Feature;

class ChangelogController extends AppController
{
	public $helpers = ['Html', 'Form'];

	/**
	 * Renders the public /changelog page. The entries and the deployed revision
	 * come from the generated changelog/index.json (produced by
	 * scripts/changelog-generate.mjs at deploy time).
	 *
	 * @return void
	 */
	public function index(): void
	{
		$this->set('_page', 'changelog');
		$this->set('_title', 'Changelog');

		$entries = [];
		$revision = null;
		$file = ROOT . DS . 'changelog' . DS . 'index.json';
		if (file_exists($file))
		{
			$data = json_decode((string) file_get_contents($file), true) ?: [];
			$entries = self::visibleEntries($data['entries'] ?? []);
			$revision = $data['revision'] ?? null;
		}

		$this->set('revision', $revision);
		$this->set('entries', $entries);
	}

	/**
	 * Timestamps of the changelog entries, newest first. Emitted inline in the
	 * layout so the "What's new" menu badge can count new items in the client
	 * without a round trip on every page load.
	 *
	 * @param string|null $file Changelog index path (overridable for tests)
	 * @return int[]
	 */
	public static function changelogTimestamps(?string $file = null): array
	{
		$file = $file ?? (ROOT . DS . 'changelog' . DS . 'index.json');
		if (!file_exists($file))
			return [];

		$data = json_decode((string) file_get_contents($file), true) ?: [];
		$entries = self::visibleEntries($data['entries'] ?? []);

		$ts = [];
		foreach ($entries as $entry)
			if (isset($entry['ts']) && is_numeric($entry['ts']))
				$ts[] = (int) $entry['ts'];

		rsort($ts);
		return $ts;
	}

	/**
	 * The entries the current visitor may see. An entry behind a flag is not
	 * announced while that flag is live and off, so the timestamps have to be
	 * filtered the same way or the badge would point at an entry that is not
	 * rendered.
	 *
	 * An entry behind a flag is public only while that flag is open to everybody: a
	 * missing row is off, like everywhere else. A feature that ships for good drops
	 * the flag line of its fragment together with its FeatureKey case, and the
	 * generator refuses a flag that FeatureKey does not name. Admins see every entry.
	 *
	 * @param array<int, array<string, mixed>> $entries
	 * @return array<int, array<string, mixed>>
	 */
	private static function visibleEntries(array $entries): array
	{
		if (Auth::isAdmin())
			return $entries;

		return array_values(array_filter($entries, function ($entry) {
			$flag = (string) ($entry['flag'] ?? '');
			return $flag === '' || Feature::enabled($flag);
		}));
	}
}
