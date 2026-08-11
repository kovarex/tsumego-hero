<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class ClientTimeDisplayTest extends TestCaseWithAuth
{
	public function testSolveHistoryHasTimeElements(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Time Test Set', 'num' => '1']]],
		]);
		$this->login($context->user['User']['name']);

		// Record an attempt so solve history has data
		ClassRegistry::init('TsumegoAttempt')->create();
		ClassRegistry::init('TsumegoAttempt')->save([
			'TsumegoAttempt' => [
				'user_id' => $context->user['id'],
				'tsumego_id' => $context->tsumegos[0]['id'],
				'solved' => 1,
				'misplays' => 0,
				'user_rating' => 1000,
				'gain' => 0,
				'seconds' => 10,
				'created' => date('Y-m-d H:i:s'),
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$timeElements = $browser->driver->findElements(WebDriverBy::tagName('time'));
		$this->assertGreaterThan(0, count($timeElements));

		$first = $timeElements[0];
		$this->assertNotEmpty($first->getAttribute('datetime'));
		$this->assertSame('datetime', $first->getAttribute('data-format'));
	}
}
