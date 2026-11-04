<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261104200456_add_user_daily_streak.php';

//The migration of the daily streak makes the same columns on a database without the streak, like
//production, and on the test server, which ran the two migrations it replaces; there it keeps the
//values and drops the streak mode setting and the golden streak.
class AddUserDailyStreakMigrationTest extends AppTestCase
{
	public function testTheOldTestServerSchemaEndsLikeAFreshOne(): void
	{
		$context = new ContextPreparator();
		$migration = self::migration();
		try
		{
			$fresh = self::streakColumns();
			$migration->down();
			$this->assertSame([], self::streakColumns());
			$migration->up();
			$this->assertSame($fresh, self::streakColumns());

			$migration->down();
			self::makeTheOldTestServerSchema();
			Util::execute("UPDATE user SET streak_length = 4, streak_best = 9, streak_freezes = 1, streak_last_day = '2026-11-03', time_zone = 'Europe/Prague',
				golden_streak_length = 2, streak_day = '2026-11-03', streak_day_xp = 50 WHERE id = ?", [$context->user['id']]);
			$migration->up();

			$this->assertSame($fresh, self::streakColumns());
			$this->assertSame([], Util::query("SHOW TABLES LIKE 'streak_mode_setting'"));
			$this->assertSame([], Util::query("SHOW COLUMNS FROM user WHERE Field IN ('streak_mode_setting_id', 'golden_streak_length', 'golden_streak_best')"));
			$this->assertSame([], Util::query('SELECT version FROM phinxlog WHERE version IN (20261104005646, 20261104183235)'));
			$user = $context->reloadUser();
			$this->assertSame([4, 9, 1, 'Europe/Prague', 50], [(int) $user['streak_length'], (int) $user['streak_best'], (int) $user['streak_freezes'], $user['time_zone'], (int) $user['streak_day_xp']]);
		}
		finally
		{
			// the schema the other tests run on comes back whatever failed
			if (!Util::query("SHOW COLUMNS FROM user LIKE 'streak_length'"))
				$migration->up();
		}
	}

	//What the test server had after 20261104005646 and 20261104183235
	private static function makeTheOldTestServerSchema(): void
	{
		Util::execute("ALTER TABLE user
			ADD COLUMN streak_length INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row with a solve that gave XP, as of streak_last_day',
			ADD COLUMN streak_best INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest streak_length the player reached',
			ADD COLUMN streak_freezes TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Freezes held, each covers one missed day',
			ADD COLUMN streak_last_day DATE NULL DEFAULT NULL COMMENT 'The last local day that counted for the streak, or was covered by a freeze',
			ADD COLUMN time_zone VARCHAR(64) NULL DEFAULT NULL COMMENT 'IANA time zone from the browser; NULL is UTC'");
		Util::execute("CREATE TABLE streak_mode_setting (id int(10) unsigned NOT NULL, name varchar(30) NOT NULL, PRIMARY KEY (id), UNIQUE KEY name (name))
			ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		Util::execute("INSERT INTO streak_mode_setting (id, name) VALUES (1, 'normal'), (2, 'golden')");
		Util::execute("ALTER TABLE user
			ADD COLUMN streak_mode_setting_id int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'The mode of the daily streak the player plays: normal or golden',
			ADD COLUMN golden_streak_length INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days in a row counted in the golden mode, as of streak_last_day',
			ADD COLUMN golden_streak_best INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The longest golden_streak_length the player reached',
			ADD COLUMN streak_day DATE NULL DEFAULT NULL COMMENT 'The local day the streak_day_ counters are of',
			ADD COLUMN streak_day_xp INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The XP the solves of streak_day gave',
			ADD COLUMN streak_day_reviews INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The mistake training reviews solved on streak_day',
			ADD COLUMN streak_day_reviews_required TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'The reviews the golden mode asked of streak_day, NULL until worked out',
			ADD KEY user_streak_mode_setting_id (streak_mode_setting_id),
			ADD CONSTRAINT user_streak_mode_setting_id FOREIGN KEY (streak_mode_setting_id) REFERENCES streak_mode_setting (id) ON UPDATE CASCADE");
		Util::execute('INSERT IGNORE INTO phinxlog (version, migration_name, breakpoint) VALUES (20261104005646, ?, 0), (20261104183235, ?, 0)', ['AddUserDailyStreak', 'AddUserGoldenStreak']);
	}

	//The columns of the streak, in their order, with their whole definition
	private static function streakColumns(): array
	{
		return Util::query("SHOW FULL COLUMNS FROM user WHERE Field LIKE 'streak%' OR Field LIKE '%golden%' OR Field = 'time_zone'");
	}

	private static function migration(): AddUserDailyStreak
	{
		$config = require ROOT . '/phinx.php';
		$adapter = AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']);
		$migration = new AddUserDailyStreak('test', 20261104200456);
		$migration->setAdapter($adapter);
		return $migration;
	}
}
