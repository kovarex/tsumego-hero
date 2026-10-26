<?php

use App\Attribute\HttpGet;
use App\Utility\Avatar;
use App\Utility\UploadedAvatar;

/**
 * AvatarsController
 *
 * Serves the board avatar of a player as a square svg. The url carries the key of
 * the display name, which is the whole input of the drawing, so the image endpoint
 * is a pure function of its url: it reads no row of the database, and a list of a
 * thousand players costs no query at all.
 *
 * The url that carries the id of the account instead answers where the board of that
 * account is, which is what a list uses when it knows the player but not the drawing.
 *
 * A board keeps its corners (a circle would cut the pattern off), so the callers
 * pass border-radius:0 for this avatar and keep the round shape for a picture.
 *
 * A picture a player uploaded is served here as well, from the database.
 */
class AvatarsController extends AppController
{
	/**
	 * @param string|null $key The key of a display name
	 * @return CakeResponse svg image, cached for a year
	 */
	#[HttpGet]
	public function image(?string $key = null)
	{
		if ($key === null || preg_match('/^[0-9a-f]{' . Avatar::KEY_LENGTH . '}$/', $key) !== 1)
			throw new NotFoundException('Invalid board key');

		$this->response->type('svg');
		$this->response->header('Cache-Control: public, max-age=31536000, immutable');
		$this->response->etag(md5(Avatar::VERSION . '|' . $key));
		$this->response->body(Avatar::svg($key));

		return $this->response;
	}

	/**
	 * The same board as a png, which is what a page shares: several networks drop
	 * an svg from a share preview. The drawing is a function of the key, so the
	 * picture is still made without reading a row of the database.
	 *
	 * @param string|null $key The key of a display name
	 * @return CakeResponse png image, cached for a year
	 */
	#[HttpGet]
	public function imagePng(?string $key = null)
	{
		if ($key === null || preg_match('/^[0-9a-f]{' . Avatar::KEY_LENGTH . '}$/', $key) !== 1)
			throw new NotFoundException('Invalid board key');

		$this->response->type('png');
		$this->response->header('Cache-Control: public, max-age=31536000, immutable');
		$this->response->etag(md5(Avatar::VERSION . '|png|' . $key));
		$this->response->body(Avatar::png($key));

		return $this->response;
	}

	/**
	 * The picture a player uploaded. The url carries the hash of the picture, so what it
	 * answers never changes and the browser keeps it for a year; the url of a picture that
	 * was replaced or removed, or of a deleted account, answers nothing.
	 *
	 * @param int|null $id User id
	 * @param string|null $hash user.uploaded_avatar_hash
	 * @return CakeResponse webp image, cached for a year
	 */
	#[HttpGet]
	public function picture(?int $id = null, ?string $hash = null)
	{
		$picture = $id === null || $hash === null ? null : UploadedAvatar::picture($id, $hash);
		if ($picture === null)
			throw new NotFoundException('No such picture');

		$this->response->type(['webp' => 'image/webp']);
		$this->response->type('webp');
		$this->response->header('Cache-Control: public, max-age=31536000, immutable');
		$this->response->etag($hash);
		$this->response->body($picture);

		return $this->response;
	}

	/**
	 * The board of an account, addressed by the id of its user. The avatar a player picked
	 * is never answered here, only the board, which is what a picture that stopped working
	 * falls back on. The answer is where that board lives, so the drawing stays on the url
	 * that a browser keeps for a year.
	 *
	 * @param int|null $id User id
	 * @return CakeResponse redirect to the board of the account
	 */
	#[HttpGet]
	public function boardOfUser(?int $id = null)
	{
		$this->loadModel('User');
		$user = $id === null ? null : $this->User->find('first', [
			'fields' => ['User.display_name', 'User.deleted'],
			'conditions' => ['User.id' => $id],
		]);

		if ($user === null || User::isDeleted($user['User']) || empty($user['User']['display_name']))
			throw new NotFoundException('No board for this account');

		// The board of a name is fixed, the name of an account is not, so this is the
		// answer that may not be kept for long.
		$this->response->header('Cache-Control', 'max-age=60');

		return $this->redirect(Avatar::url($user['User']['display_name']));
	}
}
