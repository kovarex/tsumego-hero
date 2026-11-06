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
//"changed", "same_play", and when changed "property_changes", "unexpected_changes", "merged_away"), or {"id", "error"}
//when the SGF can't be read.
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
	const { editor, mergedAway } = loadTracked(besogo, sgf);
	const result = normalise ? normaliseLoaded(besogo, editor) : {};
	const root = editor.getRoot();
	const next = root.nextMove();
	const problem = root.checkTsumegoHeroCompatibility(root);
	return {
		first_move_color: next ? (next == BLACK ? 'B' : 'W') : 'N',
		correct_moves: root.exportCorrectMoves(),
		problem: problem ? problem.message + ' (move ' + problem.node.moveNumber + ')' : null,
		...(normalise ? normalisedText(besogo, editor, sgf, result, mergedAway) : {})
	};
}

//Loads an SGF as the site does, and tells which of its nodes besogo merged away, by their place in the text (depth
//first, the root 0): a line that transposes into another is pruned with all that comes after it (pruneTree of
//treeProblemUpdater.js). loadNodeTree of loadSgf.js makes a node for each node of the text, in that order, before
//anything else makes one, so the nodes are matched by the order they were made in.
export function loadTracked(besogo, sgf)
{
	const parsed = besogo.parseSgf(sgf);
	const editor = besogo.makeEditor(19, 19, {});
	const made = [];
	const makeGameRoot = besogo.makeGameRoot;
	let makeChild = null;
	besogo.makeGameRoot = function(...args)
	{
		const root = makeGameRoot.apply(this, args);
		made.push(root);
		makeChild = root.makeChild;
		root.makeChild = function()
		{
			const child = makeChild.call(this);
			made.push(child);
			return child;
		};
		return root;
	};
	try
	{
		besogo.loadSgf(parsed, editor);
	}
	finally
	{
		besogo.makeGameRoot = makeGameRoot;
	}
	const root = editor.getRoot();
	if (makeChild)
		root.makeChild = makeChild;
	const kept = new Set();
	const visit = node =>
	{
		kept.add(node);
		node.children.forEach(visit);
	};
	visit(root);
	const mergedAway = new Set();
	const count = textNodes(parsed).length;
	if (made.length >= count)
		for (let index = 0; index < count; ++index)
			if (!kept.has(made[index]))
				mergedAway.add(index);
	return { editor, mergedAway };
}

//The nodes of a parsed SGF, depth first
function textNodes(tree)
{
	const nodes = [];
	const visit = node =>
	{
		nodes.push(node);
		node.children.forEach(visit);
	};
	visit(tree);
	return nodes;
}

