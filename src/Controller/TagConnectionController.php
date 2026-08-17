<?php

App::uses('ForbiddenException', 'Routing/Error');

class TagConnectionController extends AppController
{
	public function add($tsumegoID, $tagName)
	{
		if (!Auth::isLoggedIn())
			throw new ForbiddenException('Not logged in.');
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
		if (!Auth::isLoggedIn())
			throw new ForbiddenException('Not logged in.');

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

		if ($tagConnection['TagConnection']['approved'] == 1 && !Auth::isAdmin())
			throw new ForbiddenException('Only admins can remove approved tags.');

		if ($tagConnection['TagConnection']['user_id'] != Auth::getUserID() && !Auth::isAdmin())
			throw new ForbiddenException('You can\'t remove tag proposed by someone else.');

		ClassRegistry::init('TagConnection')->delete($tagConnection['TagConnection']['id']);
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}
}
