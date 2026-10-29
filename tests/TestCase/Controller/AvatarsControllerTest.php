<?php

use App\Utility\Avatar;

class AvatarsControllerTest extends IntegrationTestCase
{
	public function testImageIsAnSvg()
	{
		$this->get('/board/' . Avatar::key('Anna'));
		$result = $this->view;

		$this->assertStringStartsWith('<svg', $result);
		$this->assertContentType('image/svg+xml');
	}

	//The url carries the key the drawing is made of, so the board of a name is
	//the drawing of the key in its url and nothing else.
	public function testTheImageIsTheDrawingOfTheKeyInTheUrl()
	{
		$key = Avatar::key('Anna');

		$this->get('/board/' . $key);

		$this->assertSame(Avatar::svg($key), $this->view);
	}

	public function testTheSameNameAlwaysGetsTheSameImage()
	{
		$this->get('/board/' . Avatar::key('Anna'));
		$first = $this->view;
		$this->get('/board/' . Avatar::key('Anna'));
		$second = $this->view;

		$this->assertSame($first, $second);
	}

	public function testDifferentNamesGetDifferentImages()
	{
		$this->get('/board/' . Avatar::key('Anna'));
		$first = $this->view;
		$this->get('/board/' . Avatar::key('kovarex'));
		$second = $this->view;

		$this->assertNotSame($first, $second);
	}

	//The endpoint is a function of its url and not of an account, so a board is
	//drawn for any key: a page does not need a row to show a board of a name.
	public function testABoardIsDrawnWithoutAnAccountBehindIt()
	{
		$key = Avatar::key('a name no account has');

		$this->get('/board/' . $key);

		$this->assertStringStartsWith('<svg', $this->view);
	}

	//The browser of a visitor keeps the avatar for a year, so the response has to say that it will not change.
	public function testImageIsCachedForALongTime()
	{
		$this->get('/board/' . Avatar::key('Anna'));

		$headers = $this->_response->header();
		$this->assertNotEmpty($headers['Etag']);
		$this->assertStringContainsString('max-age=', $headers['Cache-Control']);
	}

	//An avatar is fetched by a plain img tag, so it must not depend on a session
	//or on the display name gate that sends a flagged player to the rename page.
	public function testImageLoadsForAVisitorWhoIsNotLoggedIn()
	{
		$this->get('/board/' . Avatar::key('Anna'));
		$result = $this->view;

		$this->assertStringStartsWith('<svg', $result);
	}

	public function testAKeyThatIsNotADrawingIsNotFound()
	{
		foreach (['not-a-hex-key', '123', 'ABCDEFABCDEF', 'abcdefabcdef0'] as $key)
		{
			$notFound = false;
			// The exception itself is what tells the key was refused.
			$this->disableErrorHandlerMiddleware();
			try
			{
				$this->get('/avatars/image/' . $key);
			}
			catch (NotFoundException $e)
			{
				$notFound = true;
			}
			$this->assertTrue($notFound, 'The key ' . $key . ' was served as a board');
		}
	}

	//A list knows the id of every player it shows but not the display name the drawing is
	//made of, so the board of an account is reachable by its id as well. That is what an
	//avatar falls back on when a picture of another host is gone.
	public function testTheBoardOfAnAccountIsReachableByTheUserId()
	{
		$context = new ContextPreparator(['user' => ['name' => 'Anna']]);

		$this->get('/avatar/' . $context->user['id']);

		$this->assertStringContainsString(Avatar::url('DN_Anna'), (string) $this->headerValue('Location'));
	}

	//A deleted account asked to be forgotten, so its board is not drawn, not even by id.
	public function testADeletedAccountHasNoBoard()
	{
		$context = new ContextPreparator(['user' => ['name' => 'Anna', 'deleted' => true]]);

		$this->get('/avatar/' . $context->user['id']);

		$this->assertResponseCode(404);
	}

	//The error page is the site's one, with its header: the board answers without a session, but
	//its error page needs what the layout shows.
	public function testAnUnknownUserIdHasNoBoard()
	{
		$this->get('/avatar/999999999');

		$this->assertResponseCode(404);
		$this->assertResponseContains('Page Not Found');
		$this->assertResponseContains('id="logo1"');
	}

	//A share preview takes a picture and not an svg, so the board of a key is served
	//as a png on the same drawing. The url says which of the two it wants.
	public function testTheBoardIsAlsoServedAsAPng()
	{
		$key = Avatar::key('Anna');

		$this->get('/board/' . $key . '.png');
		$result = $this->view;
		$size = getimagesizefromstring($result);

		$this->assertSame("\x89PNG\r\n\x1a\n", substr($result, 0, 8));
		$this->assertContentType('image/png');
		$this->assertSame(Avatar::SHARE_SIZE, $size[0]);
		$this->assertSame(Avatar::SHARE_SIZE, $size[1]);
	}

	public function testThePngOfAKeyIsCachedForALongTime()
	{
		$this->get('/board/' . Avatar::key('Anna') . '.png');

		$headers = $this->_response->header();
		$this->assertNotEmpty($headers['Etag']);
		$this->assertStringContainsString('max-age=', $headers['Cache-Control']);
	}

	public function testAPngKeyThatIsNotADrawingIsNotFound()
	{
		$this->get('/avatars/imagePng/not-a-hex-key');

		$this->assertResponseCode(404);
	}
}
