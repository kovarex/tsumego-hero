<?php

namespace App\Utility;

/**
 * Breach check for testing, so no test has to ask the public list.
 *
 * Usage:
 *   $checker = new FakePwnedPasswordChecker();
 *   $checker->addBreachedPassword('password123');
 *   PwnedPasswordChecker::useForTests($checker);
 */
class FakePwnedPasswordChecker implements PwnedPasswordCheckerInterface
{
	/**
	 * @var array<string, true> The passwords this fake calls breached
	 */
	private array $breachedPasswords = [];

	/**
	 * @var array<string> The passwords the site asked about
	 */
	private array $askedPasswords = [];

	public function addBreachedPassword(string $password): void
	{
		$this->breachedPasswords[$password] = true;
	}

	public function isBreached(string $password): bool
	{
		$this->askedPasswords[] = $password;
		return isset($this->breachedPasswords[$password]);
	}

	/**
	 * @return array<string> The passwords the site asked about, in order
	 */
	public function getAskedPasswords(): array
	{
		return $this->askedPasswords;
	}
}
