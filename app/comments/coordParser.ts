/**
 * Coordinate reference parser for comment text.
 *
 * Go players reference board points in many ways in prose:
 *   - single point:        "M16", "black M16"
 *   - a sequence:          "M16-N17-M20", "R17->R19->P19", "Q2,P3,N3", "S2 S4 Q1"
 *   - alternatives:        "F2/G1", "D4 or T2"
 *   - colored moves:       "b D18, w B19", "black R19", "W-T19"
 *
 * This parser tokenizes such text into "slices": plain text or a run of
 * coordinates, tagging each run as a sequence or alternative (a lone
 * coordinate is a one-move sequence) and recording an optional color per
 * coordinate. It is orientation-agnostic (coordinates are absolute board
 * intersections) and does NOT depend on besogo.
 *
 * It deliberately uses heuristics that match the actual comment corpus and
 * degrades gracefully (a coordinate it cannot link into a run is still kept).
 */
export type CoordColor = 'b' | 'w';
export type CoordGroupKind = 'sequence' | 'alternative';

export interface CoordToken
{
	/** Normalized coordinate, e.g. "M16" */
	coord: string;
	/** As written, e.g. "m16" */
	raw: string;
	/** Optional color marker the author used, e.g. "b" / "white" */
	color?: CoordColor;
}

export interface CoordRun
{
	type: 'coords';
	kind: CoordGroupKind;
	tokens: CoordToken[];
	/** separators[i] sits between tokens[i] and tokens[i+1] */
	separators: string[];
}

export type ParsedSlice =
	| { type: 'text'; text: string }
	| CoordRun;

// Go columns a-t (skipping i), case-insensitive, then a number 1-19.
// Note: this intentionally matches a letter immediately followed by digits, so
// "B1" is a coordinate; a standalone "b " is a color marker.
const COORD_RE = /([A-Ha-hJ-Tj-t])([1-9]\d?)/g;

function normalizeCoord(letter: string, digits: string): string
{
	return letter.toUpperCase() + digits;
}

// Words that may appear in a gap between two moves of one line. Anything else
// (e.g. "is", "the", "because") is prose and ends the run. Move verbs, connectives
// and colour markers are all allowed so prose like "kill with H18" still reads as
// a move link while "is wrong for black" does not.
const CONNECTOR_WORDS = new Set([
	// connectives
	'then', 'and', 'if', 'after', 'before', 'next', 'now', 'with', 'at', 'into',
	'to', 'by', 'or', 'either', 'when', 'as', 'also', 'first', 'second', 'finally',
	'again', 'followed', 'still', 'until', 'once', 'while',
	// move verbs
	'play', 'plays', 'played', 'playing', 'take', 'takes', 'took', 'answer',
	'answers', 'respond', 'responds', 'connect', 'connects', 'capture', 'captures',
	'fill', 'fills', 'throw', 'throws', 'atari', 'kill', 'kills', 'force', 'forces',
	'make', 'makes', 'get', 'gets', 'eat', 'eats', 'block', 'blocks', 'cut', 'cuts',
	'extend', 'extends', 'nobi', 'put', 'puts', 'keep', 'keeps', 'lead', 'leads',
	'result', 'results', 'better',
	// modal auxiliaries linking a follow-up move
	'can', 'could', 'should', 'would', 'will', 'may', 'might', 'must', 'do',
	'does', 'did'
]);

function isColorWord(w: string): boolean
{
	const lw = w.toLowerCase();
	return lw === 'black' || lw === 'white' || lw === 'b' || lw === 'w';
}

