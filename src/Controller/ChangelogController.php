<?php

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
	public function index()
	{
		$this->set('_page', 'changelog');
		$this->set('_title', 'Changelog');

		$entries = [];
		$revision = null;
		$file = ROOT . DS . 'changelog' . DS . 'index.json';
		if (file_exists($file))
		{
			$data = json_decode((string) file_get_contents($file), true) ?: [];
			$entries = $data['entries'] ?? [];
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
		$entries = $data['entries'] ?? [];

		$ts = [];
		foreach ($entries as $entry)
			if (isset($entry['ts']) && is_numeric($entry['ts']))
				$ts[] = (int) $entry['ts'];

		rsort($ts);
		return $ts;
	}
}
