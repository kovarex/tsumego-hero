<?php

use PHPUnit\Framework\Attributes\Group;

//A row of buttons that switches between boards or charts marks the one shown the same way everywhere: the highscore
//boards, the time mode and the collection page give it .btn--inactive (the lighter grey), and so does the profile.
#[Group('browser')]
#[RetryOnBrowserFailure]
class SelectedSwitchBrowserTest extends IntegrationTestCase
{
	public function testTheProfileMarksTheChartShownLikeTheHighscoreMarksItsBoard(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$browser = Browser::instance();

		$browser->get('users/highscore');
		$board = $browser->driver->executeScript(<<<'JS'
			const selected = [...document.querySelectorAll('.highscore-nav .btn')].filter(button => button.classList.contains('btn--inactive'));
			return {texts: selected.map(button => button.textContent.trim()), background: getComputedStyle(selected[0]).backgroundColor};
			JS);
		$this->assertSame(['level'], $board['texts']);

		$browser->get('users/view/' . $context->user['id']);
		$selected = fn() => $browser->driver->executeScript(<<<'JS'
			const selected = [...document.querySelectorAll('[data-profile-show]')].filter(button => button.classList.contains('btn--inactive'));
			return {ids: selected.map(button => button.id), background: selected.length ? getComputedStyle(selected[0]).backgroundColor : null};
			JS);
		// the left side opens on the level, the right one on the rating
		$profile = $selected();
		$this->assertSame(['userShowLevelButtonLeft', 'userShowRatingButtonRight'], $profile['ids']);
		$this->assertSame($board['background'], $profile['background'], 'The chart shown has the colour of the board shown');
		$this->assertSame('true', $browser->findById('userShowLevelButtonLeft')->getAttribute('aria-pressed'));
		$this->assertSame('false', $browser->findById('userShowRatingButtonLeft')->getAttribute('aria-pressed'));

		$browser->clickId('userShowTimeButtonLeft');
		$this->assertSame(['userShowTimeButtonLeft', 'userShowRatingButtonRight'], $selected()['ids']);
		$browser->assertNoJsErrors();
	}
}
