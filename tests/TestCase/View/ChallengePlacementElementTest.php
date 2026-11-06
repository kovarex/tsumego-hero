<?php

use App\View\AppView;

//The icon of a place in a challenge: medals for the first three, the number after them, the place in words for screen readers.
class ChallengePlacementElementTest extends AppTestCase
{
	public function testMedalsForTheFirstThreeAndNumbersAfterThem(): void
	{
		$view = new AppView();
		$render = fn(int $place) => $view->element('Challenges/placement', ['place' => $place]);

		foreach ([1 => ['gold', '1st place'], 2 => ['silver', '2nd place'], 3 => ['bronze', '3rd place']] as $place => [$medal, $label])
		{
			$icon = $render($place);
			$this->assertStringContainsString('challenge-place--' . $medal . '" role="img" aria-label="' . $label . '"', $icon);
			$this->assertStringContainsString('<svg', $icon);
		}

		$icon = $render(11);
		$this->assertStringContainsString('challenge-place--rank" role="img" aria-label="11th place"', $icon);
		$this->assertStringContainsString('>11</span>', $icon);
		$this->assertStringNotContainsString('<svg', $icon);
	}
}
