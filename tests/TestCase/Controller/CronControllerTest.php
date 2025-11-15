<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../ContextPreparator.php');

class CronControllerTest extends TestCaseWithAuth {
	public function testCronResetsPowers() {
		foreach ([
			'used_refinement',
			'used_sprint',
			'used_rejuvenation',
			'used_potion',
			'used_intuition',
			'used_revelation'] as $name) {
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE, $name => 1]]);
			$this->assertSame($context->reloadUser()[$name], 1);
			$this->testAction('/cron/daily/' . CRON_SECRET);
			$this->assertSame($context->reloadUser()[$name], 0);
		}
	}

	public function testCronResetsDamage() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'damage' => 7]]);
		$this->assertSame($context->reloadUser()['damage'], 7);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['damage'], 0);
	}
}
