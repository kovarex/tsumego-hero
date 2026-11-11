<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261111005204_add_user_rating_day.php';

//The migration fills user_rating_day from the attempts so far: the highest user_rating of each day of each player,
//walking the user ids in batches, and running it again changes nothing
class AddUserRatingDayMigrationTest extends AppTestCase
{
	public function testTheDaysAreTheHighestRatingOfEachDayOfEachPlayer(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$attempt = fn(int $daysAgo, int $rating, string $time = '10:00:00') => ['solved' => true, 'user_rating' => $rating, 'created' => $day($daysAgo) . ' ' . $time];
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob'], ['name' => 'carol']],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => [
				$attempt(400, 1200, '00:00:00'), $attempt(400, 1260, '23:59:59'), $attempt(400, 1230),
				$attempt(30, 1500), $attempt(2, 1490, '12:00:00'), $attempt(2, 1480)]]]]);
		$aliceID = (int) $context->user['id'];
		$tsumegoID = (int) $context->tsumegos[0]['id'];
		$insert = fn(int $userID, ?int $rating, string $created) => Util::execute(
			'INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, user_rating, tsumego_rating, created) VALUES (?, ?, 0, 1, 0, ?, 1000, ?)',
			[$userID, $tsumegoID, $rating, $created]);
		$bobID = (int) $context->otherUsers[0]['id'];
		$insert($bobID, 900, $day(5) . ' 08:00:00');
		$insert($bobID, null, $day(5) . ' 09:00:00');
		$insert($bobID, null, $day(6) . ' 08:00:00');
		// carol's id is past the first batches of ids, her attempts move with it
		$carolID = 2 * AddUserRatingDay::USERS_PER_STATEMENT + 500;
		$insert((int) $context->otherUsers[1]['id'], 1700, $day(1) . ' 08:00:00');
		Util::execute('UPDATE user SET id = ? WHERE id = ?', [$carolID, $context->otherUsers[1]['id']]);
		Util::execute('DELETE FROM user_rating_day');

		self::migration()->up();

		$expected = [
			[$aliceID, $day(400), 1260],
			[$aliceID, $day(30), 1500],
			[$aliceID, $day(2), 1490],
			[$bobID, $day(5), 900],
			[$carolID, $day(1), 1700]];
		$this->assertSame($expected, self::days(), 'a day of no rating at all has no row');

		self::migration()->up();
		$this->assertSame($expected, self::days(), 'running it again changes nothing');
	}

	/** @return list<array{0: int, 1: string, 2: int}> */
	private static function days(): array
	{
		return array_map(fn($row) => [(int) $row['user_id'], $row['day'], (int) $row['rating']],
			Util::query('SELECT user_id, day, rating FROM user_rating_day ORDER BY user_id, day'));
	}

	private static function migration(): AddUserRatingDay
	{
		$config = require ROOT . '/phinx.php';
		$migration = new AddUserRatingDay('test', 20261111005204);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
