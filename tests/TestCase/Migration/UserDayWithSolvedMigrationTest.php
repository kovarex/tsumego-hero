<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261111102421_user_day_with_solved.php';

//The migration fills user_day from the attempts so far: a row for each day of each player, with the highest
//user_rating (NULL when no attempt had one) and whether an attempt was solved, walking the user ids in batches; the
//rows already there keep their higher rating, and running it again changes nothing
class UserDayWithSolvedMigrationTest extends AppTestCase
{
	public function testTheDaysAreEveryDayOfEachPlayerWithItsRatingAndSolve(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob'], ['name' => 'carol']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]]]]]);
		$tsumegoID = (int) $context->tsumegos[0]['id'];
		$insert = fn(int $userID, ?int $rating, bool $solved, string $created) => Util::execute(
			'INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, ?, 0, ?, 1000, ?)',
			[$userID, $tsumegoID, $solved ? 1 : 0, $rating, $created]);
		$aliceID = (int) $context->user['id'];
		$bobID = (int) $context->otherUsers[0]['id'];
		$insert($aliceID, 1200, false, $day(400) . ' 00:00:00');
		$insert($aliceID, 1260, true, $day(400) . ' 23:59:59');
		$insert($aliceID, 1500, false, $day(30) . ' 10:00:00');
		$insert($aliceID, 1490, false, $day(2) . ' 10:00:00');
		$insert($aliceID, null, true, $day(2) . ' 11:00:00');
		$insert($bobID, null, true, $day(5) . ' 08:00:00');
		$insert($bobID, null, false, $day(6) . ' 08:00:00');
		// carol's id is past the first batches of ids, her attempts move with it
		$carolID = 2 * UserDayWithSolved::USERS_PER_STATEMENT + 500;
		$insert((int) $context->otherUsers[1]['id'], 1700, true, $day(1) . ' 08:00:00');
		Util::execute('UPDATE user SET id = ? WHERE id = ?', [$carolID, $context->otherUsers[1]['id']]);
		// what the earlier migration kept: the days with a rating, one of them higher than its attempts
		Util::execute('DELETE FROM user_day');
		Util::execute('INSERT INTO user_day (user_id, day, rating) VALUES (?, ?, 1260), (?, ?, 1550)', [$aliceID, $day(400), $aliceID, $day(30)]);

		self::migration()->up();

		$expected = [
			[$aliceID, $day(400), 1260, 1],
			[$aliceID, $day(30), 1550, 0],
			[$aliceID, $day(2), 1490, 1],
			[$bobID, $day(6), null, 0],
			[$bobID, $day(5), null, 1],
			[$carolID, $day(1), 1700, 1]];
		$this->assertSame($expected, self::days());

		self::migration()->up();
		$this->assertSame($expected, self::days(), 'running it again changes nothing');
	}

	/** @return list<array{0: int, 1: string, 2: ?int, 3: int}> */
	private static function days(): array
	{
		return array_map(fn($row) => [(int) $row['user_id'], $row['day'], $row['rating'] === null ? null : (int) $row['rating'], (int) $row['solved']],
			Util::query('SELECT user_id, day, rating, solved FROM user_day ORDER BY user_id, day'));
	}

	private static function migration(): UserDayWithSolved
	{
		$config = require ROOT . '/phinx.php';
		$migration = new UserDayWithSolved('test', 20261111102421);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
