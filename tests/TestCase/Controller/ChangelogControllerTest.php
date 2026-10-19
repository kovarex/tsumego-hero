<?php

use App\Utility\Feature;
use App\Utility\FeatureKey;

class ChangelogControllerTest extends TestCaseWithAuth
{
	public function tearDown(): void
	{
		ClassRegistry::init('FeatureFlag')->deleteAll(['flag_key' => 'probe']);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		parent::tearDown();
	}

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

	/**
	 * Write a temp changelog index file and return its path.
	 */
	private function fixture(string $json): string
	{
		$path = tempnam(sys_get_temp_dir(), 'changelog_') ?: sys_get_temp_dir() . '/changelog_test.json';
		file_put_contents($path, $json);

		return $path;
	}

	public function testChangelogTimestampsReturnsEmptyWhenFileMissing()
	{
		$this->assertSame([], ChangelogController::changelogTimestamps('/nonexistent/changelog/index.json'));
	}

	public function testChangelogTimestampsFiltersNonNumericAndSortsDescending()
	{
		$path = $this->fixture(json_encode([
			'entries' => [
				['ts' => 100],
				['ts' => 'not-a-number'],
				['ts' => 300],
				['ts' => 200],
				['text' => 'no timestamp'],
			],
		]));

		$this->assertSame([300, 200, 100], ChangelogController::changelogTimestamps($path));
	}

	public function testChangelogTimestampsReturnsEmptyWhenEntriesHaveNoTimestamps()
	{
		$path = $this->fixture(json_encode([
			'entries' => [
				['text' => 'a'],
				['date' => '2026-01-01'],
			],
		]));

		$this->assertSame([], ChangelogController::changelogTimestamps($path));
	}

	public function testChangelogTimestampsReturnsEmptyWhenFileIsNotValidJson()
	{
		$path = $this->fixture('this is not json');

		$this->assertSame([], ChangelogController::changelogTimestamps($path));
	}

	public function testBeforeFilterExposesChangelogTimestampsViewVar()
	{
		$this->testAction('/changelog', ['method' => 'get', 'return' => 'vars']);

		$this->assertArrayHasKey('changelogTimestamps', $this->vars);
		$this->assertIsArray($this->vars['changelogTimestamps']);
	}

	private function fixtureWithFlaggedEntry(string $flag = 'mistake-training'): string
	{
		return $this->fixture(json_encode([
			'entries' => [
				['ts' => 100, 'text' => 'plain entry'],
				['ts' => 200, 'text' => 'flagged entry', 'flag' => $flag],
			],
		]));
	}

	public function testEntriesBehindAnOffFlagAreNotCountedAsNew()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);

		$this->assertSame([100], ChangelogController::changelogTimestamps($this->fixtureWithFlaggedEntry()));
	}

	public function testEntriesBehindAReleasedFlagAreCountedAsNew()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::EVERYONE);

		$this->assertSame([200, 100], ChangelogController::changelogTimestamps($this->fixtureWithFlaggedEntry()));
	}

	/**
	 * A flag without a row is off, like everywhere else, so deleting the row while the
	 * code still checks the flag does not announce the feature it switches off.
	 */
	public function testEntriesBehindAFlagWithoutARowAreHidden()
	{
		ClassRegistry::init('FeatureFlag')->deleteAll(['flag_key' => 'mistake-training']);
		Feature::reset();

		$this->assertSame([100], ChangelogController::changelogTimestamps($this->fixtureWithFlaggedEntry()));
	}

	public function testAdminsStillSeeEntriesBehindAnOffFlag()
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);

		$this->assertSame([200, 100], ChangelogController::changelogTimestamps($this->fixtureWithFlaggedEntry()));
	}
}
