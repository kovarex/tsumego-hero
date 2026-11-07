<?php

use App\Utility\Live;

class LiveTest extends AppTestCase
{
	private const string CHANNEL = 'test-live-stamp';

	public function setUp(): void
	{
		parent::setUp();
		@unlink(Live::path(self::CHANNEL));
	}

	public function tearDown(): void
	{
		@unlink(Live::path(self::CHANNEL));
		parent::tearDown();
	}

	//Each bump rewrites the stamp with a new version and when it was written, and leaves no temporary file behind
	public function testABumpRewritesTheStamp(): void
	{
		$this->assertNull(Live::read(self::CHANNEL));
		$before = microtime(true);

		Live::bump(self::CHANNEL);
		$first = Live::read(self::CHANNEL);
		Live::bump(self::CHANNEL);
		$second = Live::read(self::CHANNEL);

		$this->assertSame(1, $first['version']);
		$this->assertGreaterThanOrEqual($before, $first['time']);
		$this->assertSame(2, $second['version']);
		$this->assertNotSame(file_get_contents(Live::path(self::CHANNEL)), json_encode($first));
		$this->assertSame([], glob(Live::DIRECTORY . DS . '.' . self::CHANNEL . '-*'));
		$this->assertSame('0644', substr(sprintf('%o', fileperms(Live::path(self::CHANNEL))), -4));
	}

	//The daily prune takes the stamps no bump touched for a week, and nothing else of the directory
	public function testThePruneTakesOnlyTheStampsOfAWeekAgo(): void
	{
		$now = time();
		$old = 'test-live-old';
		$recent = 'test-live-recent';
		$notAChannel = 'Test_Live.old';
		try
		{
			foreach ([$old, $recent, $notAChannel] as $name)
				file_put_contents(Live::DIRECTORY . DS . $name, '{}');
			touch(Live::path($old), $now - Live::STALE_AFTER_DAYS * 24 * 3600 - 1);
			touch(Live::path($recent), $now - Live::STALE_AFTER_DAYS * 24 * 3600 + 60);
			touch(Live::DIRECTORY . DS . $notAChannel, $now - 30 * 24 * 3600);
			$htaccessTime = filemtime(Live::DIRECTORY . DS . '.htaccess');
			touch(Live::DIRECTORY . DS . '.htaccess', $now - 30 * 24 * 3600);

			$this->assertGreaterThanOrEqual(1, Live::pruneStale($now));

			$this->assertFileDoesNotExist(Live::path($old));
			$this->assertFileExists(Live::path($recent));
			$this->assertFileExists(Live::DIRECTORY . DS . $notAChannel, 'not a channel name');
			$this->assertFileExists(Live::DIRECTORY . DS . '.htaccess');
			touch(Live::DIRECTORY . DS . '.htaccess', $htaccessTime);

			// a stamp gone is a stamp never bumped
			$this->assertNull(Live::read($old));
			Live::bump($old);
			$this->assertSame(1, Live::read($old)['version']);
		}
		finally
		{
			foreach ([$old, $recent, $notAChannel] as $name)
				@unlink(Live::DIRECTORY . DS . $name);
		}
	}

	//A channel is a file name in webroot/live, nothing that could leave it
	public function testAChannelIsAPlainName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Live::bump('../index');
	}

	//The page asks for the stamp as a static file, with an ETag for the 304 of the next ask, which every
	//cache asks for again each time, and a channel never bumped is no error. The tests' Caddy (Caddyfile)
	//serves it as Apache does with webroot/live/.htaccess, from the file system, not through PHP.
	public function testTheStampIsServedAsItIs(): void
	{
		$missing = $this->request('/live/' . self::CHANNEL);
		$this->assertSame(204, $missing['status']);

		Live::bump(self::CHANNEL);
		$response = $this->request('/live/' . self::CHANNEL);
		$this->assertSame(200, $response['status']);
		$this->assertSame(file_get_contents(Live::path(self::CHANNEL)), $response['body']);
		$this->assertSame('no-cache', $response['headers']['cache-control'] ?? null);
		$this->assertSame('nosniff', $response['headers']['x-content-type-options'] ?? null);
		$this->assertStringStartsWith('application/json', $response['headers']['content-type'] ?? '');
		$this->assertArrayHasKey('etag', $response['headers']);
		$this->assertArrayNotHasKey('x-powered-by', $response['headers'], 'not through PHP');
	}

	public function testTheStampDirectoryIsInGitOnlyForItsRules(): void
	{
		$this->assertFileExists(WWW_ROOT . 'live' . DS . '.htaccess');
		$htaccess = file_get_contents(WWW_ROOT . 'live' . DS . '.htaccess');
		$this->assertStringContainsString('Header set Cache-Control "no-cache"', $htaccess);
		$this->assertStringContainsString('php_flag engine off', $htaccess);
		$this->assertStringContainsString('RewriteRule ^ - [R=204,L]', $htaccess);
	}

	//The stamp as the page gets it, through the browser of the tests, which reaches the site
	/** @return array{status: int, headers: array<string, string>, body: string} */
	private function request(string $path): array
	{
		$browser = Browser::instance();
		$browser->get('empty.php');
		return $browser->driver->executeAsyncScript("
			const done = arguments[arguments.length - 1];
			fetch(arguments[0], {cache: 'no-store'}).then(async response => done({
				status: response.status,
				headers: Object.fromEntries(response.headers.entries()),
				body: await response.text()}));", [$path]);
	}
}
