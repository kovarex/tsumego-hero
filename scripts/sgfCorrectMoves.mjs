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
//"changed", "same_play", and when changed "property_changes", "unexpected_changes", "merged_away", "setup_rewritten",
//"unreadable_statuses"), or {"id", "error"} when the SGF can't be read.
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
	const { editor, loaded } = loadTracked(besogo, sgf);
	const result = normalise ? normaliseLoaded(besogo, editor) : {};
	// a status besogo can't read (S[undefined]) is never written, so a text with one isn't as the site keeps it
	if (normalise && !result.changed && hasUnreadableStatus(besogo, sgf))
		result.changed = true;
	const root = editor.getRoot();
	const next = root.nextMove();
	const problem = root.checkTsumegoHeroCompatibility(root);
	return {
		first_move_color: next ? (next == BLACK ? 'B' : 'W') : 'N',
		correct_moves: root.exportCorrectMoves(),
		problem: problem ? problem.message + ' (move ' + problem.node.moveNumber + ')' : null,
		...(normalise ? normalisedText(besogo, editor, sgf, result, loaded) : {})
	};
}

//Loads an SGF as the site does, and tells by their place in the text (depth first, the root 0) which of its nodes
//besogo shows otherwise than written: mergedAway, the nodes of a line that transposes into another, pruned with all
//that comes after them (pruneTree of treeProblemUpdater.js); movesLeftOut, the nodes whose move is illegal (besogo
//couldn't play it, or played it over a stone). loadNodeTree of loadSgf.js
//makes a node for each node of the text, in that order, before anything else makes one, so the nodes are matched by
//the order they were made in.
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
	const loaded = { mergedAway: new Set(), movesLeftOut: new Set() };
	const nodes = textNodes(parsed);
	if (made.length >= nodes.length)
		nodes.forEach((node, index) =>
		{
			const has = id => node.props.some(prop => prop.id === id);
			if (!kept.has(made[index]))
				loaded.mergedAway.add(index);
			if (kept.has(made[index]) && (has('B') || has('W')) && (!made[index].move || made[index].move.overwrite))
				loaded.movesLeftOut.add(index);
		});
	return { editor, loaded };
}

//Whether besogo reads an S value as a status: what it can't read comes out as "undefined" when written
export function statusReads(besogo, value)
{
	return !besogo.loadStatusFromString(value.trim()).str().includes('undefined');
}

