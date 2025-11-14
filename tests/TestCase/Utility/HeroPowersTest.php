<?php

use Facebook\WebDriver\WebDriverBy;

class HeroPowersTest extends CakeTestCase {
	public function testRefinementGoldenTsumego() {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->driver->findElement(WebDriverBy::cssSelector('#refinementLink'))->click();
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $context->otherTsumegos[0]['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');
	}
}
