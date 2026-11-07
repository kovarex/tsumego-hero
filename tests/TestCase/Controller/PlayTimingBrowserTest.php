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
		// Next shows the problem in place (PlayNavigation.js)
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "' . $urls[1] . '" && window.tsumegoID === ' . (int) $context->tsumegos[1]['id']);
		// the samples of the boards are taken after their first paint
		usleep(300000);
		// a problem opened with its page from another one
		$browser->get(ltrim($urls[0], '/'));
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor !== null');
		usleep(300000);
		$browser->get('sets');

		// the last four: the page a previous test left the browser on sends its own when the test leaves it
		$expected = ['problem_open', 'result_post', 'problem_next_in_place', 'problem_next'];
		$samples = [];
		for ($wait = 0; $wait < 50 && array_column($samples, 'metric') !== $expected; $wait++, usleep(100000))
			$samples = array_slice(Util::query('SELECT metric, milliseconds, server_milliseconds, mode FROM rum_sample ORDER BY id'), -4);
		$this->assertSame($expected, array_column($samples, 'metric'));
		foreach ($samples as $sample)
		{
			$this->assertGreaterThan(0, (int) $sample['milliseconds'], $sample['metric']);
			// the server's part of a problem shown in place is in the time it was loaded ahead, not in this one
			if ($sample['metric'] !== 'problem_next_in_place')
				$this->assertNotNull($sample['server_milliseconds'], $sample['metric'] . ' has the time of the server');
			$this->assertSame(Constants::$LEVEL_MODE, (int) $sample['mode'], $sample['metric']);
		}
		$browser->assertNoJsErrors();
	}
}
