<?php

use App\Utility\SimilarSearchLogic;
use App\Utility\Util;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

//bin/cake recompute_correct_moves works the first move color and the correct moves out again for every version,
//changing nothing without --write
class RecomputeCorrectMovesCommandTest extends AppTestCase
{
	use ConsoleIntegrationTestTrait;

	//Small problems in the bottom corners whose correct moves were stored turned to the top-left, or not at all
	private const string BOTTOM_LEFT = '(;SZ[19]AB[br][bs](;B[as]C[+])(;B[ar]))';
	private const string BOTTOM_RIGHT = '(;SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr]))';

	//Problems 979 (page 848) and 27583 (page 11420): the same shape mirrored, four stones apart, with their correct
	//first moves aq and sq where their stones are; the setup page stored both as ac, turned to the top-left
	private const string PROBLEM_979 = "(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]\nRU[Japanese]SZ[19]KM[0.00]\nAW[cm][bo][co][cp][dq][eq][gq]AB[bp][cq][cr]\n(;B[dr]\n;W[bq]\n;B[br]\n;W[ap])\n(;B[br]\n;W[aq]\n;B[ap]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[bq]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[aq]\n;W[bs]\n(;B[br]\n;W[ao]\n(;B[as]\n;W[ar]\n;B[cs]\n;W[ap]\n(;B[bq]\n;W[dr]\n;B[as]C[+])\n(;B[as]\n;W[bq]))\n(;B[ap]\n;W[ar])\n(;B[cs]\n;W[ap]))\n(;B[dr]\n;W[br])))\n";
	private const string PROBLEM_27583 = "(;FF[4]GM[1]CA[UTF-8]AP[besogo:0.0.2-alpha]SZ[19]ST[2]\nRU[Japanese]KM[6.50]DT[2021-09-08]\nAB[qq][qr][rp]AW[nq][oq][pp][pq][qo][qp][ro]\n(;B[sq]\n(;W[rs]\n(;B[rr]\n;W[so]\n(;B[ss]\n;W[sp]\n;B[rq]\n;W[sr]\n;B[qs]\nC[+])\n(;B[sp]\n;W[sr])\n(;B[qs]\n;W[sp]))\n(;B[pr]\n;W[or]\n;B[rr]\n;W[so]\n(;B[sp]\n;W[sr])\n(;B[ss]\n;W[sp])))\n(;W[so]\n(;B[rs]\n;W[pr]\n;B[sr]\nC[+])\n(;B[pr]\n;W[rs]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[or]\n;W[nr]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[os]\n;W[rr]\n;B[rq]\n;W[qs])))\n(;B[rr]\n;W[sp])))\n(;B[sp]\n;W[sr])\n(;B[rr]\n;W[sp]\n;B[sq]\n;W[rq])\n(;B[sr]\n;W[sp])\n(;B[pr]\n;W[rq]))";

	private function prepare(): array
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sgf' => ['data' => self::BOTTOM_LEFT, 'correct_moves' => 'aa', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::BOTTOM_RIGHT, 'correct_moves' => 'ss', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::BOTTOM_RIGHT, 'correct_moves' => null, 'first_move_color' => null]]]]);
		return array_map(fn($tsumego) => (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ?', [$tsumego['id']])[0]['id'], $context->tsumegos);
	}

	private static function stored(array $ids): array
	{
		return array_map(fn($id) => array_values(Util::query('SELECT first_move_color, correct_moves FROM sgf WHERE id = ?', [$id])[0]), $ids);
	}

	public function testADryRunReportsTheDifferencesAndChangesNothing(): void
	{
		$ids = $this->prepare();
		$this->exec('recompute_correct_moves');

		$this->assertExitSuccess();
		$this->assertOutputContains('Dry run: nothing is written');
		$this->assertOutputContains("sgf {$ids[0]}: first_move_color 'B' -> B, correct_moves 'aa' -> 'as'");
		$this->assertOutputContains("sgf {$ids[2]}: first_move_color NULL -> B, correct_moves NULL -> 'ss'");
		$this->assertOutputNotContains("sgf {$ids[1]}:");
		$this->assertOutputContains('3 rows, 2 would change, 0 unreadable');
		$this->assertSame([['B', 'aa'], ['B', 'ss'], [null, null]], self::stored($ids));
	}

	public function testWriteStoresTheNewValues(): void
	{
		$ids = $this->prepare();
		$this->exec('recompute_correct_moves --write --batch 2');

		$this->assertExitSuccess();
		$this->assertOutputContains('3 rows, 2 updated, 0 unreadable');
		$this->assertSame([['B', 'as'], ['B', 'ss'], ['B', 'ss']], self::stored($ids));

		$this->exec('recompute_correct_moves');
		$this->assertOutputContains('3 rows, 0 would change');
	}

	public function testTheListIsCutAndUnreadableRowsAreKept(): void
	{
		$ids = $this->prepare();
		Util::execute("UPDATE sgf SET sgf = 'not an sgf' WHERE id = ?", [$ids[1]]);
		$this->exec('recompute_correct_moves --write --show 1');

		$this->assertOutputContains("sgf {$ids[0]}:");
		$this->assertOutputNotContains("sgf {$ids[2]}:");
		$this->assertOutputContains("3 rows, 2 updated, 1 unreadable (kept as they are): {$ids[1]}");
		$this->assertSame(['B', 'ss'], self::stored($ids)[1]);
	}

	//With the correct moves recomputed in the coordinates of the SGF, the similar search finds each from the other
	public function testThePairOfTheReportFindsEachOtherOnceRecomputed(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['set_order' => 1, 'sgf' => ['data' => self::PROBLEM_979, 'correct_moves' => 'ac', 'first_move_color' => 'B']],
			['set_order' => 2, 'sgf' => ['data' => self::PROBLEM_27583, 'correct_moves' => 'ac', 'first_move_color' => 'B']]]]);
		$found = function (int $index) use ($context): array {
			$search = new SimilarSearchLogic($this->fetchTable('SetConnection')->get($context->setConnections[$index]['id']));
			$search->execute();
			return array_map(fn($item) => $item->tsumegoButton->tsumegoID, $search->result->items);
		};
		$this->assertSame([], $found(0));

		$this->exec('recompute_correct_moves --write');
		$this->assertSame(['aq', 'sq'], array_column(Util::query('SELECT correct_moves FROM sgf ORDER BY tsumego_id'), 'correct_moves'));
		$this->assertSame([$context->tsumegos[1]['id']], $found(0));
		$this->assertSame([$context->tsumegos[0]['id']], $found(1));
	}
}
