<?php

use App\Utility\Avatar;

class AvatarsControllerTest extends ControllerTestCase
{
	public function testImageIsAnSvg()
	{
		$result = $this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);

		$this->assertStringStartsWith('<svg', $result);
		$this->assertEquals('image/svg+xml', $this->controller->response->type());
	}

	/**
	 * The url carries the key the drawing is made of, so the board of a name is
	 * the drawing of the key in its url and nothing else.
	 */
	public function testTheImageIsTheDrawingOfTheKeyInTheUrl()
	{
		$key = Avatar::key('Anna');

		$this->assertSame(Avatar::svg($key), $this->testAction('/board/' . $key, ['return' => 'contents']));
	}

	public function testTheSameNameAlwaysGetsTheSameImage()
	{
		$first = $this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);
		$second = $this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);

		$this->assertSame($first, $second);
	}

	public function testDifferentNamesGetDifferentImages()
	{
		$first = $this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);
		$second = $this->testAction('/board/' . Avatar::key('kovarex'), ['return' => 'contents']);

		$this->assertNotSame($first, $second);
	}

	/**
	 * The endpoint is a function of its url and not of an account, so a board is
	 * drawn for any key: a page does not need a row to show a board of a name.
	 */
	public function testABoardIsDrawnWithoutAnAccountBehindIt()
	{
		$key = Avatar::key('a name no account has');

		$this->assertStringStartsWith('<svg', $this->testAction('/board/' . $key, ['return' => 'contents']));
	}

	/**
	 * The browser of a visitor keeps the avatar for a year, so the response has
	 * to say that it will not change.
	 */
	public function testImageIsCachedForALongTime()
	{
		$this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);

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
		$result = $this->testAction('/board/' . Avatar::key('Anna'), ['return' => 'contents']);

		$this->assertStringStartsWith('<svg', $result);
	}

	public function testAKeyThatIsNotADrawingIsNotFound()
	{
		foreach (['not-a-hex-key', '123', 'ABCDEFABCDEF', 'abcdefabcdef0'] as $key)
		{
			$notFound = false;
			try
			{
				$this->testAction('/avatars/image/' . $key, ['return' => 'contents']);
			}
			catch (NotFoundException $e)
			{
				$notFound = true;
			}
			$this->assertTrue($notFound, 'The key ' . $key . ' was served as a board');
		}
	}

	/**
	 * A list knows the id of every player it shows but not the display name the drawing is
	 * made of, so the board of an account is reachable by its id as well. That is what an
	 * avatar falls back on when a picture of another host is gone.
	 */
	public function testTheBoardOfAnAccountIsReachableByTheUserId()
	{
		$context = new ContextPreparator(['user' => ['name' => 'Anna']]);

		$this->testAction('/avatar/' . $context->user['id'], ['method' => 'get']);

		$this->assertStringContainsString(Avatar::url('DN_Anna'), (string) $this->headers['Location']);
	}

	/**
	 * A deleted account asked to be forgotten, so its board is not drawn, not even by id.
	 */
	public function testADeletedAccountHasNoBoard()
	{
		$context = new ContextPreparator(['user' => ['name' => 'Anna', 'deleted' => true]]);

		$this->expectException(NotFoundException::class);
		$this->testAction('/avatar/' . $context->user['id'], ['method' => 'get']);
	}

	public function testAnUnknownUserIdHasNoBoard()
	{
		$this->expectException(NotFoundException::class);
		$this->testAction('/avatar/999999999', ['method' => 'get']);
	}

	/**
	 * A share preview takes a picture and not an svg, so the board of a key is served
	 * as a png on the same drawing. The url says which of the two it wants.
	 */
	public function testTheBoardIsAlsoServedAsAPng()
	{
		$key = Avatar::key('Anna');

		$result = $this->testAction('/board/' . $key . '.png', ['return' => 'contents']);
		$size = getimagesizefromstring($result);

		$this->assertSame("\x89PNG\r\n\x1a\n", substr($result, 0, 8));
		$this->assertEquals('image/png', $this->controller->response->type());
		$this->assertSame(Avatar::SHARE_SIZE, $size[0]);
		$this->assertSame(Avatar::SHARE_SIZE, $size[1]);
	}

	public function testThePngOfAKeyIsCachedForALongTime()
	{
		$this->testAction('/board/' . Avatar::key('Anna') . '.png', ['return' => 'contents']);

		$headers = $this->controller->response->header();
		$this->assertNotEmpty($headers['Etag']);
		$this->assertStringContainsString('max-age=', $headers['Cache-Control']);
	}

	public function testAPngKeyThatIsNotADrawingIsNotFound()
	{
		$this->expectException(NotFoundException::class);
		$this->testAction('/avatars/imagePng/not-a-hex-key', ['return' => 'contents']);
	}
}
