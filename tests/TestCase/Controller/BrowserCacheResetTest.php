<?php

class BrowserCacheResetTest extends TestCaseWithAuth
{
	public function testFirstRequestClearsTheBrowserCacheOnce()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);
		unset($_COOKIE['browser_cache_version']);

		$this->testAction('/changelog', ['method' => 'get']);
		$this->assertSame('"cache"', $this->controller->response->header()['Clear-Site-Data'] ?? null);
		$this->assertSame(AppController::BROWSER_CACHE_VERSION, $_COOKIE['browser_cache_version']);

		$this->testAction('/changelog', ['method' => 'get']);
		$this->assertArrayNotHasKey('Clear-Site-Data', $this->controller->response->header());
	}
}
