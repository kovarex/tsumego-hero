<?php

class TsumegoImagesControllerTest extends IntegrationTestCase
{
	public function testReturnsValidPngWithCorrectHeaders()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][ed]AW[dc][ec];B[cd];W[cc])',
				'sets' => [['name' => 'Test Life & Death', 'num' => 1]],
				'status' => 'S',
			],
		]);
		$scId = $context->tsumegos[0]['set-connections'][0]['id'];

		$this->get("/tsumego-image/{$scId}");

		// Valid PNG with correct content type
		$this->assertStringStartsWith("\x89PNG", $this->view);
		$this->assertContentType('image/png');

		// The image is cacheable, so a browser can keep it
		$this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', (string) $this->headerValue('Etag'));
	}

	public function testRepeatedRequestsReturnSameImage()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][ed]AW[dc][ec];B[cd];W[cc])',
				'sets' => [['name' => 'Determinism Test Set', 'num' => 1]],
				'status' => 'S',
			],
		]);
		$scId = $context->tsumegos[0]['set-connections'][0]['id'];

		$this->get("/tsumego-image/{$scId}");
		$result1 = $this->view;
		$this->get("/tsumego-image/{$scId}");
		$result2 = $this->view;

		$this->assertStringStartsWith("\x89PNG", $result1);
		$this->assertEquals($result1, $result2, 'Image generation should be deterministic');
	}

	public function testNonexistentSetConnectionThrows404()
	{
		$this->get('/tsumego-image/999999');

		$this->assertResponseCode(404);
	}
}
