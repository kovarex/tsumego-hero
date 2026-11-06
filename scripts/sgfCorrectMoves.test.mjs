import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { analyse, makeBesogo, propertyChanges } from './sgfCorrectMoves.mjs';

const besogo = makeBesogo();
const of = sgf => analyse(besogo, sgf);

//Problems 979 and 27583 (pages 848 and 11420), imported as written: their stones are in the bottom-left and the
//bottom-right corner, where their correct first moves aq and sq are
const PROBLEM_979 = "(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]\nRU[Japanese]SZ[19]KM[0.00]\nAW[cm][bo][co][cp][dq][eq][gq]AB[bp][cq][cr]\n(;B[dr]\n;W[bq]\n;B[br]\n;W[ap])\n(;B[br]\n;W[aq]\n;B[ap]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[bq]\n;W[ar]\n;B[dr]\n;W[er]\n;B[ds]\n;W[bs])\n(;B[aq]\n;W[bs]\n(;B[br]\n;W[ao]\n(;B[as]\n;W[ar]\n;B[cs]\n;W[ap]\n(;B[bq]\n;W[dr]\n;B[as]C[+])\n(;B[as]\n;W[bq]))\n(;B[ap]\n;W[ar])\n(;B[cs]\n;W[ap]))\n(;B[dr]\n;W[br])))\n";
const PROBLEM_27583 = "(;FF[4]GM[1]CA[UTF-8]AP[besogo:0.0.2-alpha]SZ[19]ST[2]\nRU[Japanese]KM[6.50]DT[2021-09-08]\nAB[qq][qr][rp]AW[nq][oq][pp][pq][qo][qp][ro]\n(;B[sq]\n(;W[rs]\n(;B[rr]\n;W[so]\n(;B[ss]\n;W[sp]\n;B[rq]\n;W[sr]\n;B[qs]\nC[+])\n(;B[sp]\n;W[sr])\n(;B[qs]\n;W[sp]))\n(;B[pr]\n;W[or]\n;B[rr]\n;W[so]\n(;B[sp]\n;W[sr])\n(;B[ss]\n;W[sp])))\n(;W[so]\n(;B[rs]\n;W[pr]\n;B[sr]\nC[+])\n(;B[pr]\n;W[rs]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[or]\n;W[nr]\n(;B[rr]\n;W[sp]\n(;B[ss]\n;W[rq])\n(;B[rq]\n;W[ss]))\n(;B[os]\n;W[rr]\n;B[rq]\n;W[qs])))\n(;B[rr]\n;W[sp])))\n(;B[sp]\n;W[sr])\n(;B[rr]\n;W[sp]\n;B[sq]\n;W[rq])\n(;B[sr]\n;W[sp])\n(;B[pr]\n;W[rq]))";

test('a line ending in a correct move makes its first move correct', () =>
{
	assert.deepEqual(of('(;GM[1]FF[4]SZ[19];B[aa];W[ab];B[ba]C[+])'), { first_move_color: 'B', correct_moves: 'aa', problem: null });
});

test('a tree with only wrong lines has no correct move', () =>
{
	assert.equal(of('(;SZ[19](;B[aa];W[ab])(;B[ba];W[bb]))').correct_moves, '');
});

test('every correct first move is listed, in the order of the tree', () =>
{
	assert.equal(of('(;SZ[19]AB[bb](;B[aa]C[+])(;B[cc]C[+])(;B[dd]))').correct_moves, 'aacc');
	assert.equal(of('(;GM[1]FF[4]SZ[19](;W[cd])(;W[gg];B[ba]C[+])(;W[hh]C[+]))').correct_moves, 'gghh');
});

test('white can move first', () =>
{
	const result = of('(;SZ[19]AW[bb](;W[aa]C[+])(;W[cc]))');
	assert.equal(result.first_move_color, 'W');
	assert.equal(result.correct_moves, 'aa');
});

//As the setup page did: without a move the color is black's, besogo's first move by default
test('a problem without moves has no correct move', () =>
{
	assert.deepEqual(of('(;SZ[19]AB[dd][de]AW[ed])'), { first_move_color: 'B', correct_moves: '', problem: null });
});

//The second line reaches the position of the correct first one, so its first move is correct too
test('a transposition to a correct position makes its first move correct', () =>
{
	assert.equal(of('(;SZ[19](;B[aa];W[ss];B[bb]C[+])(;B[bb];W[ss];B[aa]))').correct_moves, 'aabb');
});

