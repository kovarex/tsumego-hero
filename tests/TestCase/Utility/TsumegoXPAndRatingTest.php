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

	public function checkSprintInXpAndTimeInStatus2($browser) {
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$status = $browser->driver->findElement(WebDriverBy::cssSelector('#status2'))->getText();
		$this->assertSame(preg_match('/(\d+):([0-5]\d)/', $status, $m), 1, 'The status should contain time in format m:s, but it wasn\'t found in the string: "' . $status . "'");
		$this->assertTrue($m > 1);
	}

	public function testShowingSprintAfterSprintIsClicked(): void {
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		$browser->clickId('sprint');
		$browser->driver->wait(10, 500)->until(function () use ($browser, $context) {
			$xpDisplayText = $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText();
			$xpText = strval(TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$SPRINT_MULTIPLIER) . ' XP';
			return str_contains($xpDisplayText, $xpText);
		});
		$this->checkSprintInXpAndTimeInStatus2($browser);
	}

	public function testShowingSprintWhenOpeningProblemWhileSprintIsActive(): void {
		$context = new ContextPreparator([
			'user' => [
				'mode' => Constants::$LEVEL_MODE,
				'premium' => 1,
				'sprint_start' => date('Y-m-d H:i:s')],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'difficulty' => 66]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the sprint is active from the start
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0]) * Constants::$SPRINT_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->checkSprintInXpAndTimeInStatus2($browser);
	}
}
