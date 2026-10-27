<?php

use App\Utility\PwnedPasswordChecker;

//The list of breached passwords is a service on the internet: the answers it gives
//decide which passwords are refused, and a list that does not answer has to let
//the player through instead of holding the sign up.
class PwnedPasswordCheckerTest extends CakeTestCase
{
	//The rest of the SHA-1 of "password123", the part the list holds next to a count.
	private const KNOWN_PASSWORD_REST_OF_HASH = 'C6008F9CAB4083784CBD1874F76618D2A97';

	public function testAPasswordInTheBlockIsCalledBreached(): void
	{
		$checker = $this->checkerAnswering("0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n"
			. self::KNOWN_PASSWORD_REST_OF_HASH . ":10445\r\n");

		$this->assertTrue($checker->isBreached('password123'));
	}

	public function testAPasswordThatIsNotInTheBlockIsNotCalledBreached(): void
	{
		$checker = $this->checkerAnswering("0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n"
			. "1E4C9B93F3F0682250B6CF8331B7EE68FD8:3\r\n");

		$this->assertFalse($checker->isBreached('password123'));
	}

	public function testOnlyTheStartOfTheHashIsAskedFor(): void
	{
		$askedUrl = null;
		$checker = new PwnedPasswordChecker(null, function (string $url) use (&$askedUrl) {
			$askedUrl = $url;
			return '';
		});

		$checker->isBreached('password123');

		$this->assertSame('https://api.pwnedpasswords.com/range/CBFDA', $askedUrl);
	}

	public function testAListThatCannotBeReachedLetsThePasswordThrough(): void
	{
		$checker = new PwnedPasswordChecker('http://127.0.0.1:1/range/');

		$this->assertFalse($checker->isBreached('password123'));
	}

	//A checker whose list answers with the given block of hashes.
	private function checkerAnswering(string $block): PwnedPasswordChecker
	{
		return new PwnedPasswordChecker(null, fn(string $url) => $block);
	}
}
