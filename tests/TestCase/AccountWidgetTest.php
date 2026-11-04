<?php

use App\Model\Table\TimeModeCategoryTable;
use App\Utility\Constants;
use App\Utility\Level;
use App\Utility\Rating;

#[RetryOnBrowserFailure]
class AccountWidgetTest extends IntegrationTestCase
{
	// by default we show level
	public function testShowLevelInAccountWidget()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['xp' => 13, 'level' => 5]]);
		$browser->get('sites/blank');
		$this->assertSame('Level 5', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('13/90', $browser->find('#account-bar-xp')->getText());
	}

	//The bar counts the XP to the next level with the server's table of levels
	public function testTheXPToTheNextLevelIsTheServers()
	{
		new ContextPreparator(['user' => ['xp' => 13, 'level' => 5]]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$levels = range(1, 120);
		$this->assertSame(
			array_map(fn($level) => Level::getXPForNext($level), $levels),
			$browser->driver->executeScript('return arguments[0].map(level => window.eval("accountWidget").xpForNextLevel(level));', [$levels]));
	}

	public function testUpdateXPOnSolve()
	{
		foreach (['V', 'W'] as $status)
		{
			$context = new ContextPreparator([
				'user' => ['xp' => 13, 'level' => 5],
				'tsumego' => ['set_order' => 1, 'status' => $status]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

			$this->assertSame('Level 5', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame('13/90', $browser->find('#account-bar-xp')->getText());

			$browser->playWithResult('S');

			$browser->hover($browser->find('body'));
			$this->assertSame('Level 5', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(13 + Rating::ratingToXP(1000, $status == 'V' ? 1 : Constants::$RESOLVING_MULTIPLIER)) . '/90', $browser->find('#account-bar-xp')->getText());
		}
	}

	public function testShowRankInAccountWidget()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['rating' => 2075]]);
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('sites/blank');
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('2075', $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateRatingInAccountWidgetOnMisplay()
	{
		foreach (['V', 'S'] as $initialStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => ['rating' => 2075],
				'tsumego' => ['set_order' => 1, 'rating' => 1000, 'status' => $initialStatus]]);
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

			$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame('2075', $browser->find('#account-bar-xp')->getText());

			$browser->playWithResult('F');

			$expectedChange = $initialStatus == 'S' ? 0 : Rating::calculateRatingChange(2075, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

			$browser->hover($browser->find('body'));
			$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(round(2075 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertLessThan(abs(1000 + $expectedChange - $context->reloadUser()['rating']), 0.01);
		}
	}

	public function testUpdateRatingInAccountWidgetOnSolve()
	{
		foreach (['V', 'S'] as $initialStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['set_order' => 1, 'rating' => 1000, 'status' => $initialStatus]]);
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

			$this->assertSame('11k', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame('1000', $browser->find('#account-bar-xp')->getText());

			$browser->playWithResult('S');

			// already solved problem doesn't update rating
			$expectedChange = $initialStatus == 'S' ? 0 : Rating::calculateRatingChange(1000, 1000, 1, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

			$browser->hover($browser->find('body'));
			$this->assertSame('11k', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(round(1000 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertEqualsWithDelta(1000 + $expectedChange, $context->reloadUser()['rating'], 0.01);
		}
	}

	public function testUpdateRankInAccountWidgetOnMisplay()
	{
		foreach (['V', 'S'] as $initialStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => ['rating' => 2050],
				'tsumegos' => [['set_order' => 1, 'rating' => 1000, 'status' => $initialStatus]]]);
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

			$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame('2050', $browser->find('#account-bar-xp')->getText());

			$browser->playWithResult('F');

			// already solved problem doesn't update rating
			$expectedChange = $initialStatus == 'S' ? 0 : Rating::calculateRatingChange(2050, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

			// rank gets demoted from 1d to 1k
			$browser->hover($browser->find('body'));
			$this->assertSame($initialStatus == 'S' ? '1d' : '1k', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(round(2050 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
		}
	}

	public function testSettingsLevelRadioSwitchesWidgetToLevel()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['rating' => 2075]]);
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('users/settings');

		// AccountWidget starts in rating mode (shows a rank, not "Level")
		$ratingText = $browser->find('#account-bar-xp')->getText();
		$this->assertStringStartsNotWith('Level', $ratingText);

		// Click "Level" radio button on the settings page
		$browser->clickId('levelBarDisplay1');
		$browser->hover($browser->find('body'));

		// AccountWidget should now show level
		$this->assertStringStartsWith('Level', $browser->find('#account-bar-xp')->getText());
	}

	public function testSettingsRatingRadioSwitchesWidgetToRating()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['rating' => 2075]]);
		$browser->get('users/settings');

		// AccountWidget starts in level mode by default
		$this->assertStringStartsWith('Level', $browser->find('#account-bar-xp')->getText());

		// Click "Rating" radio button on the settings page
		$browser->clickId('levelBarDisplay2');
		$browser->hover($browser->find('body'));

		// AccountWidget should now show rating (a rank, not "Level")
		$ratingText = $browser->find('#account-bar-xp')->getText();
		$this->assertStringStartsNotWith('Level', $ratingText);
	}

	public function testSettingsRatingRadioPersistsAcrossPageLoad()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['rating' => 2075]]);
		$browser->get('users/settings');

		$browser->clickId('levelBarDisplay2');
		$browser->hover($browser->find('body'));

		// Reload the page - preference should persist
		$browser->get('users/settings');
		$this->assertStringStartsNotWith('Level', $browser->find('#account-bar-xp')->getText());
	}

	//The menu of the account opens while the pointer is over the bar, and the switch in it turns the bar to the rank
	public function testTheMenuOfTheBarSwitchesItToTheRank()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['xp' => 13, 'level' => 5, 'rating' => 2075]]);
		$browser->get('sites/blank');
		$this->assertFalse($browser->find('#heroLogout')->isDisplayed());

		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertTrue($browser->find('#heroLogout')->isDisplayed());
		$this->assertSame('Rating Bar', $browser->find('#textBarInMenu')->getText());
		$browser->find('#textBarInMenu')->click();

		$this->assertSame('Level Bar', $browser->find('#textBarInMenu')->getText());
		$browser->hover($browser->find('body'));
		$this->assertFalse($browser->find('#heroLogout')->isDisplayed());
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->get('sites/blank');
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
	}

	public function testTheModeSelectorSwitchesTheBar()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['rating' => 2075]]);
		$browser->get('sites/blank');

		$browser->driver->executeScript('document.getElementById("modeSelector").click();');
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->driver->executeScript('document.getElementById("modeSelector").click();');
		$this->assertStringStartsWith('Level ', $browser->find('#account-bar-xp')->getText());
	}

	//In a time mode run the bar counts the problems right and wrong as they are played
	public function testTheTimeModeBarCountsASolveAtOnce()
	{
		$parameters = ['user' => ['mode' => Constants::$LEVEL_MODE], 'time-mode-ranks' => ['5k']];
		for ($i = 0; $i < 3; ++$i)
			$parameters['tsumegos'][] = ['set_order' => $i];
		$context = new ContextPreparator($parameters);
		$browser = Browser::instance();
		$browser->get('timeMode/start?categoryID=' . TimeModeCategoryTable::SLOW . '&rankID=' . $context->timeModeRanks[0]['id']);
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('0 right', $browser->find('#account-bar-xp')->getText());
		$widthBefore = $browser->driver->executeScript('return document.getElementById("xp-bar-fill").style.width;');

		$browser->playWithResult('S');

		$browser->hover($browser->find('body'));
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('1 right', $browser->find('#account-bar-xp')->getText());
		$this->assertNotSame($widthBefore, $browser->driver->executeScript('return document.getElementById("xp-bar-fill").style.width;'));
	}
}
