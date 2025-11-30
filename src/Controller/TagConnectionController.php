<?php

class TagConnectionController extends AppController
{
	function add($tsumegoID, $tagName)
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
		$tagConnection['TagConnection']['approved'] = 0;
		ClassRegistry::init('TagConnection')->create();
		ClassRegistry::init('TagConnection')->save($tagConnection);
		$this->response->statusCode(200);
		return $this->response;
	}
}
