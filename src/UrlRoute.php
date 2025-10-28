<?php

class UrlRoute extends CakeRoute {
	public function parse($url) {
		$params = parent::parse($url);
		$numberCandidate = substr($url, 1);
		if (is_numeric($numberCandidate)) {
			$params['controller'] = 'Tsumegos';
			$params['action'] = 'play';
			$params['pass'] = [null, $numberCandidate];
		}
		return $params;
	}
}
