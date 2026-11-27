<?php

namespace App\Utility;

use Cake\Core\Configure;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Throwable;

//Where the time of a request goes, measured while it runs and cheap enough to stay on in production: the wait
//before PHP got the request, the start of PHP and the app, the database (the connection and the queries), the
//caches, the calls out of the site (HTTP, mail, node) and the PHP of the page itself. Every response carries the
//numbers in a Server-Timing header, which the Network tab of the browser's developer tools shows under Timing.
//A request slower than SlowLog.thresholdMilliseconds (1000 unless core.local.php sets another) is also written to
//logs/slow.log as one line of JSON, with its slowest queries, which `bin/cake slow_report` sums up (SlowLogReport).
//The middleware calls begin() first and finish() last (App\Middleware\RequestTimingMiddleware); the database
//(App\Database\TimedStatement, MysqlDriver), the caches (TimedFileEngine, OpcacheFileEngine) and the calls out
//(external()) report to it.
class RequestTiming
{
	public const int DEFAULT_THRESHOLD_MILLISECONDS = 1000;

	//The slow log is moved to slow.log.1 at this size, the older ones to .2 and so on, and the oldest kept is .KEPT_FILES
	public const int ROTATE_BYTES = 10 * 1024 * 1024;
	public const int KEPT_FILES = 5;

	//How many of a request's queries a line of the slow log lists, the ones that took the most time together
	public const int LOGGED_QUERIES = 5;

	//The longest SQL text a line of the slow log keeps
	public const int LOGGED_SQL_LENGTH = 400;

	//The distinct SQL texts a request remembers; the queries of a request running more go into the totals only
	private const int REMEMBERED_QUERIES = 500;

	//A wait before PHP longer than this is no wait but a wrong X-Request-Start, in seconds
	private const int LONGEST_QUEUE_SECONDS = 3600;

	//When begin() ran, by hrtime(), null outside of a request
	private static ?int $start = null;

	//Seconds from the start of PHP to begin(): the autoloader, the configuration and the bootstrap of Cake
	private static float $bootSeconds = 0.0;

	//Seconds the request waited after the web server got it and before PHP started, null when the server doesn't say
	private static ?float $queueSeconds = null;

	private static int $connectNanoseconds = 0;
	private static int $queryCount = 0;
	private static int $queryNanoseconds = 0;

	//By SQL text: [how many times it ran, nanoseconds together]
	/** @var array<string, array{0: int, 1: int}> */
	private static array $queries = [];

	private static int $cacheCount = 0;
	private static int $cacheMisses = 0;
	private static int $cacheNanoseconds = 0;
	private static int $externalCount = 0;
	private static int $externalNanoseconds = 0;
	private static bool $finished = false;

	//The start of a request: everything measured before is forgotten
	public static function begin(): void
	{
		self::$start = hrtime(true);
		self::$bootSeconds = 0.0;
		self::$queueSeconds = null;
		self::$connectNanoseconds = 0;
		self::$queryCount = 0;
		self::$queryNanoseconds = 0;
		self::$queries = [];
		self::$cacheCount = 0;
		self::$cacheMisses = 0;
		self::$cacheNanoseconds = 0;
		self::$externalCount = 0;
		self::$externalNanoseconds = 0;
		self::$finished = false;
		// the command line (the tests that dispatch a request) started long before its request did
		if (PHP_SAPI === 'cli' || !isset($_SERVER['REQUEST_TIME_FLOAT']))
			return;
		$phpStart = (float) $_SERVER['REQUEST_TIME_FLOAT'];
		self::$bootSeconds = max(0.0, microtime(true) - $phpStart);
		self::$queueSeconds = self::queueSeconds($phpStart, $_SERVER['HTTP_X_REQUEST_START'] ?? null);
		// a request that PHP ends on a fatal error, its time limit among them, gets no response to finish
		register_shutdown_function(self::finishFatal(...));
	}

	//The wait before PHP, from the time the web server got the request: "t=<microseconds since 1970>", which
	//webroot/.htaccess has Apache send in X-Request-Start
	public static function queueSeconds(float $phpStart, ?string $requestStart): ?float
	{
		if ($requestStart === null || !preg_match('/^t=(\d{16})$/', trim($requestStart), $match))
			return null;
		$queue = $phpStart - (int) $match[1] / 1e6;
		return $queue >= 0 && $queue < self::LONGEST_QUEUE_SECONDS ? $queue : null;
	}

	public static function connected(int $nanoseconds): void
	{
		self::$connectNanoseconds += $nanoseconds;
	}

	public static function query(string $sql, int $nanoseconds): void
	{
		self::$queryCount++;
		self::$queryNanoseconds += $nanoseconds;
		if (isset(self::$queries[$sql]))
		{
			self::$queries[$sql][0]++;
			self::$queries[$sql][1] += $nanoseconds;
		}
		elseif (count(self::$queries) < self::REMEMBERED_QUERIES)
			self::$queries[$sql] = [1, $nanoseconds];
	}

