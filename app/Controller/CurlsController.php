<?php


class CurlsController extends AppController {
	public function data() {
		$_SESSION['title'] = 'CURLs';
		$curls = $this->Curl->find('all', array(
			'limit' => 1000, 
			'order' => 'id DESC',
			'fields' => array('Curl.id', 'Curl.type', 'Curl.response', 'Curl.url', 'Curl.user_id', 'Curl.tsumego_id', 'Curl.created'),
		));
		$this->set('curls', $curls);
    }
}


?>