//Nothing turns the problem to a corner: the moves are as the SGF has them
test('a problem in any corner keeps the coordinates of its SGF', () =>
{
	assert.equal(of('(;SZ[19]AB[bb][ab](;B[aa]C[+])(;B[ba]))').correct_moves, 'aa');
	assert.equal(of('(;SZ[19]AB[rb][sb](;B[sa]C[+])(;B[ra]))').correct_moves, 'sa');
	assert.equal(of('(;SZ[19]AB[br][bs](;B[as]C[+])(;B[ar]))').correct_moves, 'as');
	assert.equal(of('(;SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr]))').correct_moves, 'ss');
	assert.equal(of('(;SZ[9]AB[hh][ih](;B[ii]C[+])(;B[hi]))').correct_moves, 'ii');
});

test('the two problems of the report get their correct moves where their stones are', () =>
{
	assert.deepEqual(of(PROBLEM_979), { first_move_color: 'B', correct_moves: 'aq', problem: null });
	assert.deepEqual(of(PROBLEM_27583), { first_move_color: 'B', correct_moves: 'sq', problem: null });
});

//What keeps the board editor from saving a problem
test('a line that ends in a solver move not marked correct is a problem', () =>
{
	assert.equal(of('(;SZ[19]AB[bb](;B[aa]C[+])(;B[dd]))').problem, 'Last solver move not marked correct (unless there are comments or markups) (move 1)');
});

test('the script reads JSON lines and answers each, a broken SGF with its error', () =>
{
	const script = join(dirname(fileURLToPath(import.meta.url)), 'sgfCorrectMoves.mjs');
	const input = [
		JSON.stringify({ id: 7, sgf: '(;SZ[19];B[aa]C[+])' }),
		'',
		JSON.stringify({ id: 'x', sgf: 'not an sgf' }),
		'not json',
		JSON.stringify({ id: 9 })].join('\n') + '\n';
	const run = spawnSync(process.execPath, [script], { input, encoding: 'utf-8' });
	assert.equal(run.status, 0, run.stderr);
	const lines = run.stdout.trim().split('\n').map(line => JSON.parse(line));
	assert.deepEqual(lines[0], { id: 7, first_move_color: 'B', correct_moves: 'aa', problem: null });
	assert.equal(lines[1].id, 'x');
	assert.match(lines[1].error, /Expected/);
	assert.equal(lines[2].id, null);
	assert.ok(lines[2].error);
	assert.deepEqual(lines[3], { id: 9, error: 'no sgf text' });
	assert.equal(lines.length, 4);
});

const normalised = sgf => analyse(besogo, sgf, true);

//A problem in any corner ends in the top-left one, with its correct moves turned along
test('normalising turns a problem in any corner to the top-left', () =>
{
	for (const [sgf, flipX, flipY] of [
		['(;SZ[19]AB[rb][sb](;B[sa]C[+])(;B[ra];W[qa]))', true, false],
		['(;SZ[19]AB[br][bs](;B[as]C[+])(;B[ar];W[aq]))', false, true],
		['(;SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))', true, true]])
	{
		const result = normalised(sgf);
		assert.deepEqual(result.transformation, { transpose: false, flipX, flipY, invertColors: false }, sgf);
		assert.equal(result.changed, true);
		assert.equal(result.same_play, true, sgf);
		assert.equal(result.correct_moves, 'aa', sgf);
		assert.match(result.sgf, /AB\[ba\]\[bb\]|AB\[ab\]\[bb\]/, sgf);
	}
});

test('normalising puts the correct variations first at every level, the others in their order', () =>
{
	//the correct first move is third, and after it the correct answer to white second
	const result = normalised('(;SZ[19]AB[cc](;B[ba];W[ab])(;B[ca];W[ab])(;B[aa];W[ab](;B[ca];W[ba])(;B[bb]C[+])))');
	assert.equal(result.changed, true);
	assert.equal(result.sgf.replace(/\s/g, ''), '(;FF[4]GM[1]SZ[19]ST[0]AB[cc](;B[aa];W[ab](;B[bb]C[+])(;B[ca];W[ba]))(;B[ba];W[ab])(;B[ca];W[ab]))');
	assert.equal(result.correct_moves, 'aa');
});

