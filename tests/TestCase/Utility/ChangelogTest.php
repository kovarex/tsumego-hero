<?php

use App\Utility\Changelog;
use Cake\Cache\Cache;

//The timestamps are kept in the cache, which the site has on (the test configuration turns it off)
class ChangelogTest extends AppTestCase
{
	private string $file;

	public function setUp(): void
	{
		parent::setUp();
		Cache::enable();
		$this->file = tempnam(sys_get_temp_dir(), 'changelog_');
	}

	public function tearDown(): void
	{
		Cache::delete('changelog_timestamps', 'long');
		Cache::disable();
		unlink($this->file);
		parent::tearDown();
	}

	private function write(array $timestamps, int $modified): void
	{
		file_put_contents($this->file, json_encode(['entries' => array_map(fn(int $ts) => ['ts' => $ts], $timestamps)]));
		touch($this->file, $modified);
		clearstatcache();
	}

	public function testTheTimestampsOfANewFileReplaceTheCachedOnes(): void
	{
		$this->write([100, 200], 1000);
		$this->assertSame([200, 100], Changelog::timestamps($this->file));

		$this->write([100, 200, 300], 2000);
		$this->assertSame([300, 200, 100], Changelog::timestamps($this->file));
	}

	public function testTheCachedTimestampsAreServedWhileTheFileIsTheSame(): void
	{
		$this->write([100, 200], 1000);
		Changelog::timestamps($this->file);

		file_put_contents($this->file, 'not read again');
		touch($this->file, 1000);
		clearstatcache();
		$this->assertSame([200, 100], Changelog::timestamps($this->file));
	}
}
