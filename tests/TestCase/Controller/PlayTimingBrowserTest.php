<?php

use App\Utility\Constants;
use App\Utility\Util;

//The play page measures how long a problem took until its board took moves and how long its result was
//recorded, and sends it when it is left (webroot/js/PlayTiming.js, RumController)
#[RetryOnBrowserFailure]
class PlayTimingBrowserTest extends IntegrationTestCase
{
	public function testThePlayPageSendsItsTimingsWhenItIsLeft(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2]]);
		$urls = array_map(fn($tsumego) => '/' . $tsumego['set-connections'][0]['id'], $context->tsumegos);
		$browser = Browser::instance();

		$browser->get($urls[0]);
		$browser->playWithResult('S');
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "' . $urls[1] . '"');
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor !== undefined');
		// the sample of the board is taken after its first paint
		usleep(300000);
		$browser->get('sets');

		$samples = [];
		for ($wait = 0; $wait < 50 && count($samples) < 3; $wait++, usleep(100000))
			$samples = Util::query('SELECT metric, milliseconds, server_milliseconds, mode FROM rum_sample ORDER BY id');
		$this->assertSame(['problem_open', 'result_post', 'problem_next'], array_column($samples, 'metric'));
		foreach ($samples as $sample)
		{
			$this->assertGreaterThan(0, (int) $sample['milliseconds'], $sample['metric']);
			$this->assertNotNull($sample['server_milliseconds'], $sample['metric'] . ' has the time of the server');
			$this->assertSame(Constants::$LEVEL_MODE, (int) $sample['mode'], $sample['metric']);
		}
		$browser->assertNoJsErrors();
	}
}
