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
				'created' => '2026-08-15 12:00:00',
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$timeElements = $browser->driver->findElements(WebDriverBy::tagName('time'));
		$this->assertGreaterThan(0, count($timeElements));

		// Server stores in UTC (+00:00), browser is in Europe/Prague (CEST +02:00).
		// Displayed time should be shifted by 2 hours.
		$displayed = $timeElements[0]->getText();
		$this->assertNotEmpty($displayed);
		$this->assertStringNotContainsString('2026-08-15 12:00:00', $displayed,
			'Displayed time should be converted from UTC to local timezone');

		// datetime attribute comes from PHP server (UTC)
		$this->assertSame('2026-08-15T12:00:00+00:00', $timeElements[0]->getAttribute('datetime'));
	}
}
