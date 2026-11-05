<?php

//The hero powers of the play page call the server with fetch, as ajax (webroot/js/util.js)
#[RetryOnBrowserFailure]
class HeroPowerAjaxBrowserTest extends IntegrationTestCase
{
	//The server knows the call for an ajax one, so its error comes as JSON (AppExceptionRenderer), which makeAjaxCall alerts
	public function testAHeroPowerCallIsAnAjaxCall(): void
	{
		new ContextPreparator(['user' => ['level' => 100]]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$alert = $browser->driver->executeAsyncScript('let done = arguments[arguments.length - 1];
			window.alert = message => done(message);
			makeAjaxCall("/hero/revelation/999999999", () => done("solved"));');
		$this->assertSame('{"error":"Tsumego not found"}', $alert);
	}

	public function testTheSprintAndIntuitionButtonsUseTheirPower(): void
	{
		$context = new ContextPreparator(['user' => ['level' => 100], 'tsumego' => ['set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->clickId('sprint');
		$browser->waitUntilJs('document.getElementById("sprint").src.endsWith("/img/hp1x.png")');
		$browser->clickId('intuition');
		$browser->waitUntilJs('document.getElementById("intuition").src.endsWith("/img/hp2x.png")');

		$user = $context->reloadUser();
		$this->assertSame([1, 1], [(int) $user['used_sprint'], (int) $user['used_intuition']]);
	}
}
