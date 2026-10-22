/**
 * Comments keep their coordinates and colours as they are in the stored SGF,
 * without the transformation (corner, rotation, colour swap) the board is
 * shown with. The text is converted when it is shown and when it is saved.
 *
 * The server applies the same rewrite when a new SGF version reorients the
 * problem (App\Utility\CommentCoordinates); keep the two in sync.
 */
import { COORD_RE, LINK_RE } from './coordParser';

/** Maps a coordinate label such as "D4"; null keeps the text as written. */
export type CoordinateMapping = (label: string) => string | null;

const COLOR_WORD_RE = /\b(black|white)\b/gi;
// A b/w letter that is not the end of a longer word or number.
const COLOR_LETTER_RE = /(?<![\p{L}\p{N}])[bwBW]/gu;
// What follows a colour letter that marks the colour of a coordinate: "b E2", "w-T19", "bL18".
const FOLLOWED_BY_COORDINATE_RE = /^[\s,:-]*[A-HJ-Ta-hj-t][1-9][0-9]?(?![\p{L}\p{N}])/u;

function swapColorWord(word: string): string
{
	const swapped = word.toLowerCase() === 'black' ? 'white' : 'black';
	if (word === word.toUpperCase())
		return swapped.toUpperCase();
	if (word[0] === word[0].toUpperCase())
		return swapped[0].toUpperCase() + swapped.slice(1);
	return swapped;
}

/**
 * Swaps black and white: the words, a standalone B or W ("B kills"), and a
 * b/w letter marking the colour of a coordinate ("b E2", "W-T19", "bL18").
 */
export function swapColors(text: string): string
{
	return text
		.replace(COLOR_WORD_RE, swapColorWord)
		.replace(COLOR_LETTER_RE, (letter: string, offset: number, whole: string) =>
		{
			const rest = whole.slice(offset + 1);
			const standalone = !/^[\p{L}\p{N}]/u.test(rest);
			const isUpper = letter === letter.toUpperCase();
			if (!(standalone && isUpper) && !FOLLOWED_BY_COORDINATE_RE.test(rest))
				return letter;
			const swapped = letter.toLowerCase() === 'b' ? 'w' : 'b';
			return isUpper ? swapped.toUpperCase() : swapped;
		});
}

/**
 * Rewrites every coordinate in the text through `mapping` (keeping the case
 * of its letter) and optionally swaps the colours. Links stay as written.
 */
export function transformCommentText(text: string, mapping: CoordinateMapping, invertColors: boolean): string
{
	// split() keeps the links, the captured separators, at the odd indexes
	return text.split(LINK_RE)
		.map((part, index) => index % 2 ? part : transformTextOutsideLinks(part, mapping, invertColors))
		.join('');
}

function transformTextOutsideLinks(text: string, mapping: CoordinateMapping, invertColors: boolean): string
{
	const transformed = text.replace(COORD_RE, (match: string, letter: string, digits: string) =>
	{
		if (parseInt(digits, 10) > 19)
			return match;
		const mapped = mapping(letter.toUpperCase() + digits);
		if (!mapped)
			return match;
		return letter === letter.toLowerCase() ? mapped[0].toLowerCase() + mapped.slice(1) : mapped;
	});
	return invertColors ? swapColors(transformed) : transformed;
}
