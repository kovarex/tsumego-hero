import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { analyse, makeBesogo } from './sgfCorrectMoves.mjs';

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
