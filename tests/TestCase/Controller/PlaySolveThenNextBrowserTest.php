<?php

use App\Utility\Constants;
use App\Utility\Util;

//kovarex's report: reset a part, open the last problem of the collection, solve it cleanly, Next. The problem had been
//failed before the reset, and the solve continued that failed attempt (PlayResultProcessor), which ended solved with
//its misplay and counted in the new run. The solve after a reset is an attempt of its own.
#[RetryOnBrowserFailure]
class PlaySolveThenNextBrowserTest extends IntegrationTestCase
{
	private const SGF = '(;GM[1]FF[4]SZ[19]AB[cc];B[aa]C[+])';

	public function testASolveFollowedByNextKeepsOneCleanAttempt(): void
	{
		$tsumegos = [];
		for ($i = 0; $i < 4; $i++)
			$tsumegos[] = ['sets' => [['name' => 'reset set', 'num' => $i + 1]], 'status' => 'S', 'sgf' => ['data' => self::SGF, 'first_move_color' => 'B'],
				'attempts' => [['solved' => true, 'seconds' => 5, 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]]];
		// the last problem was failed before the reset, and not solved since
		$tsumegos[3]['status'] = 'F';
		$tsumegos[3]['attempts'] = [['solved' => false, 'misplays' => 1, 'seconds' => 5, 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]];
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE, 'collection_size' => 200], 'tsumegos' => $tsumegos]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		$last = $context->tsumegos[3];
		$browser = Browser::instance();

		// the reset of the part, through its form
		$browser->get('sets/' . $setID);
		$browser->clickId('showx');
		$browser->clickId('reset-textfield');
		$browser->driver->getKeyboard()->sendKeys('reset');
		$submit = $browser->find('#reset-submit');
		$submit->click();
		$browser->waitUntilStale($submit);
		$this->assertSame(['W', 'W', 'W'], array_column(Util::query('SELECT status FROM tsumego_status WHERE user_id = ? ORDER BY tsumego_id', [$context->user['id']]), 'status'),
			'the part is reset: the solved problems back in Review, the failed one cleared');

		$browser->get('/' . $last['set-connections'][0]['id']);
		$browser->waitForBoard();
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Correct!")');
		$browser->waitForSubmitResult();
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('document.readyState === "complete" && location.pathname.startsWith("/sets/")');

		$attempts = Util::query('SELECT solved, misplays, created > NOW() - INTERVAL 1 HOUR AS now FROM tsumego_attempt WHERE user_id = ? AND tsumego_id = ? ORDER BY id', [$context->user['id'], $last['id']]);
		$this->assertEquals([['solved' => 0, 'misplays' => 1, 'now' => 0], ['solved' => 1, 'misplays' => 0, 'now' => 1]], $attempts,
			'the failed attempt before the reset stays as it was, the solve after it is an attempt of its own, clean');
	}
}
