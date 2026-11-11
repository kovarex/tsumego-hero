<?php

use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\ActivePlayers;
use App\Utility\Auth;
use App\Utility\PlayOutcome;
use App\Utility\UserDay;
use App\Utility\Util;

//A solve in the time mode makes the day one the player was active on (user_day.solved), as a level mode solve does;
//a failed time mode attempt does not
class TimeModeActiveDayTest extends IntegrationTestCase
{
	private function playInTimeMode(PlayOutcome $outcome): ContextPreparator
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED]]]]]);
		// the player opens the play page, which is what puts the account into time mode
		$this->post('/timeMode/play');
		Auth::init();
		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 5, 'outcome' => $outcome->value]);
		return $context;
	}

	public function testATimeModeSolveMakesTheDayActive(): void
	{
		$context = $this->playInTimeMode(PlayOutcome::SolvedClean);
		$userID = (int) $context->user['id'];

		$this->assertSame([], Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the time mode writes no tsumego_attempt');
		$this->assertSame([['rating' => null, 'solved' => 1]], array_map(fn($row) => ['rating' => $row['rating'], 'solved' => (int) $row['solved']],
			Util::query('SELECT rating, solved FROM user_day WHERE user_id = ? AND day = ?', [$userID, date('Y-m-d')])), 'a day without a rating');
		$this->assertSame(1, ActivePlayers::count(date('Y-m-d')));
	}

	public function testAFailedTimeModeAttemptDoesNot(): void
	{
		$context = $this->playInTimeMode(PlayOutcome::Failed);

		$this->assertSame(TimeModeAttemptStatusTable::FAILED, (int) Util::query('SELECT time_mode_attempt_status_id AS status FROM time_mode_attempt WHERE time_mode_session_id = ?', [$context->timeModeSessions[0]->id])[0]['status']);
		$this->assertSame([], Util::query('SELECT day FROM user_day WHERE user_id = ?', [$context->user['id']]));
		$this->assertSame(0, ActivePlayers::count(date('Y-m-d')));
	}

	//A level mode day keeps its rating when a time mode solve comes the same day
	public function testATimeModeSolveKeepsTheRatingOfTheDay(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userID = (int) $context->user['id'];
		UserDay::record($userID, date('Y-m-d'), 1500, false);
		UserDay::record($userID, date('Y-m-d'), null, true);

		$this->assertSame([['rating' => 1500, 'solved' => 1]], array_map(fn($row) => ['rating' => (int) $row['rating'], 'solved' => (int) $row['solved']],
			Util::query('SELECT rating, solved FROM user_day WHERE user_id = ?', [$userID])));
	}
}
