<?php

App::uses('ForbiddenException', 'Routing/Error');

class TagConnectionController extends AppController
{
	public function add($tsumegoID, $tagName)
	{
		$this->Authorization->authorize('TagConnection', 'add');
		if (!ClassRegistry::init('TagConnection')::canUserAddTag(Auth::getUserID()))
			throw new ForbiddenException('Daily tag limit reached.');
		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
			throw new ForbiddenException('Tag "' . $tagName . '" doesn\'t exist.');

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			throw new ForbiddenException('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => [
				'tsumego_id' => $tsumegoID,
				'tag_id' => $tag['Tag']['id']]]);
		if ($tagConnection)
			throw new ForbiddenException('The tsumego already has tag ' . $tag['Tag']['name'] . '.');

		$tagConnection = [];
		$tagConnection['tag_id'] = $tag['Tag']['id'];
		$tagConnection['tsumego_id'] = $tsumegoID;
		$tagConnection['user_id'] = Auth::getUserID();
		$tagConnection['approved'] = Auth::isAdmin() ? 1 : 0;

		if($tagConnection['approved'] == 1)
			AdminActivityLogger::log(AdminActivityType::ADD_TAG, $tagConnection['tsumego_id'], null, ' ', $tagName);

		ClassRegistry::init('TagConnection')->create();
		ClassRegistry::init('TagConnection')->save($tagConnection);
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	public function remove($tsumegoID, $tagName)
	{
		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
			throw new ForbiddenException('Tag "' . $tagName . '" doesn\'t exist.');

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			throw new ForbiddenException('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', ['conditions' => [
			'tag_id' => $tag['Tag']['id'],
			'tsumego_id' => $tsumegoID]]);
		if (!$tagConnection)
			throw new ForbiddenException('Tag to remove isn\'t assigned to this tsumego.');

		$this->Authorization->authorize($tagConnection, 'remove');

		ClassRegistry::init('TagConnection')->delete($tagConnection['TagConnection']['id']);
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}
}
