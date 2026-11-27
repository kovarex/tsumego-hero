<?php

namespace App\Controller;

use App\Attribute\HttpGet;
use App\Utility\SetImage;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

//Serves the cover of a set from the database (set_cover).
class SetCoversController extends AppController
{
	//The cover of a set. The url carries the hash of the picture, so what it answers never
	//changes and the browser keeps it for a year; the url of a cover that was replaced or
	//removed answers nothing.
	//$id: Set id
	//$hash: set.cover_hash
	/** @return Response webp image, cached for a year */
	#[HttpGet]
	public function picture(?int $id = null, ?string $hash = null)
	{
		$picture = $id === null || $hash === null ? null : SetImage::picture($id, $hash);
		if ($picture === null)
			throw new NotFoundException('No such cover');

		$this->setResponseHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$this->setEtag($hash);
		return $this->respond($picture, 'webp');
	}
}
