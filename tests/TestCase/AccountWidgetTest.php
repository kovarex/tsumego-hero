<?php

class AccountWidgetTest extends ControllerTestCase
{
	// by default we show level
	public function testShowLevelInAccountWidget()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['xp' => 13, 'level' => 5]]);
		$browser->get('/');
		$this->assertSame('Level 5', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('13/90', $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateXPOnSolve()
	{
		foreach (['V', 'W'] as $status)
		{
			$context = new ContextPreparator([
				'user' => ['xp' => 13, 'level' => 5],
				'tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => $status]]]);
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
		$browser->get('/');
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
				'tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000, 'status' => $initialStatus]]]);
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
				'tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000, 'status' => $initialStatus]]]);
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
			$this->assertWithinMargin(1000 + $expectedChange, $context->reloadUser()['rating'], 0.01);
		}
	}

	public function testUpdateRankInAccountWidgetOnMisplay()
	{
		foreach (['V', 'S'] as $initialStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => ['rating' => 2050],
				'tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000, 'status' => $initialStatus]]]);
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
}
