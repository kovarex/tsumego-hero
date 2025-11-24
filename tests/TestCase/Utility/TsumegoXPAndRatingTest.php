<?php

App::uses('TsumegoXPAndRating', 'Utility');
App::uses('Level', 'Utility');
use Facebook\WebDriver\WebDriverBy;

class TsumegoXPAndRatingTest extends TestCaseWithAuth
{
	public function testshowNormalXP(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testShowingGoldenXP(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'G']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is golden
		$this->assertTextContains(TsumegoUtil::getXpValue($context->otherTsumegos[0], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Golden', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testShowingNormalStatusAndUpdatingToSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Solved', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testShowingSolvedOnOpenedSolvedProblem(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'S']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->assertTextContains('Solved', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function checkSprintInXpAndTimeInStatus2($browser)
	{
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$status = $browser->driver->findElement(WebDriverBy::cssSelector('#status2'))->getText();
		$this->assertSame(preg_match('/(\d+):([0-5]\d)/', $status, $m), 1, 'The status should contain time in format m:s, but it wasn\'t found in the string: "' . $status . "'");
		$this->assertTrue($m > 1);
	}

	public function testShowingSprintAfterSprintIsClicked(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$this->assertTextStartsNotWith($browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText(), strval(TsumegoUtil::getXpValue($context->otherTsumegos[0])) . ' XP');
		$browser->clickId('sprint');
		$browser->driver->wait(10, 500)->until(function () use ($browser, $context) {
			$xpDisplayText = $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText();
			$xpText = strval(TsumegoUtil::getXpValue($context->otherTsumegos[0], Constants::$SPRINT_MULTIPLIER)) . ' XP';
			return str_contains($xpDisplayText, $xpText);
		});
		$this->checkSprintInXpAndTimeInStatus2($browser);
	}

	public function testShowingSprintWhenOpeningProblemWhileSprintIsActive(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'mode' => Constants::$LEVEL_MODE,
				'premium' => 1,
				'sprint_start' => date('Y-m-d H:i:s')],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		// the sprint is active from the start
		$this->assertTextContains(strval(TsumegoUtil::getXpValue($context->otherTsumegos[0], Constants::$SPRINT_MULTIPLIER)) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$this->checkSprintInXpAndTimeInStatus2($browser);
	}

	public function testProgressDeletionsAffectXPShownAndGained(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'other-users' => [['mode' => Constants::$LEVEL_MODE, 'name' => 'Ivan Detkov']],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]],
			'progress-deletions' => [
				['set' => 'set 1', 'created' => date('Y-m-d H:i:s')],
				['set' => 'set 1', 'created' => date('Y-m-d H:i:s', strtotime('-2 months'))],
				['set' => 'set 1', 'created' => date('Y-m-d H:i:s'), 'user' => 'Ivan Detkov']]]);

		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		// only one progress deletion affects this, one is too old, and one is from other user
		$originalTsumegoXpValue = TsumegoUtil::getXpValue($context->otherTsumegos[0], TsumegoXPAndRating::getProgressDeletionMultiplier(1));
		usleep(1000 * 100);
		$this->assertTextContains(strval($originalTsumegoXpValue) . ' XP', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());

		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame($context->XPGained(), $originalTsumegoXpValue);
	}

	public function testXPForNextLevel()
	{
		$this->assertSame(Level::getXPForNext(1), 50);
		$this->assertSame(Level::getXPForNext(2), 60);
		$this->assertSame(Level::getXPForNext(3), 70);
		$this->assertSame(Level::getXPForNext(4), 80);
		$this->assertSame(Level::getXPForNext(5), 90);
		$this->assertSame(Level::getXPForNext(6), 100);
		$this->assertSame(Level::getXPForNext(7), 110);
		$this->assertSame(Level::getXPForNext(8), 120);
		$this->assertSame(Level::getXPForNext(9), 130);
		$this->assertSame(Level::getXPForNext(10), 140);
		$this->assertSame(Level::getXPForNext(11), 150);
		$this->assertSame(Level::getXPForNext(12), 175);
		$this->assertSame(Level::getXPForNext(13), 200);
		$this->assertSame(Level::getXPForNext(100), 58850);
		$this->assertSame(Level::getXPForNext(101), 60000);
		$this->assertSame(Level::getXPForNext(102), 60000);
	}

	public function testXPForNextLevelComparedToPreviousSumCode()
	{
		$current = 0;
		for ($level = 1; $level < 110; $level++)
		{
			$this->assertSame($current, Level::oldXPSumCode($level), "Level: " . $level);
			$current += Level::getXPForNext($level);
		}
	}

	public function testXpForNextLevelComparedToNewSumCode()
	{
		$current = 0;
		for ($level = 1; $level < 110; $level++)
		{
			$this->assertSame($current, Level::getXpSumToGetLevel($level), "Level: " . $level);
			$current += Level::getXPForNext($level);
		}
	}
}
