<?php

App::uses('HtmlSanitizer', 'Utility');

class HtmlSanitizerTest extends CakeTestCase
{
	public function testSanitizeNeutralizesXssPayloads(): void
	{
		$inputs = [
			'<b onmouseover="alert(1)">hi</b>',
			'<a href="javascript:alert(1)">click</a>',
			'<b>fine</b><script>alert(1)</script>',
		];
		foreach ($inputs as $input)
			$this->assertStringNotContainsString('alert(1)', HtmlSanitizer::sanitize($input));
		$this->assertStringContainsString('<b>hi</b>', HtmlSanitizer::sanitize($inputs[0]));
	}

	public function testSanitizeKeepsAllowedMarkup(): void
	{
		$result = HtmlSanitizer::sanitize('<a href="/sets/1">link</a>');
		$this->assertStringContainsString('href="/sets/1"', $result);

		$headline = HtmlSanitizer::sanitize('<h1 onmouseover="alert(1)">Headline</h1>');
		$this->assertStringContainsString('<h1>Headline</h1>', $headline);
		$this->assertStringNotContainsString('onmouseover', $headline);
	}

	public function testSanitizeProducesBalancedMarkup(): void
	{
		$this->assertSame('<table><tr><td>unclosed</td></tr></table>', HtmlSanitizer::sanitize('<table><tr><td>unclosed'));
	}

	public function testSanitizeStripsExternalImages(): void
	{
		$result = HtmlSanitizer::sanitize('External <img src="https://evil.com/tracker.png"> Internal <img src="/img/ok.png">');

		$this->assertStringNotContainsString('evil.com', $result);
		$this->assertStringContainsString('/img/ok.png', $result);
	}
}
