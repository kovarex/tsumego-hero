<?php

use PHPUnit\Framework\AssertionFailedError;

//getStringDom() parses a page as a browser does and fails the test on invalid markup, which a
//browser would repair by guessing.
class PageMarkupTest extends IntegrationTestCase
{
	private static function page(string $body): string
	{
		return "<!DOCTYPE html>\n<html>\n<head><title>Page</title></head>\n<body>\n" . $body . "\n</body>\n</html>";
	}

	public function testAValidPageParses(): void
	{
		$this->view = self::page('<div><b>12 Problems</b><br></div>');

		$this->assertSame('12 Problems', $this->getStringDom()->querySelector('b')->textContent);
	}

	public function testAnInvalidPageFailsWithTheErrorAndItsLine(): void
	{
		$this->view = self::page("<div>\n<b>12 Problems<br></div>");

		try
		{
			$this->getStringDom();
		}
		catch (AssertionFailedError $failure)
		{
			$this->assertStringContainsString('unexpected-element-in-open-elements-stack', $failure->getMessage());
			$this->assertStringContainsString('line: 6', $failure->getMessage());
			$this->assertStringContainsString('<b>12 Problems<br></div>', $failure->getMessage());
			return;
		}
		$this->fail('A page with an unclosed <b> parsed without an error.');
	}
}
