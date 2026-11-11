<?php

use App\Utility\UserDay;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261111140812_add_active_players_day.php';

//The migration stores the players who solved a problem each day before today from user_day, and running it again
//changes nothing
class AddActivePlayersDayMigrationTest extends AppTestCase
{
	public function testTheDaysAreThePlayersWhoSolvedEachDayBeforeToday(): void
	{
		$day = fn(int $daysAgo) => date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
		$context = new ContextPreparator(['user' => ['name' => 'alice'], 'other-users' => [['name' => 'bob'], ['name' => 'carol']]]);
		[$alice, $bob, $carol] = [(int) $context->user['id'], (int) $context->otherUsers[0]['id'], (int) $context->otherUsers[1]['id']];
		UserDay::record($alice, $day(400), 1500, true);
		UserDay::record($alice, $day(3), 1500, true);
		UserDay::record($bob, $day(3), null, true);
		UserDay::record($carol, $day(3), 1400, false);
		UserDay::record($carol, $day(2), 1400, false);
		UserDay::record($bob, $day(1), null, true);
		UserDay::record($alice, $day(0), 1500, true);
		Util::execute('DELETE FROM active_players_day');

		self::migration()->up();

		$expected = [$day(400) => 1, $day(3) => 2, $day(1) => 1];
		$this->assertSame($expected, self::stored(), 'a day of fails only has no row, today none yet');

		self::migration()->up();
		$this->assertSame($expected, self::stored(), 'running it again changes nothing');
	}

	/** @return array<string, int> */
	private static function stored(): array
	{
		return array_map('intval', array_column(Util::query('SELECT day, players FROM active_players_day ORDER BY day'), 'players', 'day'));
	}

	private static function migration(): AddActivePlayersDay
	{
		$config = require ROOT . '/phinx.php';
		$migration = new AddActivePlayersDay('test', 20261111140812);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
