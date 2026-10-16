<?php

use App\Utility\Avatar;

/**
 * AvatarsController
 *
 * Serves the board avatar of a player as a square svg. The url carries the key of
 * the display name, which is the whole input of the drawing, so the endpoint is a
 * pure function of its url: it reads no row of the database, and a list of a
 * thousand players costs no query at all.
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
}
