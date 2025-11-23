<?php

class CurlsController extends AppController
{
	/**
	 * @return void
	 */
	public function data()
	{
		$this->Session->write('title', 'CURLs');
		$curls = $this->Curl->find('all', [
			'limit' => 1000,
			'order' => 'id DESC',
			'fields' => ['Curl.id', 'Curl.type', 'Curl.response', 'Curl.url', 'Curl.user_id', 'Curl.tsumego_id', 'Curl.created'],
		]);
		$this->set('curls', $curls);
	}

}
