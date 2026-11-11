<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\Auth;
use App\Utility\Clock;
use App\Utility\DailyReset;
use App\Utility\DailyStreak;
use App\Utility\Util;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

//The hearts and the hero powers come back when the player's own day turns (DailyReset), at
//DailyStreak::DAY_START_HOUR in their time zone like the daily streak, not with the daily cron at midnight UTC.
class DailyResetTest extends IntegrationTestCase
{
	use ConsoleIntegrationTestTrait;

	private const array POWERS = ['used_refinement', 'used_sprint', 'used_rejuvenation', 'used_potion', 'used_intuition', 'used_revelation'];

	protected function tearDown(): void
	{
		Clock::freeze(null);
		parent::tearDown();
	}

	//A player in $timeZone who lost 7 hearts and used every hero power on their day $lastDay
	private function playerOf(string $timeZone, string $lastDay = '2026-11-09'): ContextPreparator
	{
		// the fixture signs the player in, at noon UTC of their last day, so no reset is due yet
		self::freezeAtNoonOf($lastDay);
		return new ContextPreparator([
			'user' => ['name' => 'player', 'level' => 100, 'time_zone' => $timeZone, 'damage' => 7, 'daily_reset_day' => $lastDay]
				+ array_fill_keys(self::POWERS, 1),
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_POTION, 'value' => 1]]]);
	}

	private static function freezeAtNoonOf(string $day): void
	{
		Clock::freeze(new DateTimeImmutable($day . ' 12:00:00', new DateTimeZone('UTC')));
	}

	//The player signs in at $utc: the damage, the hero powers used and the day of the last reset as the
	//signed-in row has them, which the row in the database has too
	/** @return array{damage: int, powers: int, day: ?string} */
	private function signInAt(ContextPreparator $context, string $utc): array
	{
		Clock::freeze(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$signedIn = Auth::getUser();
		$stored = Util::query('SELECT damage, ' . implode(', ', self::POWERS) . ', daily_reset_day FROM user WHERE id = ?', [$context->user['id']])[0];
		foreach (['damage', ...self::POWERS] as $column)
			$this->assertSame((int) $stored[$column], (int) $signedIn[$column], $column . ' of the signed-in row is the stored one');
		return [
			'damage' => (int) $stored['damage'],
			'powers' => array_sum(array_map(fn(string $column) => (int) $stored[$column], self::POWERS)),
			'day' => $stored['daily_reset_day'],
		];
	}

	public function testInPragueTheHeartsComeBackAtThreeInTheMorning(): void
	{
		$context = $this->playerOf('Europe/Prague');

		// 02:30 in Prague, 01:30 UTC: past midnight of the server and of the player, but the 9th still goes on
		$this->assertSame(['damage' => 7, 'powers' => 6, 'day' => '2026-11-09'], $this->signInAt($context, '2026-11-10 01:30:00'));
		// 03:01 in Prague
		$this->assertSame(['damage' => 0, 'powers' => 0, 'day' => '2026-11-10'], $this->signInAt($context, '2026-11-10 02:01:00'));
		$this->assertSame(0.0, AchievementConditionTable::value((int) $context->user['id'], AchievementConditionTable::CATEGORY_POTION), 'The potions of the day start over too');
	}

	public function testInNewYorkTheHeartsComeBackAtThreeInTheMorning(): void
	{
		$context = $this->playerOf('America/New_York');

		// 19:00 of the 9th in New York, the 10th already in UTC
		$this->assertSame(['damage' => 7, 'powers' => 6, 'day' => '2026-11-09'], $this->signInAt($context, '2026-11-10 00:00:00'));
		// 02:30 of the 10th in New York
		$this->assertSame(['damage' => 7, 'powers' => 6, 'day' => '2026-11-09'], $this->signInAt($context, '2026-11-10 07:30:00'));
		// 03:01 in New York
		$this->assertSame(['damage' => 0, 'powers' => 0, 'day' => '2026-11-10'], $this->signInAt($context, '2026-11-10 08:01:00'));
	}

	//A hero power used at 02:00 in Prague is of the 9th, so it is there again at 03:00
	public function testAPowerUsedBeforeThreeIsOfThePreviousDay(): void
	{
		self::freezeAtNoonOf('2026-11-09');
		$context = new ContextPreparator(['user' => ['name' => 'player', 'level' => 100, 'time_zone' => 'Europe/Prague', 'daily_reset_day' => '2026-11-09']]);
		$this->signInAt($context, '2026-11-10 01:00:00');

		$this->post('/hero/sprint');
		$this->assertResponseCode(200);
		$this->assertSame(['damage' => 0, 'powers' => 1, 'day' => '2026-11-09'], $this->signInAt($context, '2026-11-10 01:59:00'), 'Used on the 9th');

		$this->assertSame(['damage' => 0, 'powers' => 0, 'day' => '2026-11-10'], $this->signInAt($context, '2026-11-10 02:00:00'), 'Back at 03:00 in Prague');
	}

	//The reset is done once a day: the hearts lost after it stay lost until the next day
	public function testTheResetIsDoneOnceADay(): void
	{
		$context = $this->playerOf('Europe/Prague');
		$this->signInAt($context, '2026-11-10 02:01:00');
		Util::execute('UPDATE user SET damage = 3, used_sprint = 1 WHERE id = ?', [$context->user['id']]);

		$this->assertSame(['damage' => 3, 'powers' => 1, 'day' => '2026-11-10'], $this->signInAt($context, '2026-11-10 21:00:00'));
		$this->assertSame(['damage' => 3, 'powers' => 1, 'day' => '2026-11-10'], $this->signInAt($context, '2026-11-11 01:59:00'), '02:59 of the 11th in Prague');
		$this->assertSame(['damage' => 0, 'powers' => 0, 'day' => '2026-11-11'], $this->signInAt($context, '2026-11-11 02:00:00'));
	}

	//The reset runs on every request (Auth::init()), so it must cost nothing on the day's other requests: the
	//day of the row is compared in PHP and the database gets no write
	public function testARequestOnTheSameDayWritesNothing(): void
	{
		$context = $this->playerOf('Europe/Prague');
		$this->signInAt($context, '2026-11-10 02:01:00');
		$writes = fn() => array_sum(array_map(fn(array $row) => (int) $row['Value'],
			Util::query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_update', 'Com_insert', 'Com_delete')")));

		$before = $writes();
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->assertSame($before, $writes(), 'No write on a later request of the same day');
	}

	//On the day of the deploy, the migration gives every player the day they are in (AddDailyResetDay), so the
	//hearts the cron gave at midnight UTC are the ones of that day: none come back again before the player's next day
	public function testNoSecondRefillOnTheDayOfTheDeploy(): void
	{
		$deploy = new DateTimeImmutable('2026-11-10 10:00:00', new DateTimeZone('UTC'));
		$context = $this->playerOf('Europe/Prague', DailyStreak::today('Europe/Prague', $deploy));
		Util::execute('UPDATE user SET damage = 4 WHERE id = ?', [$context->user['id']]);

		$this->assertSame(4, $this->signInAt($context, '2026-11-10 22:59:00')['damage'], '23:59 in Prague');
		$this->assertSame(4, $this->signInAt($context, '2026-11-11 01:59:00')['damage'], '02:59 in Prague');
		$this->assertSame(0, $this->signInAt($context, '2026-11-11 02:00:00')['damage'], '03:00 in Prague, the next day');
	}

	//Players in five zones, none signed in, who lost 7 hearts, used a power and drank a potion on the 9th
	/** @return array<string, int> The ids of the players by their zone, 'UTC' for the one whose zone isn't known */
	private function playersInZones(): array
	{
		self::freezeAtNoonOf('2026-11-09');
		$zones = ['Europe/Prague', 'Europe/London', 'America/New_York', 'Asia/Tokyo', null];
		$context = new ContextPreparator(['other-users' => array_map(fn(?string $zone) => [
			'name' => 'player in ' . ($zone ?? 'UTC'), 'time_zone' => $zone, 'damage' => 7, 'used_sprint' => 1, 'daily_reset_day' => '2026-11-09'], $zones)]);
		Auth::logout();
		$ids = [];
		foreach ($zones as $index => $zone)
		{
			$id = (int) $context->otherUsers[$index]['id'];
			AchievementConditionTable::put($id, AchievementConditionTable::CATEGORY_POTION, 1);
			$ids[$zone ?? 'UTC'] = $id;
		}
		return $ids;
	}

	//The hearts, the sprint and the potions of the day of each player, as the database has them
	/**
	 * @param array<string, int> $ids
	 * @return array<string, array{int, int, float}>
	 */
	private static function stored(array $ids): array
	{
		return array_map(fn(int $id) => [
			(int) Util::query('SELECT damage FROM user WHERE id = ?', [$id])[0]['damage'],
			(int) Util::query('SELECT used_sprint FROM user WHERE id = ?', [$id])[0]['used_sprint'],
			AchievementConditionTable::value($id, AchievementConditionTable::CATEGORY_POTION)], $ids);
	}

	//At 02:00 UTC it is 03:00 in Prague and 11:00 in Tokyo, where the 10th began, but 02:00 in London and in UTC
	//and the evening of the 9th in New York: the run resets the first two, in the database, without their requests
	public function testTheHourlyRunResetsThePlayersWhoseDayTurned(): void
	{
		$ids = $this->playersInZones();
		Clock::freeze(new DateTimeImmutable('2026-11-10 02:00:00', new DateTimeZone('UTC')));

		$this->assertSame(2, DailyReset::resetDuePlayers());

		$this->assertSame([
			'Europe/Prague' => [0, 0, 0.0],
			'Europe/London' => [7, 1, 1.0],
			'America/New_York' => [7, 1, 1.0],
			'Asia/Tokyo' => [0, 0, 0.0],
			'UTC' => [7, 1, 1.0],
		], self::stored($ids));
	}

	//A second run in the same hour finds nobody behind, so it changes nothing, also not what was lost since
	public function testASecondRunIsANoOp(): void
	{
		$ids = $this->playersInZones();
		Clock::freeze(new DateTimeImmutable('2026-11-10 02:00:00', new DateTimeZone('UTC')));
		DailyReset::resetDuePlayers();
		Util::execute('UPDATE user SET damage = 3 WHERE id = ?', [$ids['Europe/Prague']]);
		$before = self::stored($ids);

		Clock::freeze(new DateTimeImmutable('2026-11-10 02:30:00', new DateTimeZone('UTC')));
		$this->assertSame(0, DailyReset::resetDuePlayers());
		$this->assertSame($before, self::stored($ids));
	}

	//bin/cake cron_hourly runs it, every hour
	public function testTheHourlyCommandResetsThePlayersWhoseDayTurned(): void
	{
		$ids = $this->playersInZones();
		Clock::freeze(new DateTimeImmutable('2026-11-10 08:30:00', new DateTimeZone('UTC')));

		$this->exec('cron_hourly');

		$this->assertExitSuccess();
		// silent when it works, as the scheduler mails any output
		$this->assertOutputEmpty();
		$this->assertErrorEmpty();
		// 03:30 in New York, 08:30 in London and in UTC: everyone is on the 10th
		$this->assertSame([0, 0, 0, 0, 0], array_values(array_map(fn(array $player) => $player[0], self::stored($ids))));
	}
}
