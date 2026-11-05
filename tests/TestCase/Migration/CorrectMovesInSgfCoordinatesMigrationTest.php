<?php

use App\Utility\SimilarSearchLogic;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261105150017_correct_moves_in_sgf_coordinates.php';

//The migration puts sgf.correct_moves, written in the orientation the board was shown in, into the coordinates of
//the SGF: through the one symmetry of the board that makes every move a first move of the SGF.
class CorrectMovesInSgfCoordinatesMigrationTest extends AppTestCase
{
	//The two problems of the report: 979 (page 848, the correct first move aq in the bottom-left) and 27583
	//(page 11420, sq in the bottom-right), the same shape mirrored, four stones apart. Both had correct_moves ac.
	private const string PROBLEM_979 = "(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]\nRU[Japanese]SZ[19]KM[0.00]\nAW[cm][bo][co][cp][dq][eq][gq]AB[bp][cq][cr]\n(;B[dr]\n;W[bq]\n;B[br]\n;W[ap])\n(;B[br]\n;W[aq]\n;B[ap]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[bq]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[aq]\n;W[bs]\n(;B[br]\n;W[ao]\n(;B[as]\n;W[ar]\n;B[cs]\n;W[ap]\n(;B[bq]\n;W[dr]\n;B[as]C[+])\n(;B[as]\n;W[bq]))\n(;B[ap]\n;W[ar])\n(;B[cs]\n;W[ap]))\n(;B[dr]\n;W[br])))\n";
	private const string PROBLEM_27583 = "(;FF[4]GM[1]CA[UTF-8]AP[besogo:0.0.2-alpha]SZ[19]ST[2]\nRU[Japanese]KM[6.50]DT[2021-09-08]\nAB[qq][qr][rp]AW[nq][oq][pp][pq][qo][qp][ro]\n(;B[sq]\n(;W[rs]\n(;B[rr]\n;W[so]\n(;B[ss]\n;W[sp]\n;B[rq]\n;W[sr]\n;B[qs]\nC[+])\n(;B[sp]\n;W[sr])\n(;B[qs]\n;W[sp]))\n(;B[pr]\n;W[or]\n;B[rr]\n;W[so]\n(;B[sp]\n;W[sr])\n(;B[ss]\n;W[sp])))\n(;W[so]\n(;B[rs]\n;W[pr]\n;B[sr]\nC[+])\n(;B[pr]\n;W[rs]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[or]\n;W[nr]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[os]\n;W[rr]\n;B[rq]\n;W[qs])))\n(;B[rr]\n;W[sp])))\n(;B[sp]\n;W[sr])\n(;B[rr]\n;W[sp]\n;B[sq]\n;W[rq])\n(;B[sr]\n;W[sp])\n(;B[pr]\n;W[rq]))";

	//Stones in the bottom-right corner, the first moves sq (correct), rs and pp
	private const string CORNER = '(;GM[1]FF[4]SZ[19]AB[rr][sr][qr]AW[pr][pq][qq][rq](;B[sq]C[+])(;B[rs])(;B[pp]))';

	public function testMovesThatAreFirstMovesAlreadyStayAsTheyAre(): void
	{
		$this->assertSame([CorrectMovesInSgfCoordinates::UNCHANGED, 'sq'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'sq'));
		$this->assertSame([CorrectMovesInSgfCoordinates::UNCHANGED, 'sqrs'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'sqrs'));
		//a single line of moves: the root has one child
		$this->assertSame([CorrectMovesInSgfCoordinates::UNCHANGED, 'aa'], CorrectMovesInSgfCoordinates::correctedMoves('(;SZ[19]AB[ba];B[aa];W[ab];B[ba]C[+])', 'aa'));
	}

	//The moves as each way of showing the board turned them come back to the SGF
	public function testTurnedMovesAreTurnedBack(): void
	{
		$turnedMoves = [
			'top-left (both flips)' => 'ac',
			'top-right (vertical flip)' => 'sc',
			'bottom-left (horizontal flip)' => 'aq',
			'transposed' => 'qs',
			'transposed and turned to the top-left' => 'ca',
			'rotated' => 'cs'];
		foreach ($turnedMoves as $orientation => $turned)
			$this->assertSame([CorrectMovesInSgfCoordinates::FIXED, 'sq'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, $turned), $orientation);
		//two moves turned together keep their order
		$this->assertSame([CorrectMovesInSgfCoordinates::FIXED, 'sqrs'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'acba'));
	}

