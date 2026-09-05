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
}
