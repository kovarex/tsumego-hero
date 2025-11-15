<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class HeroPowersTest extends TestCaseWithAuth {
	public function testRefinementGoldenTsumego() {
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
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // the power is used up

		// the reported xp is normal golden
		$this->assertTextContains((TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());

		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'G');
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');

		$oldXP = $context->user['xp'];
		$this->assertSame($context->reloadUser()['xp'] - $oldXP,
			Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER * TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']));
	}

	public function testGoldenTsumegoFail() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['status' => 'G', 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal golden
		$this->assertTextContains((TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());

		usleep(1000 * 100);

		// Grabbing this just to detect page reload later
		$oldBodyElement = $browser->driver->findElement(WebDriverBy::cssSelector('body'));
		$browser->driver->executeScript("displayResult('F')"); // fail the problem
		// the display result show refresh the page
		$browser->driver->wait(10)->until(WebDriverExpectedCondition::stalenessOf($oldBodyElement));
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'V');
	}
}
