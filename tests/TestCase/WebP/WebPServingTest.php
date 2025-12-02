<?php

/**
 * Test WebP image serving with Accept header detection.
 *
 * These tests verify that:
 * 1. PNG requests with Accept: image/webp serve WebP files (smaller)
 * 2. PNG requests without webp accept serve original PNG (fallback)
 * 3. JPEG requests with Accept: image/webp serve WebP files
 * 4. Vary: Accept header is included for proper caching
 *
 * Requires Apache with mod_rewrite. Excluded on CI because PHP's built-in server
 * doesn't process .htaccess files. Run locally with DDEV (Apache):
 *   vendor/bin/phpunit --group apache-only
 *
 * @group apache-only
 */

App::uses('Util', 'Utility');

class WebPServingTest extends CakeTestCase
{
	private string $baseUrl;

	public function setUp(): void
	{
		parent::setUp();
		$this->baseUrl = Util::getMyAddress();
	}

	/**
	 * Test that PNG images are served as WebP when browser supports it
	 */
	public function testPngServedAsWebpWhenBrowserSupports(): void
	{
		$response = $this->makeRequest('/img/1001weiqi-home.png', 'image/webp,image/png,*/*');

		$this->assertEquals('image/webp', $response['content_type'], 'Should serve WebP content-type');
		$this->assertLessThan(100000, $response['content_length'], 'WebP should be smaller than 100KB');
		$this->assertStringContainsString('Accept', $response['vary'] ?? '', 'Should have Vary: Accept header');
	}

	/**
	 * Test that PNG images are served as original PNG when browser doesn't support WebP
	 */
	public function testPngServedAsPngWhenBrowserDoesNotSupportWebp(): void
	{
		$response = $this->makeRequest('/img/1001weiqi-home.png', 'image/png,*/*');

		$this->assertEquals('image/png', $response['content_type'], 'Should serve PNG content-type');
		$this->assertGreaterThan(400000, $response['content_length'], 'PNG should be larger than 400KB');
		$this->assertStringContainsString('Accept', $response['vary'] ?? '', 'Should have Vary: Accept header');
	}

	/**
	 * Test that JPEG images are served as WebP when browser supports it
	 */
	public function testJpegServedAsWebpWhenBrowserSupports(): void
	{
		$response = $this->makeRequest('/img/ghost.jpg', 'image/webp,*/*');

		$this->assertEquals('image/webp', $response['content_type'], 'Should serve WebP content-type');
		$this->assertLessThan(150000, $response['content_length'], 'WebP should be smaller than original JPG');
	}

	/**
	 * Test that JPEG images are served as original JPEG when browser doesn't support WebP
	 */
	public function testJpegServedAsJpegWhenBrowserDoesNotSupportWebp(): void
	{
		$response = $this->makeRequest('/img/ghost.jpg', 'image/jpeg,*/*');

		$this->assertEquals('image/jpeg', $response['content_type'], 'Should serve JPEG content-type');
	}

	/**
	 * Test that directly requesting .webp file works
	 */
	public function testDirectWebpRequest(): void
	{
		$response = $this->makeRequest('/img/1001weiqi-home.webp', 'image/webp,*/*');

		$this->assertEquals('image/webp', $response['content_type'], 'Should serve WebP content-type');
	}

	/**
	 * Make an HTTP request and return response headers
	 */
	private function makeRequest(string $path, string $accept): array
	{
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $this->baseUrl . $path,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => true,  // HEAD request, we only need headers
			CURLOPT_HTTPHEADER => ["Accept: $accept"],
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200)
			$this->fail("HTTP request failed with code $httpCode for $path");

		// Parse headers
		$headers = [];
		foreach (explode("\r\n", $response) as $line)
			if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches))
				$headers[strtolower($matches[1])] = $matches[2];

		return [
			'content_type' => $headers['content-type'] ?? null,
			'content_length' => (int) ($headers['content-length'] ?? 0),
			'vary' => $headers['vary'] ?? null,
		];
	}
}