//Whether an SGF has an S value besogo can't read
function hasUnreadableStatus(besogo, sgf)
{
	return textNodes(besogo.parseSgf(sgf)).some(node =>
		node.props.some(prop => prop.id === 'S' && prop.values.some(value => !statusReads(besogo, value))));
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
function normalisedText(besogo, editor, sgf, result, loaded)
{
	if (!result.changed)
	{
		const { loadedSignature, ...shown } = result;
		return { sgf, ...shown, same_play: true };
	}
	const text = besogo.composeSgf(editor);
	const reloaded = besogo.makeEditor(19, 19, {});
	besogo.loadSgf(besogo.parseSgf(text), reloaded);
	const changes = propertyChanges(besogo, sgf, text, loaded);
	const { loadedSignature, ...shown } = result;
	return { sgf: text, ...shown, same_play: loadedSignature === signature(reloaded.getRoot()),
		property_changes: changes.counts, unexpected_changes: changes.unexpected, merged_away: changes.mergedAway,
		setup_rewritten: changes.setup, unreadable_statuses: changes.unreadableStatuses };
}

//What a save may change in the properties of an SGF, besides the coordinates. Any other change of the count of a property
//(G written on every node, a stone or a comment lost) is unexpected, and a rewrite with one is not stored.
//Properties besogo doesn't keep, the board editor drops them on every save: fewer of them is expected
const NOT_KEPT = new Set(['AP', 'CA', 'FG', 'PM', 'WV', 'TE', 'BM', 'DO', 'IT', 'OB', 'OW', 'BL', 'WL', 'PL', 'MN', 'V', 'DM',
	'GB', 'GW', 'HO', 'UC', 'N', 'VW', 'AR', 'LN', 'DD', 'KO', 'TB', 'TW', 'GOGGPAP', 'GOGGPFF', 'SBKV']);
//Game info (loadRootProps of loadSgf.js) is kept on the root when it isn't empty: fewer is expected
const GAME_INFO = new Set(['PB', 'BR', 'BT', 'PW', 'WR', 'WT', 'HA', 'KM', 'RU', 'TM', 'OT', 'DT', 'EV', 'GN', 'PC', 'RO', 'GC', 'ON',
	'RE', 'AN', 'CP', 'SO', 'US']);
//Setup stones are written where besogo shows them (getSetup of gameRoot.js: where a node's stone differs from its
//parent's), once: a point listed twice, in both AB and AW, or set to the stone it already has, or AE on an empty point,
//shows nothing more. Their count may change; the play check compares the stones shown at the root and every node with
//setup, so a stone that would be gone or change colour keeps the row as it is.
const SETUP = new Set(['AB', 'AW', 'AE']);
//The header every save writes once on the root
const HEADER = new Set(['FF', 'GM', 'SZ', 'ST']);
//Old names besogo reads as the new ones
const ALIASES = { L: 'LB', M: 'MA' };
const POINT_LISTS = new Set(['AB', 'AW', 'AE', 'CR', 'SQ', 'TR', 'MA', 'SL', 'M']);

//The count of each property of the two texts that differ, {id: [before, after]}, those of the changes that aren't
//expected, as "id before→after", and what went with the nodes besogo merged away, {id: count} (loaded: the places of
//those nodes in the text before, see loadTracked). What a merged away node had (its move, status, markup, comment)
//goes with it, as decided for the site; what any other node had must be written. An illegal move is named in the change. Comments are compared whole, as besogo reads them
//(the C of a node joined, one leading + that marks the move correct left aside): every one on a node that stays must
//still be the comment of a node.
export function propertyChanges(besogo, before, after, loaded = {})
{
	const old = propertyCounts(besogo.parseSgf(before), loaded);
	const now = propertyCounts(besogo.parseSgf(after));
	const counts = {};
	const unexpected = [];
	const merged = {};
	const setup = {};
	// a status besogo can't read (S[undefined], or a + an old save wrote as a space) is left out by every save
	const unreadableStatuses = old.statuses.filter(status => !statusReads(besogo, status)).length;
	for (const id of new Set([...old.counts.keys(), ...now.counts.keys()]))
	{
		const [was, is] = [old.counts.get(id) ?? 0, now.counts.get(id) ?? 0];
		if (was === is)
			continue;
		counts[id] = [was, is];
		// all that is gone was on merged away nodes, or a status besogo can't read
		const onMerged = old.merged.get(id) ?? 0;
		const unreadable = id === 'S' ? unreadableStatuses : 0;
		if (is < was && is === was - onMerged - unreadable)
		{
			if (onMerged)
				merged[id] = onMerged;
			continue;
		}
		if (SETUP.has(id))
		{
			setup[id] = is - was;
			continue;
		}
		const expected = id === 'C' ? is <= was
			: HEADER.has(id) ? is === 1
			: is < was && (NOT_KEPT.has(id) || GAME_INFO.has(id));
		const illegal = old.movesLeftOut.get(id) ?? 0;
		if (!expected)
			unexpected.push(id + ' ' + was + '→' + is + (illegal ? ' (' + illegal + ' illegal, besogo leaves it out)' : ''));
	}
	// a status that stays is written as it was (an S besogo writes otherwise is a status lost); one lost is told by the
	// count
	const readable = old.statuses.filter(status => statusReads(besogo, status));
	const writtenUnreadable = now.statuses.filter(status => !statusReads(besogo, status));
	if (writtenUnreadable.length)
		unexpected.push('S written that besogo can\'t read: ' + writtenUnreadable.map(status => JSON.stringify(status)).join(', '));
	const statuses = values => values.slice().sort().join(' | ');
	if (readable.length === now.statuses.length && statuses(readable) !== statuses(now.statuses))
	{
		const changed = readable.filter(status => !now.statuses.includes(status));
		unexpected.push('S written otherwise: ' + changed.map(status => JSON.stringify(status)).join(', '));
	}
	const kept = new Set(now.comments.keys());
	const lost = [...old.comments].filter(([text]) => !kept.has(text));
	const lostOnKeptNodes = lost.filter(([, comment]) => comment.onKeptNode).map(([, comment]) => comment.written);
	if (lostOnKeptNodes.length)
		unexpected.push('C lost: ' + lostOnKeptNodes.map(comment => JSON.stringify(comment.slice(0, 40))).join(', '));
	return { counts, unexpected, mergedAway: merged, setup, unreadableStatuses };
}

//The count of the values of each property of a parsed SGF (a point range of a point list as its points), the part of
//them on the nodes besogo merged away (by their place, depth first), the illegal moves, the statuses of the nodes that
//stay, and its comments: the comment of each node as besogo reads it (loadSgf.js joins the C of a node, treeProblemUpdater.js
//takes one leading + as the mark of a correct move) => the comment as it was written and whether a node that stays has it
function propertyCounts(tree, { mergedAway = new Set(), movesLeftOut = new Set() } = {})
{
	const counts = new Map();
	const merged = new Map();
	const leftOut = new Map();
	const statuses = [];
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
		for (const prop of node.props)
		{
			const id = ALIASES[prop.id] ?? prop.id;
			let count = prop.values.length;
			if (POINT_LISTS.has(prop.id))
				count = prop.values.reduce((sum, value) => sum + pointsIn(value), 0);
			counts.set(id, (counts.get(id) ?? 0) + count);
			if (away)
				merged.set(id, (merged.get(id) ?? 0) + count);
			else if (id === 'S')
				statuses.push(...prop.values);
			if ((id === 'B' || id === 'W') && movesLeftOut.has(index))
				leftOut.set(id, (leftOut.get(id) ?? 0) + count);
		}
	});
	return { counts, merged, movesLeftOut: leftOut, statuses, comments };
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

