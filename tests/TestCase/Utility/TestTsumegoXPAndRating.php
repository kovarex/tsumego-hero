<?php

class TestTsumegoXPAndRating extends TestCaseWithAuth {
	public function testshowNormalXP(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]], 'difficulty' => 66]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
	}

	public function testShowingGoldenXP(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]], 'difficulty' => 66, 'status' => 'G']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is golden
		$this->assertTextContains((TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), 'Golden');
	}

	public function testShowingNormalStatusAndUpdatingToSolved(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]], 'difficulty' => 66]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$this->assertTextContains($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		$this->assertTextContains($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), 'Solved');
	}

	public function testShowingSprintAfterSprintIsClicked(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]], 'difficulty' => 66]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		$browser->driver->findElement(WebDriverBy::cssSelector('#sprintLink'))->click();
		$this->assertTextContains($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$SPRINT_MULTIPLIER) . ' XP');
		$this->assertTextContains($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), 'Sprint');
	}
}
