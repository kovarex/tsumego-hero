<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261111124138_trim_correct_comment_breaks.php';

//The line break after the + of a correct comment goes, the rest of the comment and of the problem stays, and so does
//the time the version was saved
class TrimCorrectCommentBreaksMigrationTest extends AppTestCase
{
	public function testTheBreakAfterThePlusGoes(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['set_order' => 1, 'sgf' => "(;GM[1]FF[4]SZ[19]AB[dd]AW[bb];B[aa]C[+\n\nThe marked stones are useless.\nSacrifice.])"],
			['set_order' => 2, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb];B[aa]C[+Correct, but what about this answer?])']]]);
		$ids = array_map(fn($tsumego) => (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ? ORDER BY id DESC LIMIT 1', [$tsumego['id']])[0]['id'], $context->tsumegos);
		Util::execute("UPDATE sgf SET created = '2020-01-02 03:04:05' WHERE id IN (?, ?)", $ids);

		self::migration()->up();
		self::migration()->up();

		$rows = array_map(fn($id) => Util::query('SELECT sgf, created FROM sgf WHERE id = ?', [$id])[0], $ids);
		$this->assertSame("(;GM[1]FF[4]SZ[19]AB[dd]AW[bb];B[aa]C[+The marked stones are useless.\nSacrifice.])", $rows[0]['sgf']);
		$this->assertSame('(;GM[1]FF[4]SZ[19]AB[dd]AW[bb];B[aa]C[+Correct, but what about this answer?])', $rows[1]['sgf']);
		$this->assertSame(['2020-01-02 03:04:05', '2020-01-02 03:04:05'], array_map(fn($row) => (string) $row['created'], $rows));
	}

	private static function migration(): TrimCorrectCommentBreaks
	{
		$config = require ROOT . '/phinx.php';
		$migration = new TrimCorrectCommentBreaks('test', 20261111124138);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