// Detects a color marker attached to a coordinate. The marker sits either
// immediately before the coordinate (e.g. "white C1", ",w", "bL18") or, in a
// phrase like "B kills with C1", at the very start of the gap.
function detectColor(prefix: string): CoordColor | undefined
{
	const trimmed = prefix.trim();
	if (!trimmed) 
		return undefined;

	// Full word right before the coordinate: "white C1".
	const word = trimmed.match(/(black|white)\b\s*$/i);
	if (word) 
		return /^black$/i.test(word[1]) ? 'b' : 'w';

	// Bare letter right before the coordinate, after punctuation/space/start:
	// "w C1", ",w C1", "-B?", ";w". The letter must not be part of a bigger word.
	const letter = trimmed.match(/(?:^|[^\p{L}])([bwBW])\s*[,\-:]?\s*$/u);
	if (letter) 
		return /[bB]/.test(letter[1]) ? 'b' : 'w';

	// Leading bare single-letter marker: "B kills with C1", "W plays at D2".
	const lead = trimmed.match(/^([bwBW])\b/);
	if (lead) 
		return /[bB]/.test(lead[1]) ? 'b' : 'w';

	// Leading full word: "black kills with C1".
	const leadWord = trimmed.match(/^(black|white)\b/i);
	if (leadWord) 
		return /^black$/i.test(leadWord[1]) ? 'b' : 'w';

	return undefined;
}

// Classifies the text sitting between two consecutive coordinates.
function classifyGap(gap: string): 'sequence' | 'alternative' | 'break'
{
	const t = gap.trim();
	if (!t) 
		return 'sequence'; // adjacent coordinates, e.g. "M16N17"

	// pure punctuation connectors. NOTE: '.' is deliberately excluded - it ends
	// a sentence, so it splits the run rather than joining two coordinates.
	if (/^(->|-->|—|–|→)$/.test(t)) 
		return 'sequence';
	if (/^[-,–—;:>+]+$/.test(t)) 
		return 'sequence';
	if (/^\/$/.test(t)) 
		return 'alternative';

	// A sentence boundary (period / question mark / exclamation) ends the run.
	if (/[.!?]/.test(t)) 
		return 'break';

	// "instead of" / "instead" marks a replacement choice (an alternative).
	if (/\binstead\b/i.test(t)) 
		return 'alternative';

	const words = t.match(/[A-Za-z]+/g) || [];

	// No letters: only punctuation/whitespace joins. A stray digit (e.g. a
	// numbered list marker) is not a connector and splits the run.
	if (words.length === 0)
		return /[0-9]/.test(t) ? 'break' : 'sequence';

	// Any prose word (not a connector and not a colour marker) means the two
	// coordinates are not a single move line.
	if (!words.every(w => CONNECTOR_WORDS.has(w.toLowerCase()) || isColorWord(w))) 
		return 'break';

	// "or" / "either" join coordinates as choices, not a sequence.
	if (words.some(w => /^(or|either)$/i.test(w))) 
		return 'alternative';

	return 'sequence';
}

/**
 * Splits comment text into text slices and coordinate runs.
 * Coordinates linked by a connector are grouped into a single run; anything in
 * between that is not a recognized connector splits the run.
 */
export function parseCoordinateReferences(text: string): ParsedSlice[]
{
	const matches = [...text.matchAll(COORD_RE)].filter(m => parseInt(m[2], 10) <= 19);
	const slices: ParsedSlice[] = [];

	if (matches.length === 0)
	{
		if (text) 
			slices.push({ type: 'text', text });
		return slices;
	}

	let cursor = 0;
	let run: CoordRun | null = null;

	for (const m of matches)
	{
		const start = m.index;
		const end = start + m[0].length;
		const gap = text.slice(cursor, start);
		const conn = run ? classifyGap(gap) : 'break';

		if (!run || conn === 'break')
		{
			// Emit the text before this coordinate (leading text or a break).
			if (gap) 
				slices.push({ type: 'text', text: gap });
			run = { type: 'coords', kind: 'sequence', tokens: [], separators: [] };
			slices.push(run);
			cursor = start;
		}
		else
		{
			// Continue the run; the gap is a connector stored as a separator.
			// Keep the author's surrounding whitespace (collapsed) so word
			// separators like " and " stay readable instead of "B17andA16".
			run.separators.push(gap ? gap.replace(/\s+/g, ' ') : ' ');
			run.kind = conn === 'alternative' ? 'alternative' : 'sequence';
		}

		run.tokens.push({
			coord: normalizeCoord(m[1], m[2]),
			raw: m[0],
			color: detectColor(gap)
		});
		cursor = end;
	}

	if (cursor < text.length) 
		slices.push({ type: 'text', text: text.slice(cursor) });

	return slices;
}
