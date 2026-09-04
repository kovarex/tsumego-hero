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
 * coordinates, tagging each run as a single / sequence / alternative and
 * recording an optional color per coordinate. It is orientation-agnostic
 * (coordinates are absolute board intersections) and does NOT depend on besogo.
 *
 * It deliberately uses heuristics that match the actual comment corpus and
 * degrades gracefully (a coordinate it cannot link into a run is still kept).
 */
export type CoordColor = 'b' | 'w';
export type CoordGroupKind = 'single' | 'sequence' | 'alternative';

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

// Detects a color marker immediately preceding a coordinate (within the given
// prefix). Prefers full words ("black"/"white"), then a bare b/w/B/W.
function detectColor(prefix: string): CoordColor | undefined
{
	const trimmed = prefix.trim();
	const word = trimmed.match(/(black|white)\b\s*$/i);
	if (word) 
		return /^black$/i.test(word[1]) ? 'b' : 'w';

	// A bare letter, but only if it is a standalone word (not part of a bigger
	// word like "ab" or the coordinate's own letter).
	const letter = trimmed.match(/(?:^|\s)([bwBW])\s*[,\-:]?\s*$/);
	if (letter) 
		return /[bB]/.test(letter[1]) ? 'b' : 'w';

	return undefined;
}

// Removes a trailing color marker (and its surrounding punctuation) from a gap
// so that a comma-list like "w C1, b E2" still classifies as a sequence rather
// than being split by the embedded color letters.
function stripTrailingColor(gap: string): string
{
	let g = gap;
	g = g.replace(/\s*(black|white)\b\s*[,\-:]?\s*$/i, '');
	g = g.replace(/(?:^|\s)([bwBW])\s*[,\-:]?\s*$/i, '');
	return g;
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

	// connector words
	if (/^(then|and|if|after|before|next)$/i.test(t)) 
		return 'sequence';
	if (/^(or|either)$/i.test(t)) 
		return 'alternative';

	// whitespace around punctuation counts as a connector too
	if (/^[\s\-–—,;:>+]+$/.test(t)) 
		return 'sequence';

	return 'break';
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
		const conn = run ? classifyGap(stripTrailingColor(gap)) : 'break';

		if (!run || conn === 'break')
		{
			// Emit the text before this coordinate (leading text or a break).
			if (gap) 
				slices.push({ type: 'text', text: gap });
			run = { type: 'coords', kind: 'single', tokens: [], separators: [] };
			slices.push(run);
			cursor = start;
		}
		else
		{
			// Continue the run; the gap is a connector stored as a separator.
			run.separators.push(gap.trim() || ' ');
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
