<?php

use App\Attribute\HttpPost;
use App\Model\Entity\Tsumego;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\TagConnectionTable;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;

class TagConnectionController extends AppController
{
	#[HttpPost]
	public function add(string $tsumegoID, string $tagName)
	{
		$this->Authorization->authorize(['TagConnection' => new Tsumego(['id' => (int) $tsumegoID])], 'add');
		if (!TagConnectionTable::canCurrentUserAddTag())
			throw new ForbiddenException('Daily tag limit reached.');
		$tag = $this->fetchTable('Tag')->find()->where(['name' => $tagName])->first();
		if (!$tag)
			throw new ForbiddenException('Tag "' . $tagName . '" doesn\'t exist.');

		if (!$this->fetchTable('Tsumego')->exists(['id' => (int) $tsumegoID]))
			throw new ForbiddenException('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');

		$tagConnectionTable = $this->fetchTable('TagConnection');
		if ($tagConnectionTable->exists(['tsumego_id' => $tsumegoID, 'tag_id' => $tag->id]))
			throw new ForbiddenException('The tsumego already has tag ' . $tag->name . '.');

		$tagConnection = [];
		$tagConnection['tag_id'] = $tag->id;
		$tagConnection['tsumego_id'] = $tsumegoID;
		$tagConnection['user_id'] = Auth::getUserID();
		$tagConnection['approved'] = Auth::isAdmin() ? 1 : 0;

		if($tagConnection['approved'] == 1)
			AdminActivityLogger::log(AdminActivityTypeTable::ADD_TAG, $tagConnection['tsumego_id'], null, ' ', $tagName);

		$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity($tagConnection));
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	#[HttpPost]
	public function remove(string $tsumegoID, string $tagName)
	{
		$tag = $this->fetchTable('Tag')->find()->where(['name' => $tagName])->first();
		if (!$tag)
			throw new ForbiddenException('Tag "' . $tagName . '" doesn\'t exist.');

		if (!$this->fetchTable('Tsumego')->exists(['id' => (int) $tsumegoID]))
			throw new ForbiddenException('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');

		$tagConnectionTable = $this->fetchTable('TagConnection');
		$tagConnection = $tagConnectionTable->find()->where(['tag_id' => $tag->id, 'tsumego_id' => $tsumegoID])->first();
		if (!$tagConnection)
			throw new ForbiddenException('Tag to remove isn\'t assigned to this tsumego.');

		$this->Authorization->authorize($tagConnection, 'remove');

		$tagConnectionTable->deleteOrFail($tagConnection);
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}
}