test('a problem already normalised keeps its text, and normalising twice changes nothing', () =>
{
	const done = '(;SZ[19]AB[bb](;B[aa]C[+])(;B[dd];W[aa]))';
	assert.deepEqual(normalised(done), { first_move_color: 'B', correct_moves: 'aa', problem: null, sgf: done, changed: false, same_play: true,
		transformation: { transpose: false, flipX: false, flipY: false, invertColors: false } });
	for (const sgf of [PROBLEM_979, PROBLEM_27583, '(;SZ[19]AB[rr][rs](;B[sr];W[qs])(;B[ss]C[+]))'])
	{
		const once = normalised(sgf);
		const twice = normalised(once.sgf);
		assert.equal(twice.changed, false);
		assert.equal(twice.sgf, once.sgf);
		assert.equal(twice.correct_moves, once.correct_moves);
	}
});

test('the two problems of the report end in the top-left corner with their correct move ac', () =>
{
	const a = normalised(PROBLEM_979);
	const b = normalised(PROBLEM_27583);
	assert.deepEqual([a.correct_moves, a.transformation.flipX, a.transformation.flipY], ['ac', false, true]);
	assert.deepEqual([b.correct_moves, b.transformation.flipX, b.transformation.flipY], ['ac', true, true]);
	//the correct line, fourth in 979, comes first
	assert.match(a.sgf, /^\(;[^(]*\n\(;B\[ac\]/);
});

//The text leaves out what besogo merges: a line that transposes into another and the moves after a ko comes round.
//Loaded again it plays the same, which normalising checks (sgf 17040 and 16783 of the site). The labels on the merged
//line of 17040 go with it, as all a merged away node has.
test('a rewrite that leaves out merged moves still plays the same', () =>
{
	for (const [sgf, mergedAway] of [
		['(;SZ[13]AW[di][ei][fi][dj][gj][ck][fk][gk][cl][dl][fl][cm]AB[cg][dh][eh][fh][gh][ci][hi][cj][ej][hj][bk][dk][ek][hk][bl][gl][hl](;B[gi];W[fj];B[fm];W[el](;B[em];W[ek])(;B[ek];W[em]))(;B[fm](;W[em];B[gi];W[gm];B[fj]C[+])(;W[el](;B[gi];W[fj]LB[ek:A][em:B])(;B[em]C[+])))(;B[em];W[fm];B[el];W[fj]))', { W: 1, LB: 2 }],
		['(;PL[W]SZ[9]AB[ad][ae][ag][bb][be][bf][bg][bi][ch][fc][gb][gd][ge][gf][gi][hf][hg][hh][hi]AW[ai][bc][bd][cc][ce][cf][cg][dd][dh][eb][ec][eh][fd][fe][ff][fi][gg][gh][if][ii](;W[fb];B[ac](;W[gc];B[hc];W[hb];B[ga];W[hd];B[fc];W[ci];B[bh];W[gc];B[ic])(;W[hb];B[ga];W[gc];B[hc];W[fa];B[fc];W[bh];B[ah];W[gc];B[fg];W[ha];B[fh]))(;W[ga];B[fa];W[fb];B[ha];W[gc];B[hc];W[ac];B[ea];W[da];B[ga];W[db];B[fc];W[di]C[+]))', { B: 5, W: 4 }]])
	{
		const result = normalised(sgf);
		assert.equal(result.changed, true);
		assert.equal(result.same_play, true);
		assert.deepEqual(result.unexpected_changes, []);
		assert.deepEqual(result.merged_away, mergedAway);
	}
});

//What a node besogo merges into a transposition has goes with it: its move, status, markup, comment. The same lost on a
//node that stays keeps the rewrite from being stored, and so does a setup stone lost, said why when a point was set twice.
test('only what was on the merged away nodes may go', () =>
{
	const merged = normalised('(;SZ[19]AB[dd](;B[jj];W[pd])(;B[pp];W[pd];B[dp]C[+])(;B[dp];W[pd];B[pp]C[A real comment]TR[qq]S[DEAD]))');
	assert.equal(merged.changed, true);
	assert.deepEqual(merged.unexpected_changes, []);
	assert.deepEqual(merged.merged_away, { B: 1, C: 1, TR: 1, S: 1 });
	//W[sa] is a suicide, besogo keeps its node without the move
	const illegal = normalised('(;SZ[19]G[KILL]AB[rb][sb](;B[sa];W[ra]S[DEAD])(;B[ra];W[sa]C[on a kept node]S[ALIVE]))');
	assert.deepEqual(illegal.unexpected_changes, ['W 2→1']);
	//setup listed twice (sgf 19415 and 19416 of the site) or in both AB and AW shows one stone, written once: the stones
	//shown are the same, which the play check compares
	for (const sgf of ['(;SZ[19]AB[rr][rs][rr]AW[qq](;B[ss]C[+])(;B[sr];W[qs]))', '(;SZ[19]AB[rr][rs][qq]AW[qq](;B[ss]C[+])(;B[sr];W[qs]))'])
	{
		const listedTwice = normalised(sgf);
		assert.equal(listedTwice.same_play, true, sgf);
		assert.deepEqual(listedTwice.unexpected_changes, []);
		assert.deepEqual(listedTwice.setup_rewritten, { AB: -1 });
	}
	//a status on a node whose position goes on by a transposition is set aside by besogo (as on 24 rows of the site) and
	//may go; an illegal move is named (sgf 19451 and 19452 of the site play on a stone)
	const ignored = normalised('(;SZ[19]G[KILL]AB[jj](;B[qq];W[rr]S[ALIVE])(;B[ss];W[rr];B[qq]S[DEAD]))');
	assert.deepEqual([ignored.changed, ignored.same_play, ignored.unexpected_changes, ignored.status_ignored], [true, true, [], 1]);
	assert.deepEqual(normalised('(;SZ[19]AB[rr][rs][qq](;B[ss]C[+])(;B[sr];W[qs];B[qq];W[ps]))').unexpected_changes,
		['B 3→2 (1 illegal, besogo leaves it out)']);
	//a stone shown that the rewrite loses keeps the row as it is: the turn leaves out the setup of a node after a move
	assert.equal(normalised('(;SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs];AB[qs]))').same_play, false);
});

//G is the goal of a status problem, and any other G is kept as it is too, once, on the node it was on
test('G survives the rewrite in every corner, as it was written', () =>
{
	for (const [sgf, goal] of [
		['(;SZ[19]G[KILL]AB[rb][sb](;B[sa];W[ra]S[DEAD])(;B[ra];W[qa]S[ALIVE]))', 'G[KILL]'],
		['(;SZ[19]G[LIVE]AW[br][bs](;B[as];W[ar]S[ALIVE])(;B[ar];W[as]S[DEAD]))', 'G[LIVE]'],
		['(;SZ[19]G[tt]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))', 'G[tt]'],
		['(;SZ[19]AB[rb][sb](;B[sa]G[custom];W[ra];B[qa]C[+])(;B[ra];W[qa]))', 'G[custom]']])
	{
		const result = normalised(sgf);
		assert.equal(result.changed, true, sgf);
		assert.equal(result.same_play, true, sgf);
		//once, where it was: the nodes after it inherit it in besogo, but don't write it
		assert.equal(result.sgf.split('G[').length - 1, 1, result.sgf);
		assert.ok(result.sgf.includes(goal), result.sgf);
		assert.deepEqual(result.unexpected_changes, []);
		assert.equal(normalised(result.sgf).changed, false);
	}
});

//With a goal besogo judges by the statuses, the + of a status problem changes nothing
test('a status problem is judged the same without its C[+]', () =>
{
	const withPlus = analyse(besogo, '(;SZ[19]G[KILL]AB[ad][ba][bd][ca][cc][dc][ec][fa][fb]AW[ac][bb][bc][cb][db][ea](;B[eb];W[aa]S[ALIVE])(;B[aa]C[+]S[BENT4])(;B[ab];W[aa]S[ALIVE]))');
	const without = analyse(besogo, '(;SZ[19]G[KILL]AB[ad][ba][bd][ca][cc][dc][ec][fa][fb]AW[ac][bb][bc][cb][db][ea](;B[eb];W[aa]S[ALIVE])(;B[aa]S[BENT4])(;B[ab];W[aa]S[ALIVE]))');
	assert.equal(withPlus.correct_moves, 'aa');
	assert.equal(without.correct_moves, withPlus.correct_moves);
});

//A rewrite may leave out what besogo doesn't keep and add the header of every save; any other change of the count of a
//property is unexpected, and so is a comment that is gone
test('the property check tells the expected changes from the others', () =>
{
	const check = (before, after, mergedAway = new Set()) => propertyChanges(besogo.besogo, before, after, { mergedAway }).unexpected;
	const was = '(;GM[1]FF[4]CA[UTF-8]AP[x]PW[]SZ[19]G[KILL]AB[aa:bb]L[cc]C[hi]C[there];B[dd]C[+];W[ee]S[ALIVE])';
	assert.deepEqual(check(was, '(;FF[4]GM[1]SZ[19]ST[0]G[KILL]AB[aa][ab][ba][bb]LB[cc:A]C[hi\nthere];B[dd];W[ee]S[ALIVE])'), []);
	for (const [after, unexpected] of [
		['(;FF[4]GM[1]SZ[19]G[KILL]AB[aa][ab][ba][bb]LB[cc:A]C[hi\nthere];B[dd]G[KILL];W[ee]S[ALIVE]G[KILL])', ['G 1→3']],
		['(;FF[4]GM[1]SZ[19]AB[aa][ab][ba][bb]LB[cc:A]C[hi\nthere];B[dd];W[ee]S[ALIVE])', ['G 1→0']],
		['(;FF[4]GM[1]SZ[19]G[KILL]AB[aa][ab][ba][bb]C[hi\nthere];B[dd];W[ee]S[ALIVE])', ['LB 1→0']],
		['(;FF[4]GM[1]SZ[19]G[KILL]AB[aa][ab][ba][bb]LB[cc:A]C[hi];B[dd];W[ee]S[ALIVE])', ['C lost: "hi\\nthere"']],
		['(;FF[4]GM[1]SZ[19]G[KILL]AB[aa][ab][ba][bb]LB[cc:A]C[hi\nthere];B[dd];W[ee])', ['S 1→0']],
		['(;FF[4]GM[1]SZ[19]G[KILL]AB[aa][ab][ba][bb]LB[cc:A]C[hi\nthere];B[dd]S[ALIVE])', ['W 1→0']]])
		assert.deepEqual(check(was, after), unexpected, after);
	//a comment is compared whole: one inside another comment is still lost (sgf 16088 of the site), and a ++ shows as it was
	assert.deepEqual(check('(;SZ[19]C[Wrong\nEye shape];B[aa]C[Eye shape])', '(;FF[4]GM[1]SZ[19]ST[0]C[Wrong\nEye shape];B[aa])'), ['C lost: "Eye shape"']);
	assert.deepEqual(check('(;SZ[19];B[aa]C[++])', '(;FF[4]GM[1]SZ[19]ST[0];B[aa])'), ['C lost: "++"']);
	//the same comment twice, on a line and the line merged into it, may be written once
	assert.deepEqual(check('(;SZ[19](;B[aa]C[Same])(;B[bb]C[Same]))', '(;FF[4]GM[1]SZ[19]ST[0](;B[aa]C[Same])(;B[bb]))'), []);
	//a node merged away (here the third, W[bb]) goes with all it has; the same on a node that stays is lost
	assert.deepEqual(check('(;SZ[19](;B[aa];W[bb]C[Merged]TR[cc])(;B[cc]))', '(;FF[4]GM[1]SZ[19]ST[0](;B[aa])(;B[cc]))', new Set([2])), []);
	assert.deepEqual(check('(;SZ[19](;B[aa];W[bb]C[Merged]TR[cc])(;B[cc]))', '(;FF[4]GM[1]SZ[19]ST[0](;B[aa])(;B[cc]))'),
		['W 1→0', 'TR 1→0', 'C lost: "Merged"']);
	//a property besogo doesn't know is never dropped unseen
	assert.deepEqual(check('(;SZ[19]XY[1];B[aa])', '(;FF[4]GM[1]SZ[19]ST[0];B[aa])'), ['XY 1→0']);
});

//The board editor saves with the same composeSgf, without the turn: what it doesn't keep is all it leaves out
test('an editor save changes no property but those besogo does not keep', () =>
{
	for (const sgf of [PROBLEM_979, PROBLEM_27583,
		'(;GM[1]FF[4]CA[UTF-8]AP[x]PW[]PB[Black]GN[name]SZ[19]G[KILL]AB[rb][sb]L[pa](;B[sa]C[a comment];W[ra]S[DEAD])(;B[ra];W[qa]S[ALIVE]))',
		'(;SZ[19]G[tt]AB[rr][rs]AE[aa]TR[qq]MA[pp]CR[oo]SQ[nn](;B[ss]C[+])(;B[sr];W[qs]))',
		'(;SZ[19]AB[rb][sb](;B[sa]G[custom];W[ra];B[qa]C[+])(;B[ra];W[qa]))',
		'(;SZ[13]AB[aa:bc]AW[da:db](;B[ca]C[+])(;B[cb];W[ca]))'])
	{
		const editor = besogo.besogo.makeEditor(19, 19, {});
		besogo.besogo.loadSgf(besogo.besogo.parseSgf(sgf), editor);
		assert.deepEqual(propertyChanges(besogo.besogo, sgf, besogo.besogo.composeSgf(editor)).unexpected, [], sgf);
	}
});
