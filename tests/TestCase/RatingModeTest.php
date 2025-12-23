<?php

use Facebook\WebDriver\Interactions\WebDriverActions;
use PHPUnit\Framework\Constraint\ExceptionMessage;

class RatingModeTest extends ControllerTestCase
{
	public function testRatingMode()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumegos' =>	[
				['rating' => 500, 'description' => '500 rating tsumego', 'sets' => [['name' => 'set 1', 'num' => '1']]],
				['rating' => 1000, 'description' => '1000 rating tsumego', 'sets' => [['name' => 'set 2', 'num' => '1']]],
				['rating' => 1500, 'description' => '1500 rating tsumego', 'sets' => [['name' => 'set 3', 'num' => '1']]]
			]
		]);

		$browser = Browser::instance();
		$browser->get('/ratingMode');
		$this->assertSame('1000 rating tsumego', $browser->find('#descriptionText')->getText());
		Auth::getUser()['rating'] = 500;
		Auth::saveUser();

		$browser->clickId('besogo-next-button');
		$this->assertSame('500 rating tsumego', $browser->find('#descriptionText')->getText());
	}

	public function testRatingModeDifficultySelection()
	{
		foreach (['easy', 'normal', 'hard'] as $difficulty)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumegos' =>	[
					['rating' => 1000 - Constants::$RATING_MODE_DIFFERENCE_SETTING_3, 'description' => 'easy tsumego', 'sets' => [['name' => 'set 1', 'num' => '1']]],
					['rating' => 1000, 'description' => 'normal tsumego', 'sets' => [['name' => 'set 2', 'num' => '1']]],
					['rating' => 1000 + Constants::$RATING_MODE_DIFFERENCE_SETTING_3, 'description' => 'hard tsumego', 'sets' => [['name' => 'set 3', 'num' => '1']]]
				]
			]);

			$browser = Browser::instance();
			$browser->get('/ratingMode');
			$this->assertSame('normal tsumego', $browser->find('#descriptionText')->getText());

			// moving the slider either to left, moddle or right based on selected difficulty.
			$slider = $browser->find('#rangeInput');
			$size = $slider->getSize();
			$width = $size->getWidth();
			$actions = new WebDriverActions($browser->driver);
			$targetPosition = 0;
			if ($difficulty == 'easy')
				$targetPosition = -$width / 2 + 1;
			elseif ($difficulty == 'hard')
				$targetPosition = $width / 2 - 1;
			$actions->moveToElement($slider, $targetPosition, 0)->click()->perform();

			if ($difficulty == 'easy')
				$expectedDifficultyLabel = 'very easy';
			elseif ($difficulty == 'normal')
				$expectedDifficultyLabel = 'regular';
			elseif ($difficulty == 'hard')
				$expectedDifficultyLabel = 'very difficult';
			else
				throw new Exception('Unexpected difficulty');

			$this->assertSame($expectedDifficultyLabel, $browser->find("#sliderText")->getText());

			for ($i = 0; $i < 2; $i++)
			{
				$browser->clickId('besogo-next-button');
				$this->assertSame($difficulty . ' tsumego', $browser->find('#descriptionText')->getText());
				$this->assertSame($expectedDifficultyLabel, $browser->find("#sliderText")->getText());
			}
		}
	}

	public function testRatingModeResetButton()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumegos' =>	[['rating' => 1000, 'description' => '1000 rating tsumego', 'sets' => [['name' => 'set 2', 'num' => '1']]]]]);

		$browser = Browser::instance();
		$browser->get('/ratingMode');
		$this->assertSame('1000 rating tsumego', $browser->find('#descriptionText')->getText());
		$this->assertSame(0, $browser->driver->executeScript('return boardLockValue;'));
		$browser->playWithResult('F');
		$this->assertSame(0, $browser->driver->executeScript('return boardLockValue;')); // #107 we're not locking failed problems
		$browser->clickId('besogo-reset-button');
		$this->assertSame(0, $browser->driver->executeScript('return boardLockValue;'));
		$browser->playWithResult('S');
		$browser->clickId('besogo-next-button');
		$tsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('first')['TsumegoAttempt'];
		$this->assertSame(1, $tsumegoAttempt['misplays']);
		$this->assertSame(true, $tsumegoAttempt['solved']);
	}
}
