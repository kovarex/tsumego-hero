<?php

use Facebook\WebDriver\WebDriverBy;

class HeroPowersTest extends TestCaseWithAuth {
	public function testRefinementGoldenTsumego() {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');

		$browser->driver->findElement(WebDriverBy::cssSelector('#refinementLink'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $context->otherTsumegos[0]['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');

		// the reported xp is normal golden
		$this->assertTextContains((TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());

		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'G');
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');

		$newUser = ClassRegistry::init('User')->findById($context->user['id'])['User'];
		$this->assertSame($newUser['xp'] - $context->user['xp'],
			Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER * TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']));
	}
}
