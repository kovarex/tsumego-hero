#!/usr/bin/env node
//What besogo, the board of the site, says about an SGF, for the server (App\Utility\SgfAnalysis): the color
//that moves first ('B', 'W', or 'N' when the tree has no move), the first moves it marks correct (sgf.correct_moves,
//letter pairs in the coordinates of the SGF itself, as the SGF has them: no corner or orientation is applied),
//and the problem that keeps the board editor from saving it, if any.
//It runs the very files of webroot/besogo/js the site serves, in a context of their own with the few browser
//globals they touch stubbed, so the rules of correct and wrong (statuses, goals, transpositions) are not forked.
//With "normalise": true it also gives the SGF as the site stores it (normalise below), and the values are of that SGF.
//Input on stdin: JSON lines, each {"id": any, "sgf": "(;...)", "normalise"?: bool}. Output on stdout: a JSON line for
//each, in order, {"id", "first_move_color", "correct_moves", "problem"} (and with normalise "sgf", "transformation",
//"changed"), or {"id", "error"} when the SGF can't be read.
import { readFileSync } from 'node:fs';
import { createInterface } from 'node:readline';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const BESOGO = join(dirname(fileURLToPath(import.meta.url)), '..', 'webroot', 'besogo', 'js');

//The files that parse an SGF into besogo's tree and mark it as a problem, in the order the site loads them
//(LEGACY_BESOGO_FILES of vite.config.js); the rest of besogo draws the board
const FILES = [
	'besogo.js',
	'transformation.js',
	'treeProblemUpdater.js',
	'nodeHashTable.js',
	'editor.js',
	'gameRoot.js',
	'status.js',
	'parseSgf.js',
	'loadSgf.js',
	'saveSgf.js',
	'scaleParameters.js'];

export function makeBesogo()
{
	//Nothing is drawn: an element any query finds is none, and the document has nothing to listen to
	const document = {
		querySelectorAll: () => [],
		querySelector: () => null,
		getElementById: () => null,
		addEventListener: () => {},
		createElement: () => ({ style: {}, setAttribute() {}, appendChild() {}, addEventListener() {} })
	};
	const context = vm.createContext({ console, document, setTimeout, clearTimeout, structuredClone });
	context.window = context;
	const source = FILES.map(file => readFileSync(join(BESOGO, file), 'utf-8')).join(';\n');
	vm.runInContext(source + '\n;({ besogo, BLACK })', context, { filename: 'besogo' });
	return vm.runInContext('({ besogo, BLACK })', context);
}

//The analysis of one SGF text, as the setup pages of the site did it on the loaded board. With normalise, the SGF
//the site stores for it comes along (normalise below) and the values are those of that SGF.
export function analyse({ besogo, BLACK }, sgf, normalise = false)
{
	const editor = besogo.makeEditor(19, 19, {});
	besogo.loadSgf(besogo.parseSgf(sgf), editor);
	const result = normalise ? normaliseLoaded(besogo, editor) : {};
	const root = editor.getRoot();
	const next = root.nextMove();
	const problem = root.checkTsumegoHeroCompatibility(root);
	return {
		first_move_color: next ? (next == BLACK ? 'B' : 'W') : 'N',
		correct_moves: root.exportCorrectMoves(),
		problem: problem ? problem.message + ' (move ' + problem.node.moveNumber + ')' : null,
		...(normalise ? { sgf: result.changed ? besogo.composeSgf(editor) : sgf, ...result } : {})
	};
}

//The problem as the site stores it: turned to the top-left corner the way besogo turns it to show it
//(scaleParameters.prepareTransformation in parseAndLoad), and the correct variations first at every level of the
//tree, the others in the order they were. A problem already like that keeps its text (changed is false); otherwise
//the text is composed as the board editor saves it. transformation goes from the old coordinates to the new ones,
//as App\Utility\BoardTransformation reads it.
function normaliseLoaded(besogo, editor)
{
	const turn = besogo.makeTransformation();
	besogo.scaleParameters.prepareTransformation(turn);
	if (turn.hFlip || turn.vFlip)
		editor.applyTransformation(turn);
	const reordered = correctFirst(editor.getRoot());
	//a plain object, the one of besogo comes from its own context
	const transformation = JSON.parse(JSON.stringify(editor.getTransformationFromSgf()));
	return { transformation, changed: reordered || transformation.transpose || transformation.flipX || transformation.flipY };
}

//Puts the correct children of every node of the tree before the others, keeping the order within each; whether it moved any
function correctFirst(node, seen = new Set())
{
	if (seen.has(node))
		return false;
	seen.add(node);
	const CORRECT_GOOD = 1;
	const sorted = node.children.filter(child => child.correct == CORRECT_GOOD).concat(node.children.filter(child => child.correct != CORRECT_GOOD));
	let moved = sorted.some((child, index) => child !== node.children[index]);
	node.children.splice(0, node.children.length, ...sorted);
	for (const child of node.children)
		moved = correctFirst(child, seen) || moved;
	return moved;
}

async function main()
{
	const loaded = makeBesogo();
	const lines = createInterface({ input: process.stdin, crlfDelay: Infinity });
	for await (const line of lines)
	{
		if (line.trim() === '')
			continue;
		let id = null;
		let result;
		try
		{
			const input = JSON.parse(line);
			id = input.id ?? null;
			if (typeof input.sgf !== 'string')
				throw new Error('no sgf text');
			result = { id, ...analyse(loaded, input.sgf, input.normalise === true) };
		}
		catch (error)
		{
			result = { id, error: String(error && error.message ? error.message : error) };
		}
		process.stdout.write(JSON.stringify(result) + '\n');
	}
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1])
	await main();
