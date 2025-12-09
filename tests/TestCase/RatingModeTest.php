<?php

use Facebook\WebDriver\Interactions\WebDriverActions;
use PHPUnit\Framework\Constraint\ExceptionMessage;

class RatingModeTest extends ControllerTestCase
{
	public function testRatingMode()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'other-tsumegos' =>	[
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
				'other-tsumegos' =>	[
					['rating' => 1000 - Constants::$RATING_MODE_DIFFERENCE_SETTING_3, 'description' => 'easy tsumego', 'sets' => [['name' => 'set 1', 'num' => '1']]],
					['rating' => 1000, 'description' => 'normal tsumego', 'sets' => [['name' => 'set 2', 'num' => '1']]],
					['rating' => 1000 + Constants::$RATING_MODE_DIFFERENCE_SETTING_3, 'description' => 'hard tsumego', 'sets' => [['name' => 'set 3', 'num' => '1']]]
				]
			]);

			$browser = Browser::instance();
			$browser->get('/ratingMode');
			$this->assertSame('normal tsumego', $browser->find('#descriptionText')->getText());

			$slider = $browser->find('#rangeInput');
			$size = $slider->getSize();
			$width = $size->getWidth();
			// Move to the rightmost pixel inside the slider
			$actions = new WebDriverActions($browser->driver);
			$targetPosition = 0;
			if ($difficulty == 'easy')
				$targetPosition = -$width/2 + 1;
			elseif ($difficulty == 'hard')
				$targetPosition = $width/2 - 1;
			$actions->moveToElement($slider, $targetPosition, 0)->click()->perform();

			if ($difficulty == 'easy')
				$this->assertSame('very easy', $browser->find("#sliderText")->getText());
			else if ($difficulty == 'normal')
				$this->assertSame('regular', $browser->find("#sliderText")->getText());
			else if ($difficulty == 'hard')
				$this->assertSame('very difficult', $browser->find("#sliderText")->getText());
			else
				throw new Exception('Unexpected difficulty');

			$browser->clickId('besogo-next-button');
			$this->assertSame($difficulty . ' tsumego', $browser->find('#descriptionText')->getText());
		}
	}
}
