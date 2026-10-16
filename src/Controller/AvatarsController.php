<?php

use App\Utility\Avatar;

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
 */
class AvatarsController extends AppController
{
	/**
	 * @param string|null $key The key of a display name
	 * @return CakeResponse svg image, cached for a year
	 */
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
	 * The board of an account, addressed by the id of its user. The avatar a player picked
	 * is never answered here, only the board, which is what a picture that stopped working
	 * falls back on. The answer is where that board lives, so the drawing stays on the url
	 * that a browser keeps for a year.
	 *
	 * @param int|null $id User id
	 * @return CakeResponse redirect to the board of the account
	 */
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
