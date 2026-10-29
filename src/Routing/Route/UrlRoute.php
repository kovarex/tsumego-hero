<?php

namespace App\Routing\Route;

use Cake\Routing\Route\Route;

class UrlRoute extends Route
{
	public function parse(string $url, string $method): ?array
	{
		//A bare number is the address of a problem in a collection, /<set connection id>
		$numberCandidate = substr($url, 1);
		if (is_numeric($numberCandidate))
			return ['controller' => 'Tsumegos', 'action' => 'play', 'pass' => [null, $numberCandidate], 'plugin' => null, '_matchedRoute' => $this->template];
		return parent::parse($url, $method);
	}
}
