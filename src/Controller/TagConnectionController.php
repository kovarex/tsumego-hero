<?php

class TagConnectionController extends AppController
{
	public function add($tsumegoID, $tagName)
	{
		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(403);
			$this->response->body('Not logged in.');
			return $this->response;
		}
		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
		{
			$this->response->statusCode(403);
			$this->response->body('Tag "' . $tagName . '" doesn\'t exist.');
			return $this->response;
		}

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			$this->response->statusCode(403);
			$this->response->body('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');
			return $this->response;
		}

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => [
				'tsumego_id' => $tsumegoID,
				'tag_id' => $tag['Tag']['id']]]);
		if ($tagConnection)
		{
			$this->response->statusCode(403);
			$this->response->body('The tsumego already has tag ' . $tag['Tag']['name'] . '.');
			return $this->response;
		}

		$tagConnection = [];
		$tagConnection['tag_id'] = $tag['Tag']['id'];
		$tagConnection['tsumego_id'] = $tsumegoID;
		$tagConnection['user_id'] = Auth::getUserID();
		$tagConnection['approved'] = Auth::isAdmin() ? 1 : 0;
		ClassRegistry::init('TagConnection')->create();
		ClassRegistry::init('TagConnection')->save($tagConnection);
		$this->response->statusCode(200);
		return $this->response;
	}

	public function remove($tsumegoID, $tagName)
	{
		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(403);
			$this->response->body('Not logged in.');
			return $this->response;
		}

		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
		{
			$this->response->statusCode(403);
			$this->response->body('Tag "' . $tagName . '" doesn\'t exist.');
			return $this->response;
		}

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			$this->response->statusCode(403);
			$this->response->body('Tsumego with id="' . $tsumegoID . '" wasn\'t found.');
			return $this->response;
		}

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', ['conditions' => [
			'tag_id' => $tag['Tag']['id'],
			'tsumego_id' => $tsumegoID]]);
		if (!$tagConnection)
		{
			$this->response->statusCode(403);
			$this->response->body('Tag to remove isn\'t assigned to this tsumego.');
			return $this->response;
		}

		if ($tagConnection['TagConnection']['approved'] == 1 && !Auth::isAdmin())
		{
			$this->response->statusCode(403);
			$this->response->body('Only admins can remove approved tags.');
			return $this->response;
		}

		if ($tagConnection['TagConnection']['user_id'] != Auth::getUserID() && !Auth::isAdmin())
		{
			$this->response->statusCode(403);
			$this->response->body('You can\'t remove tag proposed by someone else.');
			return $this->response;
		}

		ClassRegistry::init('TagConnection')->delete($tagConnection['TagConnection']['id']);
		$this->response->statusCode(200);
		return $this->response;
	}
}
