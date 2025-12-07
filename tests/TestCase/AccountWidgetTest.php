<?php

class AccountWidgetTest extends ControllerTestCase
{
	// by default we show level
	public function testShowLevelInAccountWidget()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE, 'xp' => 13, 'level' => 14]]);
		$browser = Browser::instance();
		$browser->get('/');
		$this->assertSame('Level 14', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('13/225', $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateXPOnSolve()
	{
		foreach (['V', 'W'] as $status)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE, 'xp' => 13, 'level' => 14, 'rating' => 1000],
				'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => $status]]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

			$this->assertSame('Level 14', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame('13/225', $browser->find('#account-bar-xp')->getText());

			$browser->playWithResult('S');

			$browser->hover($browser->find('body'));
			$this->assertSame('Level 14', $browser->find('#account-bar-xp')->getText());
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(13 + Rating::ratingToXP(1000, $status == 'V' ? 1 : Constants::$RESOLVING_MULTIPLIER)) . '/225', $browser->find('#account-bar-xp')->getText());
		}
	}

	public function testShowRankInAccountWidget()
	{
		$context = new ContextPreparator(['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'xp' => 13,
			'level' => 14,
			'rating' => 2075]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('/');
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('2075', $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateRatingInAccountWidgetOnMisplay()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'xp' => 13, 'level' => 14, 'rating' => 2075],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000]]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('2075', $browser->find('#account-bar-xp')->getText());

		$browser->playWithResult('F');

		$expectedChange = Rating::calculateRatingChange(2075, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

		$browser->hover($browser->find('body'));
		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame(strval(round(2075 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateRatingInAccountWidgetOnSolve()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'xp' => 13, 'level' => 14, 'rating' => 1000],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000]]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$this->assertSame('11k', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('1000', $browser->find('#account-bar-xp')->getText());

		$browser->playWithResult('S');

		$expectedChange = Rating::calculateRatingChange(1000, 1000, 1, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

		$browser->hover($browser->find('body'));
		$this->assertSame('11k', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame(strval(round(1000 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
	}

	public function testUpdateRankInAccountWidgetOnMisplay()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'xp' => 13, 'level' => 14, 'rating' => 2050],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]], 'rating' => 1000]]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$this->assertSame('1d', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame('2050', $browser->find('#account-bar-xp')->getText());

		$browser->playWithResult('F');

		$expectedChange = Rating::calculateRatingChange(2050, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);

		// rank gets demoted from 1d to 1k
		$browser->hover($browser->find('body'));
		$this->assertSame('1k', $browser->find('#account-bar-xp')->getText());
		$browser->hover($browser->find('#account-bar-xp'));
		$this->assertSame(strval(round(2050 + $expectedChange)), $browser->find('#account-bar-xp')->getText());
	}
}
