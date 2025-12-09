<?php

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
		$browser->get('/ratingMode');
		$this->assertSame('500 rating tsumego', $browser->find('#descriptionText')->getText());
	}
}
