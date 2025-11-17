<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

App::uses('HeroPowers', 'Utility');

class HeroPowersTest extends TestCaseWithAuth {
	public function testRefinementGoldenTsumego() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		$originalTsumegoXPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego'], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$browser->clickId('refinement');
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $context->otherTsumegos[0]['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // the power is used up

		// the reported xp is normal golden
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'G');
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), $originalTsumegoXPValue);
	}

	public function testGoldenTsumegoFail() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['status' => 'G', 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal golden
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

	public function testSprint() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$originalTsumegoXPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumegoXPValue);
		$this->assertSame($context->user['used_sprint'], 1);
	}

	public function testSprintPersistsToNextTsumego() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [
				['sets' => [['name' => 'set 1', 'num' => 1]]],
				['sets' => [['name' => 'set 1', 'num' => 2]]]]]);
		$originalTsumego0XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']);
		$originalTsumego1XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[1]['id'])['Tsumego']);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving, sprint is still visible:
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego0XPValue);
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving again, sprint is applied on xp still
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego1XPValue);
	}

	private function checkPowerIsInactive($browser, $name) {
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'auto');
	}

	private function checkPowerIsActive($browser, $name) {
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'pointer');
	}

	public function testSprintLinkNotPresentWhenSprintIsUsedUp() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_sprint' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'sprint');
	}

	public function testUseIntuition() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertFalse($browser->driver->executeScript("return window.besogo.intuitionActive;"));
		$this->checkPowerIsActive($browser, 'intuition');
		$browser->clickId('intuition');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$this->checkPowerIsInactive($browser, 'intuition');
		$this->assertSame($context->reloadUser()['used_intuition'], 1);
	}

	public function testIntuitionPowerIsInactiveWhenIntuitionIsUsedUp() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'intuition');
	}

	public function testRejuvenationRestoresHealthIntuitionAndFailedTsumegos() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1, 'damage' => 7],
			'other-tsumegos' => [
				['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'F'],
				['sets' => [['name' => 'set 1', 'num' => 2]], 'status' => 'X']]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($context->user['damage'], 7);
		$this->checkPowerIsActive($browser, 'rejuvenation');
		$this->checkPowerIsInactive($browser, 'intuition');
		$browser->clickId('rejuvenation');
		$browser->driver->wait(10, 500)->until(function () use ($context) { return $context->reloadUser()['damage'] == 0; });
		$this->assertSame($context->user['used_rejuvenation'], 1);
		$this->assertSame($context->user['used_intuition'], 0);
		$this->checkPowerIsInactive($browser, 'rejuvenation');
		$this->checkPowerIsActive($browser, 'intuition');
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'V');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'W');
	}
}
