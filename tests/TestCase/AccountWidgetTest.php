<?php

namespace TestCase;

class AccountWidgetTest extends ControllerTestCase
{
	public function testShowLevelInAccountWidget()
	{
		$context = new ContextPreparator(['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'xp' => 13,
			'level' => 14]]);
		$browser = Browser::instance();
		$browser->get('/');
		$browser->getCssSelect('');
	}
}
