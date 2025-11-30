<?php

class TagConnectionController extends AppController
{
	public function add($tsumegoID, $tagName)
	{
		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(403);
			return $this->response;
		}
		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		$tagConnection = [];
		$tagConnection['TagConnection']['tag_id'] = $tag['Tag']['id'];
		$tagConnection['TagConnection']['tsumego_id'] = $tsumegoID;
		$tagConnection['TagConnection']['user_id'] = Auth::getUserID();
		$tagConnection['TagConnection']['approved'] = Auth::isAdmin() ? 1 : 0;
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
			return $this->response;
		}

		$tag = ClassRegistry::init('Tag')->findByName($tagName);
		if (!$tag)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', ['conditions' => [
			'tag_id' => $tag['Tag']['id'],
			'tsumego_id' => $tsumegoID]]);
		if (!$tagConnection)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		if ($tagConnection['TagConnection']['approved'] == 1 && !Auth::isAdmin())
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		if ($tagConnection['TagConnection']['user_id'] != Auth::getUserID() && !Auth::isAdmin())
		{
			$this->response->statusCode(403);
			return $this->response;
		}

		ClassRegistry::init('TagConnection')->delete($tagConnection['TagConnection']['id']);
		$this->response->statusCode(200);
		return $this->response;
	}
}
