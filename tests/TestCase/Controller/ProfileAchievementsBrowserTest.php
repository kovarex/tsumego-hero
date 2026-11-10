<?php

use App\Utility\AchievementId;
use App\Utility\AchievementRarity;

//Show all of the achievements on the profile shows the rows past the rarest ten, and Show the rarest hides them again
#[RetryOnBrowserFailure]
class ProfileAchievementsBrowserTest extends IntegrationTestCase
{
	public function testShowAllShowsTheRestAndBack(): void
	{
		AchievementRarity::forget();
		$ids = [AchievementId::EMERALD, AchievementId::SAPPHIRE, AchievementId::RUBY, AchievementId::DIAMOND, AchievementId::USER_OF_THE_DAY,
			AchievementId::FAVORITES, AchievementId::SPRINT, AchievementId::GOLD_DIGGER, AchievementId::BAD_POTION, AchievementId::SOLVE_1D,
			AchievementId::SOLVE_2D, AchievementId::SOLVE_3D];
		$context = new ContextPreparator(['user' => ['name' => 'player', 'solved' => 1, 'achievement-statuses' => array_map(fn(AchievementId $id) => ['id' => $id->value], $ids)]]);
		$browser = Browser::instance();

		$browser->get('/users/view/' . $context->user['id']);
		$visibleRows = 'return [...document.querySelectorAll(".profile-achievement")].filter(row => row.offsetParent !== null).length;';
		$this->assertSame(10, $browser->driver->executeScript($visibleRows));

		$browser->clickId('profile-achievements-toggle');
		$this->assertSame(12, $browser->driver->executeScript($visibleRows));
		$this->assertSame('Show the rarest 10', $browser->driver->executeScript('return document.getElementById("profile-achievements-toggle").textContent;'));

		$browser->clickId('profile-achievements-toggle');
		$this->assertSame(10, $browser->driver->executeScript($visibleRows));
		$this->assertSame('Show all 12', $browser->driver->executeScript('return document.getElementById("profile-achievements-toggle").textContent;'));
		$browser->assertNoJsErrors();
		AchievementRarity::forget();
	}
}
