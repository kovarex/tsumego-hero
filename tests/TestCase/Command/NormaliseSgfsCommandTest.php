<?php

use App\Model\Table\SgfTable;
use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\SimilarSearchLogic;
use App\Utility\Util;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

//bin/cake normalise_sgfs rewrites the stored SGFs in place, turned to the top-left with the correct variations first,
//and moves what goes with their coordinates; nothing without --write
class NormaliseSgfsCommandTest extends AppTestCase
{
	use ConsoleIntegrationTestTrait;

	//The comment and the problem of SgfControllerUploadTest in the bottom-right corner, where T1 is the first move
	private const string BOTTOM_RIGHT = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[qq];B[ss];W[sr];B[rs]C[+])';
	private const array BOTTOM_RIGHT_COMMENT = ['message' => 'black T1 then w T2', 'position' => '19/18/19/19/18/19/2/1/sgf|19/19+19/18'];
	private const string TOP_RIGHT = '(;SZ[19]AB[rb][sb](;B[sa]C[+])(;B[ra];W[qa]))';
	private const string BOTTOM_LEFT = '(;SZ[19]AB[br][bs](;B[as]C[+])(;B[ar];W[aq]))';
	//In the top-left already, but the correct variation second
	private const string CORRECT_SECOND = '(;SZ[19]AB[bb](;B[dd];W[aa])(;B[aa]C[+]))';
	private const string DONE = '(;SZ[19]AB[bb](;B[aa]C[+])(;B[dd];W[aa]))';

	//Problems 979 (page 848) and 27583 (page 11420), the same shape in the bottom-left and the bottom-right corner,
	//imported as written, with the correct moves their setup stored as ac
	private const string PROBLEM_979 = "(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]\nRU[Japanese]SZ[19]KM[0.00]\nAW[cm][bo][co][cp][dq][eq][gq]AB[bp][cq][cr]\n(;B[dr]\n;W[bq]\n;B[br]\n;W[ap])\n(;B[br]\n;W[aq]\n;B[ap]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[bq]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[aq]\n;W[bs]\n(;B[br]\n;W[ao]\n(;B[as]\n;W[ar]\n;B[cs]\n;W[ap]\n(;B[bq]\n;W[dr]\n;B[as]C[+])\n(;B[as]\n;W[bq]))\n(;B[ap]\n;W[ar])\n(;B[cs]\n;W[ap]))\n(;B[dr]\n;W[br])))\n";
	private const string PROBLEM_27583 = "(;FF[4]GM[1]CA[UTF-8]AP[besogo:0.0.2-alpha]SZ[19]ST[2]\nRU[Japanese]KM[6.50]DT[2021-09-08]\nAB[qq][qr][rp]AW[nq][oq][pp][pq][qo][qp][ro]\n(;B[sq]\n(;W[rs]\n(;B[rr]\n;W[so]\n(;B[ss]\n;W[sp]\n;B[rq]\n;W[sr]\n;B[qs]\nC[+])\n(;B[sp]\n;W[sr])\n(;B[qs]\n;W[sp]))\n(;B[pr]\n;W[or]\n;B[rr]\n;W[so]\n(;B[sp]\n;W[sr])\n(;B[ss]\n;W[sp])))\n(;W[so]\n(;B[rs]\n;W[pr]\n;B[sr]\nC[+])\n(;B[pr]\n;W[rs]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[or]\n;W[nr]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[os]\n;W[rr]\n;B[rq]\n;W[qs])))\n(;B[rr]\n;W[sp])))\n(;B[sp]\n;W[sr])\n(;B[rr]\n;W[sp]\n;B[sq]\n;W[rq])\n(;B[sr]\n;W[sp])\n(;B[pr]\n;W[rq]))";

	private function sgfRow(int $tsumegoID): array
	{
		return Util::query('SELECT * FROM sgf WHERE tsumego_id = ? ORDER BY id DESC LIMIT 1', [$tsumegoID])[0];
	}

	private function prepare(): ContextPreparator
	{
		return new ContextPreparator(['tsumegos' => [
			['sgf' => ['data' => self::BOTTOM_RIGHT, 'correct_moves' => 'ss', 'first_move_color' => 'B'], 'comments' => [self::BOTTOM_RIGHT_COMMENT]],
			['sgf' => ['data' => self::TOP_RIGHT, 'correct_moves' => 'aa', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::BOTTOM_LEFT, 'correct_moves' => null, 'first_move_color' => null]],
			['sgf' => ['data' => self::CORRECT_SECOND, 'correct_moves' => 'aa', 'first_move_color' => 'B']],
			['sgf' => ['data' => self::DONE, 'correct_moves' => 'aa', 'first_move_color' => 'B']]]]);
	}

	public function testADryRunReportsAndWritesNothing(): void
	{
		$context = $this->prepare();
		$before = Util::query('SELECT * FROM sgf ORDER BY id');
		$this->exec('normalise_sgfs');

		$this->assertExitSuccess();
		$this->assertOutputContains('Dry run: nothing is written');
		$this->assertOutputContains('5 problems, 5 rows, 3 turned, 1 reordered only, 0 values only, 1 comments moved');
		$this->assertOutputContains('Problems turned flipX+flipY (current version): ' . $context->tsumegos[0]['id']);
		$this->assertOutputContains('0 whose properties would change unexpectedly (kept as they are)');
		$this->assertOutputContains('Property counts of the rewritten rows, before -> after: ');
		$this->assertOutputContains('CA 1 -> 0 in 1 rows');
		$this->assertSame($before, Util::query('SELECT * FROM sgf ORDER BY id'));
		$this->assertSame(self::BOTTOM_RIGHT_COMMENT['message'], Util::query('SELECT message FROM tsumego_comment')[0]['message']);
	}

	//Each corner ends in the top-left, the comments with it; only the SGF and what comes from it change
	public function testWriteTurnsEveryProblemToTheTopLeft(): void
	{
		$context = $this->prepare();
		$before = Util::query('SELECT id, tsumego_id, user_id, sgf_status_id, created, accepted, description FROM sgf ORDER BY id');
		$this->exec('normalise_sgfs --write --batch 2');
		$this->assertExitSuccess();

		$bottomRight = $this->sgfRow($context->tsumegos[0]['id']);
		$this->assertSame("(;FF[4]GM[1]SZ[19]ST[2]\nAB[cc]\n;B[aa]\n;W[ab]\n;B[ba]\nC[+])", $bottomRight['sgf']);
		$this->assertSame(['B', 'aa'], [$bottomRight['first_move_color'], $bottomRight['correct_moves']]);
		$this->assertStringContainsString('"black":"cc"', $bottomRight['preview']);
		$comment = Util::query('SELECT message, position FROM tsumego_comment')[0];
		$this->assertSame(['message' => 'black A19 then w A18', 'position' => '1/2/1/1/2/1/2/1/sgf|1/1+1/2'], $comment);

		$this->assertSame('aa', $this->sgfRow($context->tsumegos[1]['id'])['correct_moves']);
		$this->assertSame(['B', 'aa'], [$this->sgfRow($context->tsumegos[2]['id'])['first_move_color'], $this->sgfRow($context->tsumegos[2]['id'])['correct_moves']]);
		$this->assertSame("(;FF[4]GM[1]SZ[19]ST[0]\nAB[bb]\n(;B[aa]\nC[+])\n(;B[dd]\n;W[aa]))", $this->sgfRow($context->tsumegos[3]['id'])['sgf'], 'the correct variation first');
		$this->assertSame(self::DONE, $this->sgfRow($context->tsumegos[4]['id'])['sgf'], 'already as the site keeps it');

		$this->assertSame($before, Util::query('SELECT id, tsumego_id, user_id, sgf_status_id, created, accepted, description FROM sgf ORDER BY id'), 'no row added, none changed but its SGF');
	}

	//The line a challenge was failed on is in the coordinates of its version, so it turns with it; a pass stays one
	public function testAChallengeFailedLineTurnsWithItsVersion(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'challenger'],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => [['sgf' => ['data' => self::BOTTOM_RIGHT, 'correct_moves' => 'ss', 'first_move_color' => 'B']]],
			'time-mode-sessions' => [['category' => TimeModeCategoryTable::BLITZ, 'status' => TimeModeSessionStatusTable::SOLVED, 'rank' => '5k',
				'attempts' => [['order' => 1, 'status' => TimeModeAttemptStatusTable::FAILED]]]]]);
		$sgfID = (int) $this->sgfRow($context->tsumegos[0]['id'])['id'];
		Util::execute('INSERT INTO challenge_failed_line (time_mode_attempt_id, moves, sgf_id) SELECT id, ?, ? FROM time_mode_attempt', ['sssrrstt', $sgfID]);

		$this->exec('normalise_sgfs');
		$this->assertOutputContains('1 challenge failed lines turned');
		$this->assertSame('sssrrstt', Util::query('SELECT moves FROM challenge_failed_line')[0]['moves']);

		$this->exec('normalise_sgfs --write');
		$this->assertSame('aaabbatt', Util::query('SELECT moves FROM challenge_failed_line')[0]['moves']);
	}

	public function testASecondRunChangesNothing(): void
	{
		$this->prepare();
		$this->exec('normalise_sgfs --write');
		$after = Util::query('SELECT * FROM sgf ORDER BY id');
		$comments = Util::query('SELECT * FROM tsumego_comment ORDER BY id');

		$this->exec('normalise_sgfs --write');
		$this->assertOutputContains('5 problems, 5 rows, 0 turned, 0 reordered only, 0 values only, 0 comments moved, 0 challenge failed lines turned, 0 transformations from source fixed');
		$this->assertSame($after, Util::query('SELECT * FROM sgf ORDER BY id'));
		$this->assertSame($comments, Util::query('SELECT * FROM tsumego_comment ORDER BY id'));
	}

	//Every version is rewritten, so the versions of a problem stay in one frame: the transformation from a source turned
	//like it is the identity again
	public function testTheVersionsAndTheirTransformationsStayInOneFrame(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sgfs' => [
			['data' => self::BOTTOM_RIGHT],
			['data' => self::BOTTOM_RIGHT, 'sgf_status_id' => SgfTable::STATUS_PROPOSED]]]]);
		$ids = array_column(Util::query('SELECT id FROM sgf WHERE tsumego_id = ? ORDER BY id', [$context->tsumegos[0]['id']]), 'id');
		Util::execute('UPDATE sgf SET source_sgf_id = ?, transformation_from_source = ? WHERE id = ?',
			[$ids[0], '{"transpose":false,"flipX":false,"flipY":false,"invertColors":false}', $ids[1]]);
		$statuses = array_column(Util::query('SELECT sgf_status_id FROM sgf ORDER BY id'), 'sgf_status_id');

		$this->exec('normalise_sgfs --write');
		$this->assertOutputContains('2 turned');
		$rows = Util::query('SELECT sgf, sgf_status_id, transformation_from_source FROM sgf ORDER BY id');
		$this->assertSame($rows[0]['sgf'], $rows[1]['sgf']);
		$this->assertSame('{"transpose":false,"flipX":false,"flipY":false,"invertColors":false}', $rows[1]['transformation_from_source']);
		$this->assertSame($statuses, array_column($rows, 'sgf_status_id'), 'a proposal stays a proposal');
	}

	public function testUnreadableRowsAreKept(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => self::BOTTOM_LEFT]]]);
		Util::execute("UPDATE sgf SET sgf = 'not an sgf'");
		$this->exec('normalise_sgfs --write');
		$this->assertOutputContains('1 unreadable (kept as they are): ' . $this->sgfRow($context->tsumegos[0]['id'])['id']);
		$this->assertSame('not an sgf', $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}

	//The two problems of the report end in the top-left with their correct moves ac, and find each other
	public function testThePairOfTheReportFindsEachOther(): void
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

		$this->exec('normalise_sgfs --write');
		foreach ([0, 1] as $index)
		{
			$row = $this->sgfRow($context->tsumegos[$index]['id']);
			$this->assertSame('ac', $row['correct_moves']);
			$this->assertStringContainsString('AB[bd][cb][cc]', $row['sgf'], 'the stones in the top-left corner');
		}
		$this->assertSame([$context->tsumegos[1]['id']], $found(0));
		$this->assertSame([$context->tsumegos[0]['id']], $found(1));
	}

	//A row whose rewrite besogo wouldn't play the same, here because it plays on an occupied point, is kept and named
	public function testARowThatWouldPlayDifferentlyIsKept(): void
	{
		//sgf 16731 of the site: B[ld] is played a second time where it already stands
		$sgf = '(;GM[1]FF[4]SZ[19]AW[mc][pc][jd][pd][qd][me][ne][oe][pf][qf][rf][og]AB[nd][od][pe][qe][re][kf][lf][mf][nf][of](;B[md];W[le](;B[kd];W[se](;B[ke];W[rd](;B[ld]C[+])(;B[pe]C[+]))(;B[ld];W[rd](;B[ld]C[+])(;B[pe]C[+])))(;B[ld];W[ke])(;B[ke];W[ld];B[kd];W[lc]))(;B[le];W[md]))';
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$id = $this->sgfRow($context->tsumegos[0]['id'])['id'];
		$this->exec('normalise_sgfs --write --show 0 --sgf ' . $id);

		$this->assertOutputContains("1 that would play differently (kept as they are): $id");
		$this->assertOutputContains("sgf $id (tsumego {$context->tsumegos[0]['id']}, current version) would play differently");
		$this->assertOutputContains("sgf $id before:");
		$this->assertSame($sgf, $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}

	//What was on a line besogo merges into a transposition goes with it: the labels of sgf 17040 of the site
	public function testARowLosingOnlyWhatWasOnAMergedLineIsRewritten(): void
	{
		$sgf = '(;SZ[13]AW[di][ei][fi][dj][gj][ck][fk][gk][cl][dl][fl][cm]AB[cg][dh][eh][fh][gh][ci][hi][cj][ej][hj][bk][dk][ek][hk][bl][gl][hl](;B[gi];W[fj];B[fm];W[el](;B[em];W[ek])(;B[ek];W[em]))(;B[fm](;W[em];B[gi];W[gm];B[fj]C[+])(;W[el](;B[gi];W[fj]LB[ek:A][em:B])(;B[em]C[+])))(;B[em];W[fm];B[el];W[fj]))';
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$id = $this->sgfRow($context->tsumegos[0]['id'])['id'];
		$this->exec('normalise_sgfs --write');

		$this->assertOutputContains('0 whose properties would change unexpectedly (kept as they are)');
		$this->assertOutputContains("1 rows rewritten lose what was on lines besogo merges into a transposition: $id; what went: ");
		$this->assertOutputContains('LB 2 in 1 rows');
		$this->assertNotSame($sgf, $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}

	//Setup listed twice (as sgf 19415 and 19416 of the site) shows one stone and is written once; the row is rewritten
	public function testARowWithSetupListedTwiceIsRewritten(): void
	{
		$sgf = '(;SZ[19]AB[rr][rs][rr]AW[qq](;B[ss]C[+])(;B[sr];W[qs]))';
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$id = $this->sgfRow($context->tsumegos[0]['id'])['id'];
		$this->exec('normalise_sgfs --write');

		$this->assertOutputContains('0 whose properties would change unexpectedly (kept as they are)');
		$this->assertOutputContains("1 rows rewritten have their setup written as besogo shows it");
		$this->assertOutputContains("sgf $id setup: AB -1");
		$this->assertStringContainsString('AB[ba][bb]', $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}

	//A row whose rewrite would change a property in a way a save isn't expected to, here one besogo doesn't know, is
	//kept and named with what would change
	public function testARowWhosePropertiesWouldChangeUnexpectedlyIsKept(): void
	{
		$sgf = '(;SZ[19]XY[1]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))';
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$id = $this->sgfRow($context->tsumegos[0]['id'])['id'];
		$this->exec('normalise_sgfs --write');

		$this->assertOutputContains("1 whose properties would change unexpectedly (kept as they are): $id");
		$this->assertOutputContains("sgf $id (tsumego {$context->tsumegos[0]['id']}, current version) would change: XY 1→0");
		$this->assertSame($sgf, $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}

	//A row kept as it is says whether it is the version shown or an old one of the history (as 19451 and 19452 of the
	//site, while the current version of their problem is clean)
	public function testAKeptOldVersionIsNamedSo(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sgfs' => [
			['data' => '(;SZ[19]XY[1]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))'],
			['data' => self::BOTTOM_RIGHT]]]]);
		$old = Util::query('SELECT MIN(id) AS id FROM sgf WHERE tsumego_id = ?', [$context->tsumegos[0]['id']])[0]['id'];
		$this->exec('normalise_sgfs');

		$this->assertOutputContains("sgf $old (tsumego {$context->tsumegos[0]['id']}, old version, not current) would change: XY 1→0");
	}

	//A status besogo can't read (S[undefined] of old saves, "A 2KO " for "A+2KO+") is left out, as every save does,
	//and counted; the others are written as they were
	public function testAStatusBesogoCantReadIsLeftOut(): void
	{
		$sgf = '(;SZ[19]G[KILL]AB[rb][sb](;B[sa];W[ra]S[A 2KO ])(;B[ra];W[qa]S[KO ])(;B[qa];W[ra]S[undefined]))';
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$id = $this->sgfRow($context->tsumegos[0]['id'])['id'];
		$this->exec('normalise_sgfs --write');

		$this->assertOutputContains("1 rows rewritten leave out 2 statuses besogo can't read (S[undefined], or a + an old save wrote as a space): $id");
		$stored = $this->sgfRow($context->tsumegos[0]['id'])['sgf'];
		$this->assertStringContainsString('S[KO ]', $stored);
		$this->assertStringNotContainsString('A 2KO', $stored);
		$this->assertStringNotContainsString('undefined', $stored);
	}

	//A row already as the site keeps it but for an S[undefined] (old saves wrote it for a status besogo couldn't read)
	//is rewritten without it
	public function testAnUndefinedStatusOfANormalisedRowIsLeftOut(): void
	{
		$sgf = "(;FF[4]GM[1]SZ[19]ST[0]\nAB[bb]\nG[KILL]\n(;B[ba]\n;W[aa]\nS[DEAD])\n(;B[aa]\n;W[ba]\nS[undefined]))";
		$context = new ContextPreparator(['tsumego' => ['sgf' => ['data' => $sgf]]]);
		$this->exec('normalise_sgfs --write');

		$this->assertOutputContains("1 rows rewritten leave out 1 statuses besogo can't read");
		$this->assertSame(str_replace("\nS[undefined]", '', $sgf), $this->sgfRow($context->tsumegos[0]['id'])['sgf']);
	}
}
