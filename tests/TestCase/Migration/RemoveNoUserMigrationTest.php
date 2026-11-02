<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261101221112_remove_no_user.php';

//The noUser account (user 33) goes with what it left behind, and anything else that still points at
//it stops the migration without changing anything
class RemoveNoUserMigrationTest extends AppTestCase
{
	public function testTheAccountGoesWithWhatItLeftBehind(): void
	{
		[$context, $rows] = self::prepare();

		self::migration()->up();

		$this->assertSame([], Util::query('SELECT id FROM user WHERE id = 33'));
		$this->assertNull(Util::query('SELECT user_id FROM sgf WHERE id = ?', [$rows['sgf']])[0]['user_id'], 'the version is automatic now');
		$this->assertSame([], Util::query('SELECT id FROM tsumego_status WHERE user_id = 33'));
		$this->assertSame([], Util::query('SELECT id FROM tsumego_comment WHERE user_id = 33'));
		$this->assertSame([], Util::query('SELECT id FROM tsumego_issue WHERE id = ?', [$rows['issue']]));
		$this->assertSame([['tsumego_issue_id' => null, 'message' => 'a reply of another player']],
			Util::query('SELECT tsumego_issue_id, message FROM tsumego_comment WHERE id = ?', [$rows['otherComment']]), 'another player\'s comment stays, as a plain comment');
		$this->assertSame([['user_id' => null, 'emerald_solves' => 7]],
			Util::query('SELECT user_id, emerald_solves FROM day_record WHERE id = ?', [$rows['dayRecord']]), 'the day keeps its statistics');
		$this->assertCount(1, Util::query('SELECT id FROM tsumego_status WHERE user_id = ?', [$context->user['id']]), 'the statuses of others stay');
	}

	public function testAnotherReferenceStopsItAndChangesNothing(): void
	{
		[, $rows] = self::prepare();
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, seconds, solved, misplays, created) VALUES (33, ?, 0, 1, 1, 0, NOW())', [$rows['tsumego']]);

		try
		{
			self::migration()->up();
			$this->fail('The migration should have stopped');
		}
		catch (RuntimeException $exception)
		{
			$this->assertStringContainsString('tsumego_attempt.user_id: 1', $exception->getMessage());
		}

		$this->assertCount(1, Util::query('SELECT id FROM user WHERE id = 33'));
		$this->assertSame(33, Util::query('SELECT user_id FROM sgf WHERE id = ?', [$rows['sgf']])[0]['user_id']);
		$this->assertCount(1, Util::query('SELECT id FROM tsumego_status WHERE user_id = 33'), 'the cleanup was taken back');
		$this->assertCount(1, Util::query('SELECT id FROM tsumego_issue WHERE id = ?', [$rows['issue']]));
		$this->assertSame(33, Util::query('SELECT user_id FROM day_record WHERE id = ?', [$rows['dayRecord']])[0]['user_id']);
	}

	//The account with a version, a status, an issue with its own comment and a reply of another player,
	//and a day it was the user of the day; the context's user has a status of its own
	private static function prepare(): array
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$tsumegoID = (int) $context->tsumegos[0]['id'];
		Util::execute("INSERT INTO user (id, name, display_name, email, password_hash) VALUES (33, 'noUser', 'noUser', 'nouser@example.com', '')");
		Util::execute("INSERT INTO sgf (sgf, user_id, tsumego_id, sgf_status_id, preview) VALUES ('(;)', 33, ?, 2, '')", [$tsumegoID]);
		$rows = ['tsumego' => $tsumegoID, 'sgf' => (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id']];
		Util::execute("INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (33, ?, 'S')", [$tsumegoID]);
		Util::execute('INSERT INTO tsumego_issue (tsumego_issue_status_id, tsumego_id, user_id) VALUES (1, ?, 33)', [$tsumegoID]);
		$rows['issue'] = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
		Util::execute("INSERT INTO tsumego_comment (tsumego_id, tsumego_issue_id, message, user_id) VALUES (?, ?, 'the issue', 33)", [$tsumegoID, $rows['issue']]);
		Util::execute("INSERT INTO tsumego_comment (tsumego_id, tsumego_issue_id, message, user_id) VALUES (?, ?, 'a reply of another player', ?)", [$tsumegoID, $rows['issue'], $context->user['id']]);
		$rows['otherComment'] = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
		Util::execute("INSERT INTO day_record (user_id, date, gems, emerald_solves, sapphire_solves, ruby_solves) VALUES (33, '2020-01-01', '0-0-0', 7, 0, 0)");
		$rows['dayRecord'] = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
		return [$context, $rows];
	}

	private static function migration(): RemoveNoUser
	{
		$config = require ROOT . '/phinx.php';
		$migration = new RemoveNoUser('test', 20261101221112);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
