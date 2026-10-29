<?php

namespace App\Routing\Route;

use Cake\Routing\Route\Route;

//A route the site answers on but never generates.
//It keeps an address that has been replaced readable for links and bookmarks written before
//the replacement, while reverse routing skips it, so every link, the sitemap and the canonical
//tag of a page keep pointing at the current address.
class ParseOnlyRoute extends Route
{
	public function match(array $url, array $context = []): ?string
	{
		return null;
	}
}
