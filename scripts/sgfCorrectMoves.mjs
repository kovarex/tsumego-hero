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
//"changed", "same_play", and when changed "property_changes", "unexpected_changes"), or {"id", "error"} when the SGF
//can't be read.
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
		...(normalise ? normalisedText(besogo, editor, sgf, result) : {})
	};
}

//The text of the normalised problem, and whether it plays the same: loaded again, its tree must have the same lines,
//moves, transpositions and correct or wrong ends as the turned tree it was composed from (the text leaves out what
//besogo merges, a transposition or a ko coming round, and markup or comments the board editor doesn't write)
function normalisedText(besogo, editor, sgf, result)
{
	if (!result.changed)
		return { sgf, ...result, same_play: true };
	const text = besogo.composeSgf(editor);
	const reloaded = besogo.makeEditor(19, 19, {});
	besogo.loadSgf(besogo.parseSgf(text), reloaded);
	const changes = propertyChanges(besogo, sgf, text, mergesMoves(editor.getRoot()));
	return { sgf: text, ...result, same_play: signature(editor.getRoot()) === signature(reloaded.getRoot()),
		property_changes: changes.counts, unexpected_changes: changes.unexpected };
}

//What a save may change in the properties of an SGF, besides the coordinates. Any other change of the count of a property
//(G written on every node, a stone or a comment lost) is unexpected, and a rewrite with one is not stored.
//Properties besogo doesn't keep, the board editor drops them on every save: fewer of them is expected
const NOT_KEPT = new Set(['AP', 'CA', 'FG', 'PM', 'WV', 'TE', 'BM', 'DO', 'IT', 'OB', 'OW', 'BL', 'WL', 'PL', 'MN', 'V', 'DM',
	'GB', 'GW', 'HO', 'UC', 'N', 'VW', 'AR', 'LN', 'DD', 'KO', 'TB', 'TW', 'GOGGPAP', 'GOGGPFF', 'SBKV']);
//Game info (loadRootProps of loadSgf.js) is kept on the root when it isn't empty: fewer is expected
const GAME_INFO = new Set(['PB', 'BR', 'BT', 'PW', 'WR', 'WT', 'HA', 'KM', 'RU', 'TM', 'OT', 'DT', 'EV', 'GN', 'PC', 'RO', 'GC', 'ON',
	'RE', 'AN', 'CP', 'SO', 'US']);
//AE that empties an empty point does nothing and isn't written
const FEWER_EXPECTED = new Set(['AE']);
//Moves besogo merged into a transposition are written once, in the line they merged into; the play check sees that the
//tree plays the same. In a tree without one, every move must be written.
const MOVES = new Set(['B', 'W']);
//The header every save writes once on the root
const HEADER = new Set(['FF', 'GM', 'SZ', 'ST']);
//Old names besogo reads as the new ones
const ALIASES = { L: 'LB', M: 'MA' };
const POINT_LISTS = new Set(['AB', 'AW', 'AE', 'CR', 'SQ', 'TR', 'MA', 'SL', 'M']);

//The count of each property of the two texts that differ, {id: [before, after]}, and those of the changes that aren't
//expected, as "id before→after". Comments are compared whole, as besogo reads them (the C of a node joined, one leading +
//that marks the move correct left aside): every one must still be the comment of a node; a merged line may leave out a
//comment written the same on the line it merged into.
export function propertyChanges(besogo, before, after, mergedMoves = false)
{
	const old = propertyCounts(besogo.parseSgf(before));
	const now = propertyCounts(besogo.parseSgf(after));
	const counts = {};
	const unexpected = [];
	for (const id of new Set([...old.counts.keys(), ...now.counts.keys()]))
	{
		const [was, is] = [old.counts.get(id) ?? 0, now.counts.get(id) ?? 0];
		if (was === is)
			continue;
		counts[id] = [was, is];
		const expected = id === 'C' ? is <= was
			: HEADER.has(id) ? is === 1
			: is < was && (NOT_KEPT.has(id) || GAME_INFO.has(id) || FEWER_EXPECTED.has(id) || (mergedMoves && MOVES.has(id)));
		if (!expected)
			unexpected.push(id + ' ' + was + '→' + is);
	}
	const kept = new Set(now.comments.keys());
	const lost = [...old.comments].filter(([text]) => !kept.has(text)).map(([, written]) => written);
	if (lost.length)
		unexpected.push('C lost: ' + lost.map(comment => JSON.stringify(comment.slice(0, 40))).join(', '));
	return { counts, unexpected };
}