//The text of the normalised problem, and whether it plays the same: loaded again, its tree must have the same lines,
//moves, transpositions and correct or wrong ends as the turned tree it was composed from (the text leaves out what
//besogo merges, a transposition or a ko coming round, and markup or comments the board editor doesn't write)
function normalisedText(besogo, editor, sgf, result, mergedAway)
{
	if (!result.changed)
		return { sgf, ...result, same_play: true };
	const text = besogo.composeSgf(editor);
	const reloaded = besogo.makeEditor(19, 19, {});
	besogo.loadSgf(besogo.parseSgf(text), reloaded);
	const changes = propertyChanges(besogo, sgf, text, mergedAway);
	return { sgf: text, ...result, same_play: signature(editor.getRoot()) === signature(reloaded.getRoot()),
		property_changes: changes.counts, unexpected_changes: changes.unexpected, merged_away: changes.mergedAway };
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
//The header every save writes once on the root
const HEADER = new Set(['FF', 'GM', 'SZ', 'ST']);
//Old names besogo reads as the new ones
const ALIASES = { L: 'LB', M: 'MA' };
const POINT_LISTS = new Set(['AB', 'AW', 'AE', 'CR', 'SQ', 'TR', 'MA', 'SL', 'M']);

//The count of each property of the two texts that differ, {id: [before, after]}, those of the changes that aren't
//expected, as "id before→after", and what went with the nodes besogo merged away, {id: count} (mergedAway: their places
//in the text before, see loadTracked). What a merged away node had (its move, status, markup, comment) goes with it,
//as decided for the site; what any other node had must be written. Comments are compared whole, as besogo reads them
//(the C of a node joined, one leading + that marks the move correct left aside): every one on a node that stays must
//still be the comment of a node.
export function propertyChanges(besogo, before, after, mergedAway = new Set())
{
	const old = propertyCounts(besogo.parseSgf(before), mergedAway);
	const now = propertyCounts(besogo.parseSgf(after));
	const counts = {};
	const unexpected = [];
	const merged = {};
	for (const id of new Set([...old.counts.keys(), ...now.counts.keys()]))
	{
		const [was, is] = [old.counts.get(id) ?? 0, now.counts.get(id) ?? 0];
		if (was === is)
			continue;
		counts[id] = [was, is];
		const onKeptNodes = was - (old.merged.get(id) ?? 0);
		// all that is gone was on merged away nodes
		if (is < was && is === onKeptNodes)
		{
			merged[id] = was - is;
			continue;
		}
		const expected = id === 'C' ? is <= was
			: HEADER.has(id) ? is === 1
			: is < was && (NOT_KEPT.has(id) || GAME_INFO.has(id) || FEWER_EXPECTED.has(id));
		if (!expected)
			unexpected.push(id + ' ' + was + '→' + is + setupCause(old.setTwice.get(id) ?? 0, was - is));
	}
	const kept = new Set(now.comments.keys());
	const lost = [...old.comments].filter(([text]) => !kept.has(text));
	const lostOnKeptNodes = lost.filter(([, comment]) => comment.onKeptNode).map(([, comment]) => comment.written);
	if (lostOnKeptNodes.length)
		unexpected.push('C lost: ' + lostOnKeptNodes.map(comment => JSON.stringify(comment.slice(0, 40))).join(', '));
	return { counts, unexpected, mergedAway: merged };
}

//Why setup stones are fewer, when the text set the same point twice on a node (besogo keeps a point once)
function setupCause(setTwice, fewer)
{
	if (!setTwice || fewer <= 0)
		return '';
	return setTwice === fewer ? ' (each a point set twice on its node)' : ' (' + setTwice + ' of them a point set twice on its node)';
}

//The count of the values of each property of a parsed SGF (a point range of a point list as its points), the part of
//them on the nodes besogo merged away (by their place, depth first), the setup points set again on the same node, and
//its comments: the comment of each node as besogo reads it (loadSgf.js joins the C of a node, treeProblemUpdater.js
//takes one leading + as the mark of a correct move) => the comment as it was written and whether a node that stays has it
function propertyCounts(tree, mergedAway = new Set())
{
	const counts = new Map();
	const merged = new Map();
	const setTwice = new Map();
	const comments = new Map();
	textNodes(tree).forEach((node, index) =>
	{
		const away = mergedAway.has(index);
		const written = node.props.filter(prop => prop.id === 'C').map(prop => prop.values.join().trim()).join('\n');
		const text = (written.startsWith('+') ? written.slice(1) : written).trim();
		if (text)
		{
			const comment = comments.get(text) ?? { written, onKeptNode: false };
			comment.onKeptNode ||= !away;
			comments.set(text, comment);
		}
		const setup = new Set();
		for (const prop of node.props)
		{
			const id = ALIASES[prop.id] ?? prop.id;
			let count = prop.values.length;
			if (POINT_LISTS.has(prop.id))
				count = prop.values.reduce((sum, value) => sum + pointsIn(value), 0);
			counts.set(id, (counts.get(id) ?? 0) + count);
			if (away)
				merged.set(id, (merged.get(id) ?? 0) + count);
			if (SETUP.has(id))
				for (const value of prop.values)
					for (const point of pointsOf(value))
						if (setup.has(point))
							setTwice.set(id, (setTwice.get(id) ?? 0) + 1);
						else
							setup.add(point);
		}
	});
	return { counts, merged, setTwice, comments };
}

const SETUP = new Set(['AB', 'AW', 'AE']);

//The points of a point list value, "aa" or the rectangle "aa:cc", as letter pairs
function pointsOf(value)
{
	const range = value.match(/^([a-zA-Z])([a-zA-Z]):([a-zA-Z])([a-zA-Z])$/);
	if (!range)
		return [value];
	const points = [];
	const [from, to] = [[range[1], range[2]], [range[3], range[4]]].map(pair => pair.map(letter => letter.charCodeAt(0)));
	for (let x = Math.min(from[0], to[0]); x <= Math.max(from[0], to[0]); ++x)
		for (let y = Math.min(from[1], to[1]); y <= Math.max(from[1], to[1]); ++y)
			points.push(String.fromCharCode(x) + String.fromCharCode(y));
	return points;
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