//The stones of the root and of a node with setup stones, as besogo shows them: setup listed twice, or a stone set where
//it already is, shows nothing more and isn't written again, but every stone shown must still be there
function boardOf(node, turn = null)
{
	if (node.parent != null && node.getType() !== 'setup')
		return '';
	const size = node.getSize();
	const board = [];
	for (let y = 1; y <= size.y; ++y)
		for (let x = 1; x <= size.x; ++x)
		{
			const point = turn ? turn.apply({ x, y }, size) : { x, y };
			const stone = turn ? turn.applyOnColor(node.getStone(x, y)) : node.getStone(x, y);
			board[(point.y - 1) * size.x + point.x - 1] = { [-1]: 'b', 1: 'w' }[stone] ?? '.';
		}
	return board.join('');
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

//What a tree plays as, with its G and the stones its setup shows, in the coordinates turn turns it to (none: as it is): every move from every position, real or merged by a transposition, the order left aside, with
//whether each position is correct, wrong or neither. A position met again on its own line (a ko) counts as reached.
function signature(root, turn = null)
{
	const size = root.getSize();
	const point = move => turn ? turn.apply(move, size) : move;
	const color = stone => turn ? turn.applyOnColor(stone) : stone;
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
			.map(([move, child]) => (move ? (color(move.color ?? child.move?.color) == -1 ? 'B' : 'W') + point(move).x + ',' + point(move).y : 'pass')
				+ '(' + of(child) + ')')
			.sort();
		onLine.delete(node);
		const result = node.correct + ':' + goalOf(node) + ':' + boardOf(node, turn) + ':' + moves.join(';');
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
	//what the tree plays as and shows, as loaded, in the coordinates it is turned to: the rewrite is compared with it
	const loadedSignature = signature(editor.getRoot(), turn);
	if (turn.hFlip || turn.vFlip)
		editor.applyTransformation(turn);
	const reordered = correctFirst(editor.getRoot());
	//a plain object, the one of besogo comes from its own context
	const transformation = JSON.parse(JSON.stringify(editor.getTransformationFromSgf()));
	return { transformation, loadedSignature, changed: reordered || transformation.transpose || transformation.flipX || transformation.flipY };
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
