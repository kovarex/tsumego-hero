<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

App::uses('HeroPowers', 'Utility');

class HeroPowersTest extends TestCaseWithAuth {
	public function testRefinementGoldenTsumego() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal

		$browser->clickId('refinementLink');
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
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprintLink');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$oldXP = $context->user['xp'];
		$this->assertSame($context->reloadUser()['xp'] - $oldXP,
			Constants::$SPRINT_MULTIPLIER * TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']));
		$this->assertSame($context->user['used_sprint'], 1);
	}

	public function testSprintPersistsToNextTsumego() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [
				['sets' => [['name' => 'set 1', 'num' => 1]]],
				['sets' => [['name' => 'set 1', 'num' => 2]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprintLink');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving, sprint is still visible:
		$browser->clickId('besogo-next-button');
		$bla = $browser->driver->getPageSource();
		$oldXP = $context->user['xp'];
		$this->assertSame($context->reloadUser()['xp'] - $oldXP,
			Constants::$SPRINT_MULTIPLIER * TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[0]['id'])['Tsumego']));
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving again, sprint is applied on xp still
		$browser->clickId('besogo-next-button');
		$oldXP = $context->user['xp'];
		$this->assertSame($context->reloadUser()['xp'] - $oldXP,
			Constants::$SPRINT_MULTIPLIER * TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->otherTsumegos[1]['id'])['Tsumego']));
	}

	public function testSprintLinkNotPresentWhenSprintIsUsedUp() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_sprint' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertCount(0, $browser->driver->findElements(WebDriverBy::cssSelector('#sprintLink')));
	}

	public function testUseIntuition() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertFalse($browser->driver->executeScript("return window.besogo.intuitionActive;"));
		$browser->clickId('intuitionLink');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$this->assertSame($context->reloadUser()['used_intuition'], 1);
	}

	public function testIntuitionLinkNotPresentWhenIntuitionIsUsedUp() {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertCount(0, $browser->driver->findElements(WebDriverBy::cssSelector('#intuitionLink')));
	}
}
