<?php

use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\UserDay;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261111163024_time_mode_solves_in_user_day.php';

//The migration marks the days of the time mode solves so far as active (user_day.solved) and counts the stored active
//players again: a solved attempt counts on the day it started, or its session's day when it has no start; a failed
//one does not; a level mode day keeps its rating; running it again changes nothing
class TimeModeSolvesInUserDayMigrationTest extends AppTestCase
{
	public function testThePastTimeModeSolvesMakeTheirDaysActive(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED]]]);
		$alice = (int) $context->user['id'];
		$bob = (int) $context->otherUsers[0]['id'];
		$aliceSession = (int) $context->timeModeSessions[0]->id;
		Util::execute('INSERT INTO time_mode_session (user_id, created, time_mode_session_status_id, time_mode_category_id, time_mode_rank_id)
			SELECT ?, ?, time_mode_session_status_id, time_mode_category_id, time_mode_rank_id FROM time_mode_session WHERE id = ?', [$bob, $day(6) . ' 09:00:00', $aliceSession]);
		$bobSession = (int) Util::query('SELECT MAX(id) AS id FROM time_mode_session')[0]['id'];
		$attempt = fn(int $session, int $status, ?string $started, int $order) => Util::execute(
			'INSERT INTO time_mode_attempt (tsumego_id, `order`, time_mode_session_id, time_mode_attempt_status_id, started) VALUES (?, ?, ?, ?, ?)',
			[$context->tsumegos[0]['id'], $order, $session, $status, $started]);
		$attempt($aliceSession, TimeModeAttemptStatusTable::SOLVED, $day(5) . ' 10:00:00.25', 1);
		$attempt($aliceSession, TimeModeAttemptStatusTable::SOLVED, $day(5) . ' 23:59:59.75', 2);
		$attempt($aliceSession, TimeModeAttemptStatusTable::FAILED, $day(4) . ' 10:00:00', 3);
		$attempt($aliceSession, TimeModeAttemptStatusTable::SOLVED, $day(3) . ' 10:00:00', 4);
		$attempt($bobSession, TimeModeAttemptStatusTable::SOLVED, null, 1);
		$attempt($bobSession, TimeModeAttemptStatusTable::TIMEOUT, $day(2) . ' 10:00:00', 2);
		Util::execute('DELETE FROM user_day');
		Util::execute('DELETE FROM active_players_day');
		// alice played in level mode 3 days ago without solving
		UserDay::record($alice, $day(3), 1500, false);

		self::migration()->up();

		$expected = [
			[$alice, $day(5), null, 1],
			[$alice, $day(3), 1500, 1],
			[$bob, $day(6), null, 1]];
		$this->assertSame($expected, self::days());
		$this->assertSame([$day(6) => 1, $day(5) => 1, $day(3) => 1], self::stored(), 'the active players counted again');

		self::migration()->up();
		$this->assertSame($expected, self::days(), 'running it again changes nothing');
		$this->assertSame([$day(6) => 1, $day(5) => 1, $day(3) => 1], self::stored());
	}

	/** @return list<array{0: int, 1: string, 2: ?int, 3: int}> */
	private static function days(): array
	{
		return array_map(fn($row) => [(int) $row['user_id'], $row['day'], $row['rating'] === null ? null : (int) $row['rating'], (int) $row['solved']],
			Util::query('SELECT user_id, day, rating, solved FROM user_day ORDER BY user_id, day'));
	}

	/** @return array<string, int> */
	private static function stored(): array
	{
		return array_map('intval', array_column(Util::query('SELECT day, players FROM active_players_day ORDER BY day'), 'players', 'day'));
	}

	private static function migration(): TimeModeSolvesInUserDay
	{
		$config = require ROOT . '/phinx.php';
		$migration = new TimeModeSolvesInUserDay('test', 20261111163024);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
