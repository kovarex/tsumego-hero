<?php

class TsumegoImagesControllerTest extends ControllerTestCase
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

		$result = $this->testAction("/tsumego-image/{$scId}", ['return' => 'contents']);

		// Valid PNG with correct content type
		$this->assertStringStartsWith("\x89PNG", $result);
		$this->assertEquals('image/png', $this->controller->response->type());

		// Cache headers set
		$headers = $this->controller->response->header();
		$this->assertArrayHasKey('Etag', $headers);
		$this->assertNotEmpty($headers['Etag']);
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

		$result1 = $this->testAction("/tsumego-image/{$scId}", ['return' => 'contents']);
		$result2 = $this->testAction("/tsumego-image/{$scId}", ['return' => 'contents']);

		$this->assertStringStartsWith("\x89PNG", $result1);
		$this->assertEquals($result1, $result2, 'Image generation should be deterministic');
	}

	public function testNonexistentSetConnectionThrows404()
	{
		$this->expectException(NotFoundException::class);
		$this->testAction('/tsumego-image/999999', ['return' => 'contents']);
	}
}