	public static function cache(int $nanoseconds, bool $miss = false): void
	{
		self::$cacheCount++;
		self::$cacheNanoseconds += $nanoseconds;
		if ($miss)
			self::$cacheMisses++;
	}

	//Runs a call out of the site (a request to another site, the mail, node) and adds its time to the request's
	/**
	 * @template T
	 * @param callable(): T $call
	 * @return T
	 */
	public static function external(callable $call): mixed
	{
		$start = hrtime(true);
		try
		{
			return $call();
		}
		finally
		{
			self::$externalCount++;
			self::$externalNanoseconds += hrtime(true) - $start;
		}
	}

	//The end of a request: the response gets its Server-Timing header, and a slow request a line in the slow log
	public static function finish(ResponseInterface $response, string $method): ResponseInterface
	{
		$measured = self::measured();
		self::$finished = true;
		if ($measured === null)
			return $response;
		$response = $response->withHeader('Server-Timing', self::serverTiming($measured));
		if ($measured['total_ms'] >= self::thresholdMilliseconds())
			self::write(self::logEntry($measured, $method, $response->getStatusCode()));
		return $response;
	}

	//A request PHP ended on a fatal error, its time limit among them, never reached finish(), so it is logged here,
	//as a 500 whatever its time
	public static function finishFatal(): void
	{
		if (self::$finished || ($measured = self::measured()) === null)
			return;
		self::$finished = true;
		$error = error_get_last();
		$fatal = $error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true);
		if (!$fatal && $measured['total_ms'] < self::thresholdMilliseconds())
			return;
		$entry = self::logEntry($measured, (string) ($_SERVER['REQUEST_METHOD'] ?? ''), $fatal ? 500 : (int) http_response_code());
		if ($fatal)
			$entry['error'] = mb_strimwidth((string) $error['message'], 0, 300, '…');
		self::write($entry);
	}

	//The milliseconds of the request so far, by part. The parts add up to the total: php is what is left of it.
	/** @return array<string, float|int|null>|null */
	public static function measured(): ?array
	{
		if (self::$start === null)
			return null;
		$milliseconds = fn(float $seconds): float => round($seconds * 1000, 1);
		$queue = self::$queueSeconds ?? 0.0;
		$total = $queue + self::$bootSeconds + (hrtime(true) - self::$start) / 1e9;
		$parts = [
			'queue_ms' => self::$queueSeconds === null ? null : $milliseconds($queue),
			'boot_ms' => $milliseconds(self::$bootSeconds),
			'db_connect_ms' => $milliseconds(self::$connectNanoseconds / 1e9),
			'db_ms' => $milliseconds(self::$queryNanoseconds / 1e9),
			'cache_ms' => $milliseconds(self::$cacheNanoseconds / 1e9),
			'external_ms' => $milliseconds(self::$externalNanoseconds / 1e9),
		];
		return ['total_ms' => $milliseconds($total)] + $parts + [
			'php_ms' => max(0.0, round($milliseconds($total) - array_sum(array_filter($parts)), 1)),
			'db_queries' => self::$queryCount,
			'cache_operations' => self::$cacheCount,
			'cache_misses' => self::$cacheMisses,
			'external_calls' => self::$externalCount,
		];
	}

	//The Server-Timing header of the measured parts, in milliseconds, the ones of no use left out
	/** @param array<string, float|int|null> $measured */
	public static function serverTiming(array $measured): string
	{
		$metrics = ['total;dur=' . $measured['total_ms']];
		if ($measured['queue_ms'] !== null)
			$metrics[] = 'queue;dur=' . $measured['queue_ms'] . ';desc="Waiting for PHP"';
		if ($measured['boot_ms'] > 0)
			$metrics[] = 'boot;dur=' . $measured['boot_ms'] . ';desc="PHP and app start"';
		if ($measured['db_connect_ms'] > 0)
			$metrics[] = 'dbconnect;dur=' . $measured['db_connect_ms'] . ';desc="DB connection"';
		$metrics[] = 'db;dur=' . $measured['db_ms'] . ';desc="' . $measured['db_queries'] . ' queries"';
		if ($measured['cache_operations'] > 0)
			$metrics[] = 'cache;dur=' . $measured['cache_ms'] . ';desc="' . $measured['cache_operations'] . ' operations, ' . $measured['cache_misses'] . ' misses"';
		if ($measured['external_calls'] > 0)
			$metrics[] = 'external;dur=' . $measured['external_ms'] . ';desc="' . $measured['external_calls'] . ' calls out"';
		$metrics[] = 'php;dur=' . $measured['php_ms'] . ';desc="The rest of PHP"';
		return implode(', ', $metrics);
	}

	public static function thresholdMilliseconds(): float
	{
		return (float) (Configure::read('SlowLog.thresholdMilliseconds') ?? self::DEFAULT_THRESHOLD_MILLISECONDS);
	}

	public static function logPath(): string
	{
		return (string) (Configure::read('SlowLog.path') ?? LOGS . 'slow.log');
	}

	//The line of the slow log of the request: what it was (the action, never the address, which can hold a token),
	//who asked (the id only), what it answered, and where its time went
	/**
	 * @param array<string, float|int|null> $measured
	 * @return array<string, mixed>
	 */
	public static function logEntry(array $measured, string $method, int $status): array
	{
		return [
			'time' => gmdate('Y-m-d\TH:i:s\Z'),
			'method' => $method,
			'route' => self::route(),
			'status' => $status,
			'user_id' => Auth::getUserID(),
			'bot' => self::isBot((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
		] + $measured + [
			// the site keeps no PHP session (JwtAuth, CookieFlash), so a started one, and its lock, is news
			'session' => session_status() === PHP_SESSION_ACTIVE,
			'memory_mb' => round(memory_get_peak_usage() / 1048576, 1),
			'queries' => self::slowestQueries(),
		];
	}

	//Whether the user agent is a crawler or a script rather than a browser, which takes the same PHP processes as
	//the players do; only this is kept of it
	public static function isBot(string $userAgent): bool
	{
		return $userAgent === '' || preg_match('/bot\b|bot\/|crawl|spider|slurp|scrape|archiver|facebookexternalhit|python|curl|wget|go-http-client|java\/|okhttp|httpclient|headless/i', $userAgent) === 1;
	}

	//The controller and the action that answered, like "Tsumegos/play", "-" when no route matched
	private static function route(): string
	{
		$request = Router::getRequest();
		$controller = $request?->getParam('controller');
		$action = $request?->getParam('action');
		if (!is_string($controller) || !is_string($action))
			return '-';
		$prefix = $request->getParam('prefix');
		return (is_string($prefix) ? $prefix . '/' : '') . $controller . '/' . $action;
	}

	//The queries of the request that took the most time together, the ones that differ only in their values as one
	/** @return list<array{ms: float, count: int, sql: string}> */
	public static function slowestQueries(): array
	{
		$merged = [];
		foreach (self::$queries as $sql => [$count, $nanoseconds])
		{
			$normalized = self::normalizeSql($sql);
			$merged[$normalized][0] = ($merged[$normalized][0] ?? 0) + $count;
			$merged[$normalized][1] = ($merged[$normalized][1] ?? 0) + $nanoseconds;
		}
		uasort($merged, fn(array $a, array $b) => $b[1] <=> $a[1]);
		$slowest = [];
		foreach (array_slice($merged, 0, self::LOGGED_QUERIES, true) as $sql => [$count, $nanoseconds])
			$slowest[] = ['ms' => round($nanoseconds / 1e6, 1), 'count' => $count, 'sql' => (string) $sql];
		return $slowest;
	}

	//The SQL of a query without the values written into it, which become ?, and a list of them becomes one: the
	//queries that differ only in their values read alike, and no value of a player gets into the log
	public static function normalizeSql(string $sql): string
	{
		$sql = (string) preg_replace([
			"/'(?:[^'\\\\]|\\\\.)*'/s",
			'/"(?:[^"\\\\]|\\\\.)*"/s',
			'/(?<![\w.`:])\d+(?:\.\d+)?(?![\w`])/',
			'/:c\d+/',
		], '?', $sql);
		$sql = (string) preg_replace('/\?(?:\s*,\s*\?)+/', '?, …', $sql);
		$sql = (string) preg_replace('/\s+/', ' ', trim($sql));
		return mb_strimwidth($sql, 0, self::LOGGED_SQL_LENGTH, '…');
	}

	//Appends the line to the slow log, rotated by size. The log must never break the response it is written after,
	//so whatever fails here is left alone; a missing logs/ directory (deploy.sh makes it) means no log.
	/** @param array<string, mixed> $entry */
	public static function write(array $entry): void
	{
		try
		{
			$path = self::logPath();
			if (!is_dir(dirname($path)))
				return;
			clearstatcache(true, $path);
			if (is_file($path) && filesize($path) >= self::ROTATE_BYTES)
				self::rotate($path);
			$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
			if ($line !== false)
				@file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
		}
		catch (Throwable)
		{
		}
	}

	//slow.log becomes slow.log.1, the .1 before it .2, and so on up to .KEPT_FILES, which the one before replaces
	private static function rotate(string $path): void
	{
		for ($index = self::KEPT_FILES - 1; $index >= 1; $index--)
			if (is_file($path . '.' . $index))
				@rename($path . '.' . $index, $path . '.' . ($index + 1));
		@rename($path, $path . '.1');
	}

	//The files of the slow log, the current one first
	/** @return list<string> */
	public static function logFiles(?string $path = null): array
	{
		$path ??= self::logPath();
		$files = [];
		foreach (array_merge([$path], array_map(fn(int $index) => $path . '.' . $index, range(1, self::KEPT_FILES))) as $file)
			if (is_file($file))
				$files[] = $file;
		return $files;
	}
}
