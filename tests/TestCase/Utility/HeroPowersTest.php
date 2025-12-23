<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

App::uses('HeroPowers', 'Utility');

class HeroPowersTest extends TestCaseWithAuth
{
	public function testRefinementGoldenTsumego()
	{
		$context = new ContextPreparator(['user' => ['premium' => 1], 'tsumego' => 1]);
		$context->unlockAchievementsWithoutEffect();

		$originalTsumegoXPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego'], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$browser->clickId('refinement');
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // the power is used up

		// the reported xp is normal golden
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'G');
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), $originalTsumegoXPValue);
	}

	public function testGoldenTsumegoFail()
	{
		$context = new ContextPreparator(['user' => ['premium' => 1], 'tsumego' => ['status' => 'G', 'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal golden
		usleep(1000 * 100);

		// Grabbing this just to detect page reload later
		$oldBodyElement = $browser->driver->findElement(WebDriverBy::cssSelector('body'));
		$browser->driver->executeScript("displayResult('F')"); // fail the problem
		// the display result should refresh the page
		$browser->driver->wait(10)->until(WebDriverExpectedCondition::stalenessOf($oldBodyElement));
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		// Wait for navigation buttons to load
		$browser->driver->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('div.tsumegoNavi2 li')));

		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'V');
	}

	public function testSprint()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$context->unlockAchievementsWithoutEffect();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$tsumego = ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego'];
		$originalTsumegoXPValue = TsumegoUtil::getXpValue($tsumego);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		usleep(1000 * 100); // if this fails often, we should check the ajax success and wait until that
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumegoXPValue);
		$this->assertSame($context->user['used_sprint'], 1);
	}

	public function testSprintPersistsToNextTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumegos' => [1, 2]]);
		$originalTsumego0XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego']);
		$originalTsumego1XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[1]['id'])['Tsumego']);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$context->unlockAchievementsWithoutEffect();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		usleep(1000 * 100); // if this fails often, we should check the ajax success and wait until that
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving, sprint is still visible:
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego0XPValue);
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$browser->playWithResult('S'); // solve the problem

		// clicking next after solving again, sprint is applied on xp still
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego1XPValue);
	}

	private function checkPowerIsInactive($browser, $name)
	{
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'auto');
	}

	private function checkPowerIsActive($browser, $name)
	{
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'pointer');
	}

	public function testSprintLinkNotPresentWhenSprintIsUsedUp()
	{
		$context = new ContextPreparator(['user' => ['used_sprint' => 1], 'tsumego' => 1]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'sprint');
	}

	public function testUseRevelation()
	{
		foreach (['logged-off', 'not-available', 'used-up', 'normal'] as $testCase)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator(['tsumego' => 1]);
			HeroPowers::changeUserSoRevelationCanBeUsed();
			$context->unlockAchievementsWithoutEffect();
			$context->XPGained(); // to reload the current xp to be able to tell the gained later
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertSame(1, $browser->driver->executeScript("return window.revelationUseCount;"));
			$this->checkPowerIsActive($browser, 'revelation');

			if ($testCase == 'logged-off')
				$browser->logoff();
			elseif ($testCase == 'not-available')
			{
				Auth::getUser()['level'] = 1;
				Auth::saveUser();
				$context->XPGained(); // to reload the current xp to be able to tell the gained later
			}
			elseif ($testCase == 'used-up')
			{
				Auth::getUser()['used_revelation'] = 10;
				Auth::saveUser();
			}

			$browser->driver->executeScript("window.alert = function(msg) { window.alertMessage = msg; return true;};");
			$browser->clickId('revelation');
			$message =  $browser->driver->executeScript("return window.alertMessage;");
			if ($testCase == 'logged-off')
			{
				$this->assertSame($message, 'Not logged in.');
				Auth::init();
				continue;
			}
			elseif ($testCase == 'not-available')
				$this->assertSame($message, 'Revelation is not available to this account.');
			elseif ($testCase == 'user-up')
				$this->assertSame($message, 'Revelation is used up today.');
			if ($testCase == 'normal')
			{
				$browser->driver->wait(10, 50)->until(function () use ($browser) {
					return $browser->driver->executeScript("return window.revelationUseCount;") == 0;
				});
				$this->checkPowerIsInactive($browser, 'revelation');
			}
			$expectedUsedCount = $testCase == 'used-up' ? 10 : ($testCase == 'not-available' ? 0 : 1);
			$this->assertSame($context->reloadUser()['used_revelation'], $expectedUsedCount);
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			if ($testCase != 'not-available')
				$this->checkPowerIsInactive($browser, 'revelation');
			$this->assertSame($context->reloadUser()['used_revelation'], $expectedUsedCount);

			// status was changed to 'S' (solved)
			$tsumegoStatuses = ClassRegistry::init('TsumegoStatus')->find('all');
			$this->assertSame(1, count($tsumegoStatuses));
			$this->assertSame($testCase == 'normal' ? 'S' : 'V', $tsumegoStatuses[0]['TsumegoStatus']['status']);

			$this->assertSame($context->xpgained(), 0); // no xp was gained
			$this->assertSame($context->reloadUser()['rating'], ContextPreparator::$DEFAULT_USER_RATING); // xp wasn't changed
			$this->assertSame(0, count(ClassRegistry::init('TsumegoAttempt')->find('all'))); // no attempt was recorded
		}
	}

	public function testUseIntuition()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertFalse($browser->driver->executeScript("return window.besogo.intuitionActive;"));
		$this->checkPowerIsActive($browser, 'intuition');
		$browser->clickId('intuition');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$this->checkPowerIsInactive($browser, 'intuition');
		$this->assertSame($context->reloadUser()['used_intuition'], 1);
	}

	public function testIntuitionShowsCorrectSolution()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]RU[Japanese]SZ[19]AW[jb][cc][dc][kc][ed][gd][jd][fe][ie][df][gf]AB[bc][fc][gc][hc][ic][cd][fd][be][cg](;B[ee];W[de](;B[dd];W[ec];B[cb];W[eb])(;B[cb];W[db];B[dd];W[ec];B[da];W[ef];B[eb]C[+]))(;B[cb];W[db];B[ee];W[bb];B[ca];W[ac];B[bd];W[ba](;B[de];W[ab])(;B[ab];W[de]))(;B[dd];W[ec])(;B[ec];W[dd]))']]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsActive($browser, 'intuition');
		$browser->clickId('intuition');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$circles = $browser->driver->executeScript("
			return Array.from(document.querySelectorAll('#nextMoveGroup circle'))
				.map(c => ({
					cx: c.getAttribute('cx'),
					cy: c.getAttribute('cy'),
					r: c.getAttribute('r'),
					fill: c.getAttribute('fill')
				}));");
		$this->assertCount(4, $circles);
		$this->assertCount(1, array_filter($circles, fn($c) => $c['fill'] === 'green'));
	}

	public function testIntuitionPowerIsInactiveWhenIntuitionIsUsedUp()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['used_intuition' => 1], 'tsumego' => 1]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'intuition');
	}

	public function testRejuvenationRestoresHealthIntuitionAndFailedTsumegos()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['used_intuition' => 1, 'damage' => 7],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'F'],
				['set_order' => 2, 'status' => 'X']]]);
		HeroPowers::changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($context->user['damage'], 7);
		$this->checkPowerIsActive($browser, 'rejuvenation');
		$this->checkPowerIsInactive($browser, 'intuition');
		$browser->clickId('rejuvenation');
		$browser->driver->wait(10, 500)->until(function () use ($context) { return $context->reloadUser()['damage'] == 0; });
		$this->assertSame($context->user['used_rejuvenation'], 1);
		$this->assertSame($context->user['used_intuition'], 0);
		$this->checkPowerIsInactive($browser, 'rejuvenation');
		$this->checkPowerIsActive($browser, 'intuition');
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'V');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'W');
	}
}
