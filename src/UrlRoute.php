<?php

class UrlRoute extends CakeRoute
{
	/**
	 * @param mixed $url
	 * @return array|false
	 */
	public function parse(mixed $url)
	{
		$params = parent::parse($url);
		$numberCandidate = substr($url, 1);
		if (is_numeric($numberCandidate))
		{
			$params['controller'] = 'Tsumegos';
			$params['action'] = 'play';
			$params['pass'] = [null, $numberCandidate];
		}
		return $params;
	}
}
