<?php

namespace App\Utility;

use CakeLog;

/**
 * Looks a password up in the Pwned Passwords list.
 *
 * Only the first five characters of the SHA-1 hash leave the server and the rest of
 * the hash is matched here, so the list never learns which password was asked about.
 */
class PwnedPasswordChecker implements PwnedPasswordCheckerInterface
{
	private static ?PwnedPasswordCheckerInterface $testInstance = null;

	private static string $DEFAULT_RANGE_URL = 'https://api.pwnedpasswords.com/range/';

	private static int $TIMEOUT_SECONDS = 2;

	private string $rangeUrl;

	/**
	 * @var callable(string): (string|null)|null Answers the request instead of the network
	 */
	private $rangeFetcher;

	/**
	 * @param string|null $rangeUrl Where the hashes live
	 * @param callable(string): (string|null)|null $rangeFetcher Takes the address, returns what it answers
	 */
	public function __construct(?string $rangeUrl = null, ?callable $rangeFetcher = null)
	{
		$this->rangeUrl = $rangeUrl ?? self::$DEFAULT_RANGE_URL;
		$this->rangeFetcher = $rangeFetcher;
	}

	/**
	 * Answer the password check with the given checker instead of the list, on a test
	 * instance only.
	 */
	public static function useForTests(?PwnedPasswordCheckerInterface $checker): void
	{
		self::$testInstance = Util::isOnTestSite() ? $checker : null;
	}

	public static function testInstance(): ?PwnedPasswordCheckerInterface
	{
		return self::$testInstance;
	}

	/**
	 * The checker a caller should use: the one a test put in place, the fake the test site
	 * answers with, or the real list.
	 */
	public static function create(): PwnedPasswordCheckerInterface
	{
		if (self::$testInstance !== null)
			return self::$testInstance;

		return Util::isOnTestSite() ? new FakePwnedPasswordChecker() : new self();
	}

	public function isBreached(string $password): bool
	{
		$hash = strtoupper(sha1($password));
		$range = $this->fetchRange(substr($hash, 0, 5));
		if ($range === null)
			return false;

		$restOfHash = substr($hash, 5);
		foreach (explode("\n", $range) as $line)
			if (str_starts_with(trim($line), $restOfHash))
				return true;

		return false;
	}

	/**
	 * The block of hashes that share the prefix, or null when the list can not be
	 * reached. A list that does not answer must not keep a player from setting a
	 * password, so the caller reads null as "not known to be breached". A warning is
	 * logged, because a list that is never reached would otherwise be missed.
	 */
	private function fetchRange(string $prefix): ?string
	{
		$url = $this->rangeUrl . $prefix;
		if ($this->rangeFetcher !== null)
			return ($this->rangeFetcher)($url);

		$context = stream_context_create(['http' => ['timeout' => self::$TIMEOUT_SECONDS]]);
		$range = @file_get_contents($url, false, $context);
		if ($range === false)
		{
			CakeLog::write('warning', 'The Pwned Passwords list could not be asked about the hashes starting with ' . $prefix);
			return null;
		}
		return $range;
	}
}
