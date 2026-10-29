<?php

use App\Controller\AppController;

class BrowserCacheResetTest extends IntegrationTestCase
{
	//Two requests in a row are two page loads of the same browser.
	public function testFirstRequestClearsTheBrowserCacheOnce()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->get('/changelog');
		$this->assertSame('"cache"', $this->headerValue('Clear-Site-Data'));

		// The page the browser got back carries the version, so the next load keeps its cache.
		$this->assertSame(AppController::BROWSER_CACHE_VERSION, $this->browserCookies()['browser_cache_version'] ?? null);
		$this->cookie('browser_cache_version', AppController::BROWSER_CACHE_VERSION);
		$this->get('/changelog');
		$this->assertNull($this->headerValue('Clear-Site-Data'));
	}
}