	//On a board that is not square only the flips are symmetries
	public function testARectangularBoardOnlyFlips(): void
	{
		$sgf = '(;SZ[19:9]AB[rh][sh](;B[si]C[+])(;B[bi]))';
		$this->assertSame([CorrectMovesInSgfCoordinates::FIXED, 'si'], CorrectMovesInSgfCoordinates::correctedMoves($sgf, 'sa'));
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'is'], CorrectMovesInSgfCoordinates::correctedMoves($sgf, 'is'));
	}

	//Two symmetries that make different first moves of it: nothing is guessed
	public function testMovesThatFitSeveralWaysStayAsTheyAre(): void
	{
		//first moves in two corners, ac turns into either of them
		$sgf = '(;SZ[19](;B[aq])(;B[sq]))';
		$this->assertSame([CorrectMovesInSgfCoordinates::AMBIGUOUS, 'ac'], CorrectMovesInSgfCoordinates::correctedMoves($sgf, 'ac'));
	}

	public function testMovesThatFitNoWayStayAsTheyAre(): void
	{
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'jj'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'jj'));
		//one of the two fits, the other one no way along with it
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'acjk'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'acjk'));
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'abc'], CorrectMovesInSgfCoordinates::correctedMoves(self::CORNER, 'abc'));
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'ac'], CorrectMovesInSgfCoordinates::correctedMoves('not an sgf', 'ac'));
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'ac'], CorrectMovesInSgfCoordinates::correctedMoves('(;SZ[19]AB[aa])', 'ac'));
	}

	//Only the first moves count, not those deeper in the tree, and the values of properties are read as values
	public function testOnlyTheMovesRightAfterTheRootAreFirstMoves(): void
	{
		$sgf = '(;SZ[19]C[a comment with ;B[ss\\] and (;W[aa\\]) in it](;B[aq];W[sq])(;W[bq](;B[cq])(;B[dq])))';
		$this->assertSame([19, 19], CorrectMovesInSgfCoordinates::rootChildren($sgf)[0]);
		$this->assertSame([[1, 17], [2, 17]], CorrectMovesInSgfCoordinates::rootChildren($sgf)[1]);
		$this->assertSame([CorrectMovesInSgfCoordinates::UNMAPPABLE, 'cq'], CorrectMovesInSgfCoordinates::correctedMoves($sgf, 'cq'));
	}

	//The two problems of the report (979 and 27583), whose correct moves aq and sq were stored as ac
	public function testThePairOfTheReportIsFixed(): void
	{
		$this->assertSame([CorrectMovesInSgfCoordinates::FIXED, 'aq'], CorrectMovesInSgfCoordinates::correctedMoves(self::PROBLEM_979, 'ac'));
		$this->assertSame([CorrectMovesInSgfCoordinates::FIXED, 'sq'], CorrectMovesInSgfCoordinates::correctedMoves(self::PROBLEM_27583, 'ac'));
	}

	//The migration writes the fixed rows, keeps the others, and down() puts the fixed ones back
	public function testTheMigrationFixesTheRows(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sgf' => ['data' => self::CORNER, 'correct_moves' => 'sq', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::CORNER, 'correct_moves' => 'ac', 'first_move_color' => 'B']],
			['sgf' => ['data' => '(;SZ[19](;B[aq])(;B[sq]))', 'correct_moves' => 'ac', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::CORNER, 'correct_moves' => 'jj', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::CORNER, 'correct_moves' => '', 'first_move_color' => 'B']]]]);
		$ids = array_map(fn($tsumego) => (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ?', [$tsumego['id']])[0]['id'], $context->tsumegos);
		$correctMoves = fn() => array_map(fn($id) => Util::query('SELECT correct_moves FROM sgf WHERE id = ?', [$id])[0]['correct_moves'], $ids);
		$migration = self::migration();
		$migration->down();
		try
		{
			$migration->up();
			$this->assertSame(['sq', 'sq', 'ac', 'jj', ''], $correctMoves());
			$this->assertSame([['sgf_id' => $ids[1], 'correct_moves' => 'ac']], Util::query('SELECT sgf_id, correct_moves FROM sgf_correct_moves_before_sgf_coordinates'));

			$migration->down();
			$this->assertSame(['sq', 'ac', 'ac', 'jj', ''], $correctMoves());
		}
		finally
		{
			// the schema the other tests run on comes back whatever failed
			if (!Util::query("SHOW TABLES LIKE 'sgf_correct_moves_before_sgf_coordinates'"))
				$migration->up();
		}
	}

	//With the moves turned as they were stored, the similar search couldn't find the pair, with the moves the
	//migration gives them it finds each from the other
	public function testThePairOfTheReportFindsEachOtherOnceFixed(): void
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
		$this->assertSame([], $found(1));

		$migration = self::migration();
		$migration->down();
		try
		{
			$migration->up();
			$this->assertSame(['aq', 'sq'], array_column(Util::query('SELECT correct_moves FROM sgf ORDER BY tsumego_id'), 'correct_moves'));
			$this->assertSame([$context->tsumegos[1]['id']], $found(0));
			$this->assertSame([$context->tsumegos[0]['id']], $found(1));
		}
		finally
		{
			if (!Util::query("SHOW TABLES LIKE 'sgf_correct_moves_before_sgf_coordinates'"))
				$migration->up();
		}
	}

	private static function migration(): CorrectMovesInSgfCoordinates
	{
		$config = require ROOT . '/phinx.php';
		$adapter = AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']);
		$migration = new CorrectMovesInSgfCoordinates('test', 20261105150017);
		$migration->setAdapter($adapter);
		return $migration;
	}
}
