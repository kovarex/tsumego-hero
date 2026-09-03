<?php

class ChangelogControllerTest extends ControllerTestCase
{
	/**
	 * The public changelog page renders its shell and mounts the React feed.
	 */
	public function testIndexRendersPageShell()
	{
		$result = $this->testAction('/changelog', ['return' => 'view']);

		$this->assertStringContainsString('What\'s new', $result);
		$this->assertStringContainsString('data-changelog-root', $result);
	}

	/**
	 * The entries are embedded as JSON in the mount element's data-props.
	 * This holds whether or not changelog/index.json currently exists.
	 */
	public function testIndexEmbedsEntriesAsJson()
	{
		$result = $this->testAction('/changelog', ['return' => 'view']);

		$this->assertSame(1, preg_match('/data-props="([^"]*)"/', $result, $m), 'data-props attribute should be present');
		$json = html_entity_decode($m[1], ENT_QUOTES);
		$data = json_decode($json, true);

		$this->assertIsArray($data, 'data-props should decode to a JSON object');
		$this->assertArrayHasKey('entries', $data);
		$this->assertIsArray($data['entries'], 'entries should be an array');
	}
}
