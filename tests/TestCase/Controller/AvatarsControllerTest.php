<?php

class AvatarsControllerTest extends ControllerTestCase
{
	public function testImageIsAnSvg()
	{
		$result = $this->testAction('/avatar/4242', ['return' => 'contents']);

		$this->assertStringStartsWith('<svg', $result);
		$this->assertEquals('image/svg+xml', $this->controller->response->type());
	}

	public function testTheSameAccountAlwaysGetsTheSameImage()
	{
		$first = $this->testAction('/avatar/4242', ['return' => 'contents']);
		$second = $this->testAction('/avatar/4242', ['return' => 'contents']);

		$this->assertSame($first, $second);
	}

	public function testDifferentAccountsGetDifferentImages()
	{
		$first = $this->testAction('/avatar/1', ['return' => 'contents']);
		$second = $this->testAction('/avatar/2', ['return' => 'contents']);

		$this->assertNotSame($first, $second);
	}

	/**
	 * The browser of a visitor keeps the avatar for a year, so the response has
	 * to say that it will not change.
	 */
	public function testImageIsCachedForALongTime()
	{
		$this->testAction('/avatar/4242', ['return' => 'contents']);

		$headers = $this->controller->response->header();
		$this->assertNotEmpty($headers['Etag']);
		$this->assertStringContainsString('max-age=', $headers['Cache-Control']);
	}

	/**
	 * An avatar is fetched by a plain img tag, so it must not depend on a session
	 * or on the display name gate that sends a flagged player to the rename page.
	 */
	public function testImageLoadsForAVisitorWhoIsNotLoggedIn()
	{
		$result = $this->testAction('/avatar/4242', ['return' => 'contents']);

		$this->assertStringStartsWith('<svg', $result);
	}

	public function testAnIdThatIsNotANumberIsNotFound()
	{
		$this->expectException(NotFoundException::class);

		$this->testAction('/avatars/image/not-a-number', ['return' => 'contents']);
	}
}
