<?php

use App\Utility\Auth;
use App\Utility\PlayOutcome;
use App\Utility\UserRatingDay;
use App\Utility\Util;

//The highest rating of each day of a player (UserRatingDay, user_rating_day): kept with each attempt the site writes;
//the days before were filled by its migration (AddUserRatingDayMigrationTest)
class UserRatingDayTest extends IntegrationTestCase
{
	private function play(ContextPreparator $context, PlayOutcome $outcome): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 0, 'outcome' => $outcome->value]);
		$this->assertResponseOk();
	}

	/** @return array<string, int> the rating by day */
	private static function days(int $userID): array
	{
		return array_map('intval', array_column(Util::query('SELECT day, rating FROM user_rating_day WHERE user_id = ? ORDER BY day', [$userID]), 'rating', 'day'));
	}

	public function testANewAttemptKeepsTheRatingItWasWrittenWith(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => 1]);

		$this->play($context, PlayOutcome::SolvedClean);

		$attempt = Util::query('SELECT user_rating, DATE(created) AS day FROM tsumego_attempt WHERE user_id = ?', [$context->user['id']])[0];
		$this->assertSame([$attempt['day'] => (int) $attempt['user_rating']], self::days((int) $context->user['id']));
		$this->assertSame(date('Y-m-d'), $attempt['day']);
	}

	//A continued attempt moves to today; the day it was on keeps the rating the player had then
	public function testAContinuedAttemptKeepsTheRatingOfTodayAndLeavesItsOldDay(): void
	{
		$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
		$context = new ContextPreparator([
			'user' => ['rating' => 1500],
			'tsumego' => ['attempt' => ['solved' => false, 'user_rating' => 1400, 'created' => $threeDaysAgo . ' 10:00:00'], 'set_order' => 1]]);
		$userID = (int) $context->user['id'];
		$this->assertSame([$threeDaysAgo => 1400], self::days($userID));

		$this->play($context, PlayOutcome::Failed);

		$this->assertCount(1, Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the attempt was continued');
		$today = Util::query('SELECT user_rating FROM tsumego_attempt WHERE user_id = ?', [$userID])[0]['user_rating'];
		$this->assertSame([$threeDaysAgo => 1400, date('Y-m-d') => (int) $today], self::days($userID));
	}

	//A day keeps its highest rating, however the attempts of the day go
	public function testADayKeepsItsHighestRating(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userID = (int) $context->user['id'];

		UserRatingDay::record($userID, '2026-03-04', 1500.4);
		UserRatingDay::record($userID, '2026-03-04', 1450);
		$this->assertSame(['2026-03-04' => 1500], self::days($userID), 'a lower rating later keeps the higher');
		UserRatingDay::record($userID, '2026-03-04', 1520.6);
		$this->assertSame(['2026-03-04' => 1521], self::days($userID), 'a higher one replaces it, rounded as user_rating is');
	}

	public function testAnAttemptWithoutARatingKeepsNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		UserRatingDay::record((int) $context->user['id'], '2026-03-04', null);

		$this->assertSame([], self::days((int) $context->user['id']));
	}
}
