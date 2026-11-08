<?php

use App\Utility\Auth;
use App\Utility\Util;

//The icons of the mistake training and the challenges in the menu bar, with the count of what waits
class HeaderIconsTest extends IntegrationTestCase
{
	private function header(ContextPreparator $context): string
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->get('/');
		return $this->view;
	}

	public function testTheIconsCountTheReviewsDueAndTheChallengesWaiting(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'player'],
			'other-users' => [['name' => 'friend']],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))]],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-2 days'))]],
				['set_order' => 3, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))]],
			],
		]);
		$rank = $context->timeModeRanks[0]['id'];
		Util::execute('INSERT INTO challenge (created_by_user_id, minimum_time_mode_rank_id, maximum_time_mode_rank_id, seconds_per_problem) VALUES (?, ?, ?, 30)', [$context->otherUsers[0]['id'], $rank, $rank]);
		Util::execute('INSERT INTO challenge_participant (challenge_id, user_id) VALUES (LAST_INSERT_ID(), ?)', [$context->user['id']]);

		$page = $this->header($context);
		$this->assertMatchesRegularExpression('#<a id="header-mistake-training"[^>]*href="/mistake-training/overview"[^>]*aria-label="Mistake training: 2 problems to review today">.*?<span class="header-icons__badge" aria-hidden="true">2</span>#s', $page);
		$this->assertMatchesRegularExpression('#<a id="header-challenges"[^>]*href="/challenges"[^>]*aria-label="Challenges: 1 waiting for you to play it">.*?<span class="header-icons__badge" aria-hidden="true">1</span>#s', $page);
	}

	public function testNothingWaitingShowsTheIconsWithoutABadge(): void
	{
		$page = $this->header(new ContextPreparator(['user' => ['name' => 'player']]));
		$this->assertStringContainsString('id="header-mistake-training"', $page);
		$this->assertStringContainsString('aria-label="Mistake training: nothing to review today"', $page);
		$this->assertStringContainsString('id="header-challenges"', $page);
		$this->assertStringNotContainsString('header-icons__badge', $page);
	}

	public function testAGuestHasNoIcons(): void
	{
		new ContextPreparator([]);
		unset($_COOKIE['hackedLoggedInUserID']);
		Auth::init();
		$this->get('/');
		$this->assertStringNotContainsString('header-icons', $this->view);
	}
}
