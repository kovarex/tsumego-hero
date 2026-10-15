<?php

use App\Utility\Avatar;

/**
 * AvatarsController
 *
 * Serves the board avatar of a player as a square svg. The drawing comes from
 * the user id alone, so the endpoint is a pure function of its url: it reads no
 * row of the database, and a list of a thousand players costs no query at all.
 *
 * A board keeps its corners (a circle would cut the pattern off), so the callers
 * pass border-radius:0 for this avatar and keep the round shape for a picture.
 */
class AvatarsController extends AppController
{
	/**
	 * @param string|null $userId The user id
	 * @return CakeResponse svg image, cached for a year
	 */
	public function image(?string $userId = null)
	{
		if ($userId === null || !ctype_digit($userId))
			throw new NotFoundException('Invalid user id');

		$this->response->type('svg');
		$this->response->header('Cache-Control', 'public, max-age=31536000, immutable');
		$this->response->etag(md5(Avatar::VERSION . '|' . $userId));
		$this->response->body(Avatar::svg((int) $userId));

		return $this->response;
	}
}
