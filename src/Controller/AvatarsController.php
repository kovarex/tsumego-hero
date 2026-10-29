<?php

namespace App\Controller;

use Cake\Http\Response as CakeResponse;
use Cake\Http\Exception\NotFoundException;
use App\Attribute\HttpGet;
use App\Utility\Avatar;
use App\Utility\UploadedAvatar;

//Serves the board avatar of a player as a square svg. The url carries the key of
//the display name, which is the whole input of the drawing, so the image endpoint
//is a pure function of its url: it reads no row of the database, and a list of a
//thousand players costs no query at all.
//The url that carries the id of the account instead answers where the board of that
//account is, which is what a list uses when it knows the player but not the drawing.
//A board keeps its corners (a circle would cut the pattern off), so the callers
//pass border-radius:0 for this avatar and keep the round shape for a picture.
//A picture a player uploaded is served here as well, from the database.
class AvatarsController extends AppController
{
	//$key: The key of a display name
	/** @return CakeResponse svg image, cached for a year */
	#[HttpGet]
	public function image(?string $key = null)
	{
		if ($key === null || preg_match('/^[0-9a-f]{' . Avatar::KEY_LENGTH . '}$/', $key) !== 1)
			throw new NotFoundException('Invalid board key');

		$this->setResponseHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$this->setEtag(md5(Avatar::VERSION . '|' . $key));
		return $this->respond(Avatar::svg($key), 'svg');
	}

	//The same board as a png, which is what a page shares: several networks drop
	//an svg from a share preview. The drawing is a function of the key, so the
	//picture is still made without reading a row of the database.
	//$key: The key of a display name
	/** @return CakeResponse png image, cached for a year */
	#[HttpGet]
	public function imagePng(?string $key = null)
	{
		if ($key === null || preg_match('/^[0-9a-f]{' . Avatar::KEY_LENGTH . '}$/', $key) !== 1)
			throw new NotFoundException('Invalid board key');

		$this->setResponseHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$this->setEtag(md5(Avatar::VERSION . '|png|' . $key));
		return $this->respond(Avatar::png($key), 'png');
	}

	//The picture a player uploaded. The url carries the hash of the picture, so what it
	//answers never changes and the browser keeps it for a year; the url of a picture that
	//was replaced or removed, or of a deleted account, answers nothing.
	//$id: User id
	//$hash: user.uploaded_avatar_hash
	/** @return CakeResponse webp image, cached for a year */
	#[HttpGet]
	public function picture(?int $id = null, ?string $hash = null)
	{
		$picture = $id === null || $hash === null ? null : UploadedAvatar::picture($id, $hash);
		if ($picture === null)
			throw new NotFoundException('No such picture');

		$this->setResponseHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$this->setEtag($hash);
		return $this->respond($picture, 'webp');
	}

	//The board of an account, addressed by the id of its user. The avatar a player picked
	//is never answered here, only the board, which is what a picture that stopped working
	//falls back on. The answer is where that board lives, so the drawing stays on the url
	//that a browser keeps for a year.
	/** @return CakeResponse redirect to the board of the account */
	#[HttpGet]
	public function boardOfUser(?int $id = null)
	{
		$user = $id === null ? null : $this->fetchTable('User')->find()
			->select(['display_name', 'deleted'])
			->where(['id' => $id])
			->first();

		if ($user === null || $user->deleted !== null || $user->display_name === '')
			throw new NotFoundException('No board for this account');

		// The board of a name is fixed, the name of an account is not, so this is the
		// answer that may not be kept for long.
		$this->setResponseHeader('Cache-Control', 'max-age=60');

		return $this->redirect(Avatar::url($user->display_name));
	}
}
