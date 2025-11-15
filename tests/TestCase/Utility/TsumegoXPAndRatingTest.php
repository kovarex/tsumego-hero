<?php

use Facebook\WebDriver\WebDriverBy;

class TsumegoXPAndRatingTest extends TestCaseWithAuth {
	public function testshowNormalXP(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
	}

	public function testShowingGoldenXP(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66, 'status' => 'G']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is golden
		$this->assertTextContains((TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Golden', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testShowingNormalStatusAndUpdatingToSolved(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Solved', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testShowingSprintAfterSprintIsClicked(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		$browser->driver->findElement(WebDriverBy::cssSelector('#sprintLink'))->click();
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$SPRINT_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}
}
