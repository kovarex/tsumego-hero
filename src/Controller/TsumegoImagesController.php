<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\NotFoundException;
use App\Model\Table\SgfTable;
use App\Attribute\HttpGet;
use App\Utility\Constants;
use App\Utility\TsumegoImage;
use App\Utility\Util;

//The pictures of the problems for Open Graph social sharing, drawn by TsumegoImage.
class TsumegoImagesController extends AppController
{
	//Generate a PNG image for a tsumego puzzle for Open Graph sharing
	//$setConnectionId: The SetConnection ID (same as /id route)
	/** @return Response PNG image with caching headers */
	#[HttpGet]
	public function tsumegoImage(?string $setConnectionId = null)
	{
		if (!$setConnectionId || !is_numeric($setConnectionId))
			throw new NotFoundException('Invalid set connection ID');

		// Single query: join SetConnection → Tsumego → Sgf + Set, fetch only needed fields
		$data = Util::query(
			'SELECT t.created, sgf.sgf
			FROM set_connection sc
			JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
			JOIN `set` s ON s.id = sc.set_id AND s.deleted IS NULL
			JOIN sgf ON sgf.tsumego_id = t.id AND sgf.sgf_status_id = ' . SgfTable::STATUS_ACCEPTED . '
			WHERE sc.id = ?
			ORDER BY sgf.id DESC
			LIMIT 1',
			[(int) $setConnectionId]
		);

		if (empty($data))
			throw new NotFoundException('Puzzle not found');

		$row = $data[0];
		$sgfString = $row['sgf'];
		if (empty($sgfString))
			throw new NotFoundException('SGF not found');

		$imageData = TsumegoImage::png($sgfString);

		$this->setEtag(md5(Constants::$TSUMEGO_IMAGE_VERSION . $sgfString));
		$this->cacheUntil(!empty($row['created']) ? $row['created'] : '-1 day', '+1 year');
		return $this->respond($imageData, 'png');
	}
}