//Whether besogo merged a move of the tree into a transposition (a virtual child)
function mergesMoves(root, seen = new Set())
{
	if (seen.has(root))
		return false;
	seen.add(root);
	return root.virtualChildren.length > 0 || root.children.some(child => mergesMoves(child, seen));
}

//The count of the values of each property of a parsed SGF (a point range of a point list as its points), and its comments:
//the comment of each node as besogo reads it (loadSgf.js joins the C of a node, treeProblemUpdater.js takes one leading
//+ as the mark of a correct move) => the comment as it was written
function propertyCounts(tree)
{
	const counts = new Map();
	const comments = new Map();
	const visit = node =>
	{
		const written = node.props.filter(prop => prop.id === 'C').map(prop => prop.values.join().trim()).join('\n');
		const text = (written.startsWith('+') ? written.slice(1) : written).trim();
		if (text && !comments.has(text))
			comments.set(text, written);
		for (const prop of node.props)
		{
			const id = ALIASES[prop.id] ?? prop.id;
			let count = prop.values.length;
			if (POINT_LISTS.has(prop.id))
				count = prop.values.reduce((sum, value) => sum + pointsIn(value), 0);
			counts.set(id, (counts.get(id) ?? 0) + count);
		}
		node.children.forEach(visit);
	};
	visit(tree);
	return { counts, comments };
}

//The points of a point list value: "aa", or the rectangle "aa:cc"
function pointsIn(value)
{
	const range = value.match(/^([a-zA-Z])([a-zA-Z]):([a-zA-Z])([a-zA-Z])$/);
	if (!range)
		return 1;
	const at = letter => letter.charCodeAt(0);
	return (Math.abs(at(range[3]) - at(range[1])) + 1) * (Math.abs(at(range[4]) - at(range[2])) + 1);
}

//The G of a node as a save writes it (saveSgf.js): the text it was loaded with, or the goal the editor set on the root
function goalOf(node)
{
	//only the node G was on; its children inherit goalText through makeChild
	if (Object.hasOwn(node, 'goalText') && node.goalText !== undefined && node.goalText !== null)
		return 'G' + node.goalText;
	//besogo.goalStr of status.js, which writes the goal the editor set
	return node.parent == null && node.goal ? 'G' + ({ 1: 'KILL', 2: 'LIVE' }[node.goal] ?? '') : '';
}

//What a tree plays as, with its G: every move from every position, real or merged by a transposition, the order left aside, with
//whether each position is correct, wrong or neither. A position met again on its own line (a ko) counts as reached.
function signature(root)
{
	const known = new Map();
	const onLine = new Set();
	const of = node =>
	{
		if (known.has(node))
			return known.get(node);
		if (onLine.has(node))
			return 'again';
		onLine.add(node);
		//a turned virtual move keeps only its point (gameRoot's applyTransformation), its color is the target's
		const moves = node.children.map(child => [child.move, child]).concat(node.virtualChildren.map(virtual => [virtual.move, virtual.target]))
			.map(([move, child]) => (move ? ((move.color ?? child.move?.color) == -1 ? 'B' : 'W') + move.x + ',' + move.y : 'pass') + '(' + of(child) + ')')
			.sort();
		onLine.delete(node);
		const result = node.correct + ':' + goalOf(node) + ':' + moves.join(';');
		known.set(node, result);
		return result;
	};
	return of(root);
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
